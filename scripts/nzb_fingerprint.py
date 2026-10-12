#!/usr/bin/env python3
"""Read-only NZB/yEnc/PAR2/RAR metadata collector and uploader-family PoC.

The tool parses local NZB files and, when explicitly configured, fetches only
the listed article IDs using NNTP ARTICLE. It does not post, modify, or probe
servers. Output is JSON and deliberately omits credentials and raw subjects.
"""

from __future__ import annotations

import argparse
import binascii
import email.utils
import hashlib
import hmac
import ipaddress
import getpass
import json
import re
import socket
import ssl
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


YENC_RE = re.compile(r"=ybegin\s+(?P<attrs>[^\r\n]+)", re.IGNORECASE)
YEND_RE = re.compile(r"=yend\s+(?P<attrs>[^\r\n]+)", re.IGNORECASE)
ATTR_RE = re.compile(r"([\w-]+)=((?:\"[^\"]*\")|(?:'[^']*')|\S+)")
RAR_RE = re.compile(r"\.part(?P<part>\d+)\.rar\b|\.rar\b", re.IGNORECASE)
PAR_RE = re.compile(r"\.vol(?P<first>\d+)[+-](?P<count>\d+)\.par2\b|\.par2\b", re.IGNORECASE)
ID_RE = re.compile(r"<[^<>\s]+>")
PAR_MAGIC = b"PAR2\x00PKT"


def attrs(text: str) -> dict[str, str]:
    result = {key.lower(): value.strip("\"'") for key, value in ATTR_RE.findall(text)}
    name_match = re.search(r"(?:^|\s)name=(\"[^\"]*\"|'[^']*'|.*?)(?=\s+(?:line|size|part|total|crc32|pcrc32|begin|end)=|$)", text, re.IGNORECASE)
    if name_match:
        result["name"] = name_match.group(1).strip("\"'").strip()
    return result


def safe_int(value: str | None) -> int | None:
    try:
        return int(value) if value is not None else None
    except ValueError:
        return None


def parse_date(value: str | None) -> datetime | None:
    if not value:
        return None
    try:
        parsed = email.utils.parsedate_to_datetime(value)
        return parsed.astimezone(timezone.utc) if parsed.tzinfo else parsed.replace(tzinfo=timezone.utc)
    except (TypeError, ValueError, OverflowError):
        return None


def message_id_profile(message_id: str) -> dict[str, Any]:
    local, _, domain = message_id[1:-1].rpartition("@") if message_id.startswith("<") and message_id.endswith(">") else (message_id, "", "")
    return {"length": len(message_id), "local_length": len(local), "domain": domain,
            "local_alphabet": "base64url-like" if re.fullmatch(r"[A-Za-z0-9_-]+", local or "!") else "mixed",
            "separator_counts": {separator: local.count(separator) for separator in (".", "_", "-", "+")}}


def exposed_posting_ips(articles: list[dict[str, Any]]) -> list[str]:
    addresses: set[str] = set()
    pattern = re.compile(r"(?<![0-9A-Fa-f])(?:\d{1,3}\.){3}\d{1,3}(?![0-9A-Fa-f])|(?<![0-9A-Fa-f:])(?:[0-9A-Fa-f]{0,4}:){2,}[0-9A-Fa-f:]{0,4}(?![0-9A-Fa-f:])")
    for article in articles:
        headers = article.get("metadata", {}).get("headers", {})
        for name in ("nntp-posting-host", "injection-info", "x-trace", "x-poster"):
            for candidate in pattern.findall(headers.get(name, "")):
                try:
                    addresses.add(str(ipaddress.ip_address(candidate)))
                except ValueError:
                    pass
    return sorted(addresses)


def parse_nzb(path: Path) -> list[dict[str, Any]]:
    files: list[dict[str, Any]] = []
    source = path.read_bytes()
    if b"<!ENTITY" in source.upper():
        raise ValueError("Entity declarations are not accepted in NZB input")
    source = re.sub(
        rb"<!DOCTYPE\s+nzb\s+PUBLIC\s+['\"]-//newzBin//DTD NZB 1\.1//EN['\"]\s+['\"]http://www\.newzbin\.com/DTD/nzb/nzb-1\.1\.dtd['\"]\s*>",
        b"",
        source,
        flags=re.IGNORECASE,
    )
    if b"<!DOCTYPE" in source.upper():
        raise ValueError("Unsupported DTD in NZB input")
    root = ET.fromstring(source)
    root_meta = [{"type": node.attrib.get("type"), "value": node.text or ""}
                 for node in root.iter() if node.tag.rsplit("}", 1)[-1] == "meta"]
    for file_node in root.iter():
        if file_node.tag.rsplit("}", 1)[-1] != "file":
            continue
        subject = file_node.attrib.get("subject", "")
        groups = [node.text or "" for node in file_node.iter() if node.tag.rsplit("}", 1)[-1] == "group"]
        segments: list[dict[str, Any]] = []
        for node in file_node.iter():
            if node.tag.rsplit("}", 1)[-1] != "segment":
                continue
            message_id = (node.text or "").strip()
            if message_id and not message_id.startswith("<") and not message_id.endswith(">"):
                message_id = f"<{message_id}>"
            if ID_RE.fullmatch(message_id):
                segments.append({"bytes": safe_int(node.attrib.get("bytes")), "number": safe_int(node.attrib.get("number")), "id": message_id})
        files.append({"subject": subject, "date": file_node.attrib.get("date"), "poster": file_node.attrib.get("poster"),
                      "groups": groups, "segments": segments, "nzb_meta": root_meta})
    return files


@dataclass
class Nntp:
    host: str
    port: int
    user: str | None = None
    password: str | None = None
    tls: bool = False
    timeout: float = 12.0

    def __post_init__(self) -> None:
        raw = socket.create_connection((self.host, self.port), timeout=self.timeout)
        self.sock = ssl.create_default_context().wrap_socket(raw, server_hostname=self.host) if self.tls else raw
        self.file = self.sock.makefile("rwb", buffering=0)
        code, greeting = self._response()
        self.greeting = greeting
        if code not in (200, 201):
            raise RuntimeError(f"NNTP greeting rejected: {code}")
        if self.user:
            if any(char in self.user for char in "\r\n") or (self.password and any(char in self.password for char in "\r\n")):
                raise ValueError("NNTP credentials may not contain line breaks")
            self.file.write(f"AUTHINFO USER {self.user}\r\n".encode("utf-8"))
            auth_code, _ = self._response()
            if auth_code == 381 and self.password:
                self.file.write(f"AUTHINFO PASS {self.password}\r\n".encode("utf-8"))
                auth_code, _ = self._response()
            if auth_code != 281:
                raise RuntimeError(f"NNTP authentication failed with response {auth_code}")

    def _response(self) -> tuple[int, str]:
        line = self.file.readline(8192).decode("utf-8", "replace").rstrip("\r\n")
        match = re.match(r"(\d{3})\s?(.*)", line)
        if not match:
            raise RuntimeError("Invalid NNTP response")
        return int(match.group(1)), match.group(2)

    def command(self, command: str, accepted: tuple[int, ...] = (281, 381, 250, 223)) -> str:
        self.file.write(command.encode("ascii") + b"\r\n")
        code, message = self._response()
        if code not in accepted:
            raise RuntimeError(f"NNTP command failed with response {code}")
        return message

    def status(self, verb: str, message_id: str) -> dict[str, Any]:
        if "\r" in message_id or "\n" in message_id:
            raise ValueError("Invalid message ID")
        self.file.write(f"{verb} {message_id}\r\n".encode("ascii"))
        code, message = self._response()
        found = (verb == "STAT" and code == 223) or (verb == "HEAD" and code == 221)
        result: dict[str, Any] = {"code": code, "message": message, "available": found}
        if found and verb == "HEAD":
            lines = []
            while True:
                line = self.file.readline(8192)
                if not line:
                    raise RuntimeError("Truncated NNTP HEAD response")
                if line == b".\r\n":
                    break
                if line.startswith(b".."):
                    line = line[1:]
                lines.append(line.decode("utf-8", "replace").rstrip("\r\n"))
            result["header_lines"] = lines
        return result

    def capabilities(self) -> list[str]:
        self.file.write(b"CAPABILITIES\r\n")
        code, _ = self._response()
        if code != 101:
            return []
        values = []
        while True:
            line = self.file.readline(8192)
            if not line:
                raise RuntimeError("Truncated NNTP capability response")
            if line == b".\r\n":
                break
            values.append(line.decode("ascii", "replace").strip())
        return values

    def overview_format(self) -> list[str]:
        self.file.write(b"LIST OVERVIEW.FMT\r\n")
        code, _ = self._response()
        if code != 215:
            return []
        values = []
        while True:
            line = self.file.readline(8192)
            if not line:
                raise RuntimeError("Truncated NNTP overview format response")
            if line == b".\r\n":
                break
            values.append(line.decode("utf-8", "replace").strip())
        return values

    def overview(self, group: str, article_number: int, format_fields: list[str]) -> dict[str, Any]:
        if not re.fullmatch(r"[A-Za-z0-9+._-]+", group):
            return {"group": group, "available": False, "error": "invalid group name"}
        if article_number < 1:
            return {"group": group, "available": False, "error": "invalid article number"}
        self.file.write(f"GROUP {group}\r\n".encode("ascii"))
        code, message = self._response()
        if code != 211:
            return {"group": group, "available": False, "group_response": code}
        self.file.write(f"OVER {article_number}-{article_number}\r\n".encode("ascii"))
        over_code, _ = self._response()
        rows = []
        if over_code == 224:
            while True:
                line = self.file.readline(65536)
                if not line:
                    raise RuntimeError("Truncated NNTP overview response")
                if line == b".\r\n":
                    break
                if line.startswith(b".."): line = line[1:]
                rows.append(line.decode("utf-8", "replace").rstrip("\r\n"))
        values = rows[0].split("\t") if rows else []
        field_names = []
        for value in format_fields:
            if value.startswith(":"):
                field_names.append(value[1:])
            elif value.endswith(":"):
                field_names.append(value[:-1])
            else:
                field_names.append(value.split(":", 1)[0])
        article_number_value = values.pop(0) if values else None
        return {"group": group, "available": bool(rows), "group_response": code,
                "overview_response": over_code, "article_number": article_number_value,
                "overview_row": rows[0] if rows else None,
                "overview_fields": dict(zip(field_names, values))}

    def overview_message_id(self, group: str, message_id: str, format_fields: list[str]) -> dict[str, Any]:
        if not re.fullmatch(r"[A-Za-z0-9+._-]+", group):
            return {"group": group, "available": False, "error": "invalid group name"}
        self.file.write(f"GROUP {group}\r\n".encode("ascii"))
        group_code, _ = self._response()
        if group_code != 211:
            return {"group": group, "available": False, "group_response": group_code}
        self.file.write(f"OVER {message_id}\r\n".encode("ascii"))
        code, _ = self._response()
        if code != 224:
            return {"group": group, "available": False, "overview_response": code}
        rows = []
        while True:
            line = self.file.readline(65536)
            if not line:
                raise RuntimeError("Truncated NNTP OVER MSGID response")
            if line == b".\r\n":
                break
            rows.append(line.decode("utf-8", "replace").rstrip("\r\n"))
        if not rows:
            return {"group": group, "available": False, "overview_response": code}
        row = rows[0]
        values = row.split("\t")
        article_number = values.pop(0) if values else None
        names = [value[1:] if value.startswith(":") else value[:-1] if value.endswith(":") else value.split(":", 1)[0]
                 for value in format_fields]
        return {"group": group, "available": True, "overview_response": code,
                "article_number": article_number, "overview_row": row,
                "overview_fields": dict(zip(names, values))}

    def article(self, message_id: str, limit: int) -> tuple[dict[str, str], bytes]:
        self.file.write(f"ARTICLE {message_id}\r\n".encode("ascii"))
        code, _ = self._response()
        if code != 220:
            return {}, b""
        headers: dict[str, str] = {}
        header_lines: list[str] = []
        current_header: str | None = None
        body = bytearray()
        body_hash = hashlib.sha256()
        body_bytes = 0
        in_headers = True
        while True:
            line = self.file.readline(16_777_217)
            if not line:
                raise RuntimeError("Truncated NNTP ARTICLE response")
            if not line.endswith(b"\n"):
                raise RuntimeError("NNTP ARTICLE line exceeded the 16 MiB safety limit")
            if line == b".\r\n":
                break
            if line.startswith(b".."): line = line[1:]
            if in_headers:
                if line in (b"\r\n", b"\n"):
                    in_headers = False
                elif line[:1] in (b" ", b"\t") and header_lines:
                    raw_continuation = line.decode("utf-8", "replace").rstrip("\r\n")
                    header_lines.append(raw_continuation)
                    if current_header:
                        headers[current_header] += " " + raw_continuation.strip()
                elif b":" in line:
                    key, value = line.split(b":", 1)
                    decoded_key = key.decode("ascii", "replace").lower()
                    decoded_value = value.decode("utf-8", "replace").strip()
                    if decoded_key in headers:
                        headers[decoded_key] += " " + decoded_value
                    else:
                        headers[decoded_key] = decoded_value
                    current_header = decoded_key
                    header_lines.append(line.decode("utf-8", "replace").rstrip("\r\n"))
            else:
                body_hash.update(line)
                body_bytes += len(line)
                if len(body) < limit:
                    body.extend(line[:limit - len(body)])
        headers["__raw_order"] = "\n".join(header_lines)
        headers["__article_body_sha256"] = body_hash.hexdigest()
        headers["__article_body_bytes"] = str(body_bytes)
        return headers, bytes(body)

    def close(self) -> None:
        try:
            self.command("QUIT", accepted=(205,))
        except Exception:
            pass
        self.file.close()
        self.sock.close()


def parse_article(headers: dict[str, str], body: bytes) -> dict[str, Any]:
    text = body.decode("latin-1", "replace")
    begin_matches = list(YENC_RE.finditer(text))
    end_matches = list(YEND_RE.finditer(text))
    ybegin = attrs(begin_matches[0].group("attrs")) if begin_matches else {}
    yend = attrs(end_matches[-1].group("attrs")) if end_matches else {}
    decoded = decode_yenc(text[begin_matches[0].end():end_matches[-1].start()] if begin_matches and end_matches else "") if begin_matches and end_matches else None
    yp = re.search(r"=ypart\s+([^\r\n]+)", text, re.IGNORECASE)
    ypart = attrs(yp.group(1)) if yp else {}
    parsed_date = parse_date(headers.get("date"))
    injection_date = parse_date(headers.get("injection-date") or headers.get("nntp-posting-date"))
    visible_headers = {key: value for key, value in headers.items() if key != "__raw_order"}
    ordered_keys = [line.split(":", 1)[0] for line in headers.get("__raw_order", "").splitlines() if ":" in line and not line[:1].isspace()]
    raw_ybegin = begin_matches[0].group(0).strip() if begin_matches else None
    raw_ypart = yp.group(0).strip() if yp else None
    raw_yend = end_matches[-1].group(0).strip() if end_matches else None
    encoded_lines = text[begin_matches[0].end():end_matches[-1].start()].split("\r\n") if begin_matches and end_matches else []
    encoded_lines = [line for line in encoded_lines if line and not line.startswith("=ypart")]
    actual_crc = f"{binascii.crc32(decoded) & 0xffffffff:08x}" if decoded is not None else None
    multipart = bool(ypart)
    expected_segment_crc = yend.get("pcrc32") if multipart else (yend.get("crc32") or yend.get("pcrc32"))
    crc_field_checked = "pcrc32" if multipart and yend.get("pcrc32") else "crc32" if not multipart and yend.get("crc32") else "pcrc32" if not multipart and yend.get("pcrc32") else None
    part_begin = safe_int(ypart.get("begin"))
    part_end = safe_int(ypart.get("end"))
    total_size = safe_int(ybegin.get("size"))
    part_size = safe_int(yend.get("size"))
    ypart_range_present = "begin" in ypart or "end" in ypart
    ypart_range_valid = None if not ypart_range_present else (
        part_begin is not None and part_end is not None and part_begin >= 1 and part_end >= part_begin
        and (total_size is None or part_end <= total_size)
    )
    header_size_matches = None if total_size is None or decoded is None else (
        part_end is not None and part_end <= total_size if multipart else len(decoded) == total_size
    )
    crc_is_hex = bool(expected_segment_crc and re.fullmatch(r"[0-9a-fA-F]{1,8}", expected_segment_crc))
    crc_matches = None if not expected_segment_crc or (multipart and not yend.get("pcrc32")) else (
        bool(crc_is_hex and actual_crc and actual_crc.lower() == expected_segment_crc.lower().zfill(8))
    )
    return {
        "headers": {key: visible_headers[key] for key in ("message-id", "from", "organization", "user-agent", "x-newsreader", "x-mailer", "x-poster", "x-trace", "injection-info", "injection-date", "nntp-posting-host", "nntp-posting-date", "path", "xref", "references", "date", "newsgroups", "subject", "lines", "bytes", "x-received-bytes") if key in visible_headers},
        "article_body_sha256": headers.get("__article_body_sha256"),
        "article_body_bytes": safe_int(headers.get("__article_body_bytes")),
        "article_body_complete": safe_int(headers.get("__article_body_bytes")) == len(body),
        "raw_header_lines": headers.get("__raw_order", "").splitlines(),
        "header_profile": {"ordered_names": ordered_keys, "ordered_names_sha256": hashlib.sha256("\n".join(ordered_keys).encode()).hexdigest(), "folded_header_count": sum(1 for line in headers.get("__raw_order", "").splitlines() if line[:1].isspace()), "header_count": len(ordered_keys)},
        "timestamps": {"poster_date_utc": parsed_date.isoformat() if parsed_date else None,
                        "injection_date_utc": injection_date.isoformat() if injection_date else None,
                        "injection_delay_seconds": (injection_date - parsed_date).total_seconds() if injection_date and parsed_date else None},
        "yenc": {"begin": ybegin or None, "end": yend or None, "ypart": ypart or None,
                 "raw_controls": {"ybegin": raw_ybegin, "ypart": raw_ypart, "yend": raw_yend},
                 "part": safe_int(ybegin.get("part")), "total_size": total_size,
                 "line": safe_int(ybegin.get("line")), "name": ybegin.get("name"),
                 "total_parts": safe_int(ybegin.get("total")),
                 "part_size": part_size, "part_begin": part_begin, "part_end": part_end,
                 "header_size_matches": header_size_matches,
                 "end_part": safe_int(yend.get("part")),
                 "ypart_range_valid": ypart_range_valid,
                 "crc32": yend.get("crc32"), "pcrc32": yend.get("pcrc32"),
                 "decoded_bytes": len(decoded) if decoded is not None else None,
                 "decoded_size_matches_yend": None if part_size is None else bool(decoded is not None and part_size == len(decoded)),
                 "decoded_size_matches_ypart_range": None if not ypart_range_present else bool(decoded is not None and ypart_range_valid is True and part_end - part_begin + 1 == len(decoded)),
                 "decoded_crc32": f"{binascii.crc32(decoded) & 0xffffffff:08x}" if decoded is not None else None,
                 "decoded_sha256": hashlib.sha256(decoded).hexdigest() if decoded is not None else None,
                 "encoded_payload_sha256": hashlib.sha256(text[begin_matches[0].end():end_matches[-1].start()].encode("latin-1")).hexdigest() if begin_matches and end_matches else None,
                 "crc_field_checked": crc_field_checked,
                 "crc_field_is_hex": crc_is_hex if expected_segment_crc else None,
                 "crc32_matches": crc_matches,
                 "encoded_line_lengths": {"minimum": min(map(len, encoded_lines)) if encoded_lines else None,
                                          "maximum": max(map(len, encoded_lines)) if encoded_lines else None,
                                          "unique": sorted(set(map(len, encoded_lines))) if encoded_lines else []},
                 "escape_marker_count": sum(line.count("=") for line in encoded_lines),
                 "wrapper_before": text[:begin_matches[0].start()][:2048] if begin_matches else None,
                 "wrapper_after": text[end_matches[-1].end():][:2048] if end_matches else None,
                 "wrapper_lines_before_ybegin": text[:begin_matches[0].start()].count("\n") if begin_matches else None,
                 "wrapper_lines_after_yend": text[end_matches[-1].end():].count("\n") if end_matches else None,
                 "begin_count": len(begin_matches), "end_count": len(end_matches)},
        "formats": inspect_binary_metadata(decoded if decoded is not None else body),
        "_decoded_bytes": decoded,
    }


def decode_yenc(payload_text: str) -> bytes:
    """Decode one yEnc payload, preserving the standard escape and +42 rules."""
    lines = [line for line in payload_text.split("\r\n") if line and not line.startswith("=ypart") and not line.startswith("=yend")]
    output = bytearray()
    escaped = False
    for line in lines:
        for char in line.encode("latin-1", "replace"):
            if escaped:
                output.append((char - 64 - 42) & 0xff)
                escaped = False
            elif char == 61:  # '=' escape
                escaped = True
            else:
                output.append((char - 42) & 0xff)
    return bytes(output)


def inspect_binary_metadata(data: bytes) -> dict[str, Any]:
    result: dict[str, Any] = {}
    pos4, pos5 = data.find(b"Rar!\x1a\x07\x00"), data.find(b"Rar!\x1a\x07\x01\x00")
    if pos4 >= 0 or pos5 >= 0:
        result["rar"] = {"version": "4" if pos4 >= 0 and (pos5 < 0 or pos4 < pos5) else "5", "signature_offset": min(p for p in (pos4, pos5) if p >= 0)}
    packets = []
    offset = 0
    unparsed_bytes = 0
    while True:
        marker = data.find(PAR_MAGIC, offset)
        if marker < 0 or marker + 64 > len(data):
            unparsed_bytes += len(data) - offset
            break
        unparsed_bytes += marker - offset
        length = int.from_bytes(data[marker + 8:marker + 16], "little")
        if length < 64 or marker + length > len(data):
            unparsed_bytes += len(data) - marker
            break
        packet_hash = data[marker + 16:marker + 32]
        set_id = data[marker + 32:marker + 48].hex()
        raw_type = data[marker + 48:marker + 64]
        packet_type = raw_type.replace(b"PAR 2.0\0", b"", 1).split(b"\0", 1)[0].decode("ascii", "replace")
        body = data[marker + 64:marker + length]
        packet = {"offset": marker, "length": length, "set_id": set_id, "type": packet_type,
                  "packet_md5": packet_hash.hex(),
                  "packet_md5_valid": hmac.compare_digest(packet_hash, hashlib.md5(data[marker + 32:marker + length]).digest()),
                  "payload_bytes": len(body), "packet_structure_valid": True}
        if packet_type in ("RecvSlic", "PkdRecvS") and len(body) >= 4:
            packet["recovery_exponent"] = int.from_bytes(body[:4], "little")
        elif packet_type in ("RecvSlic", "PkdRecvS"):
            packet["packet_structure_valid"] = False
        elif packet_type in ("Main", "PkdMain"):
            packed = packet_type == "PkdMain"
            packet["packed"] = packed
            packet["subslice_size"] = int.from_bytes(body[:8], "little") if packed and len(body) >= 8 else None
            base = 8 if packed else 0
            packet["recovery_block_size"] = int.from_bytes(body[base:base + 8], "little") if len(body) >= base + 8 else None
            count_offset = base + 8
            packet["recoverable_file_count"] = int.from_bytes(body[count_offset:count_offset + 4], "little") if len(body) >= count_offset + 4 else None
            ids_start = count_offset + 4
            packet["all_file_ids"] = [body[i:i + 16].hex() for i in range(ids_start, len(body) - 15, 16)]
            recoverable_count = packet.get("recoverable_file_count") or 0
            packet["recoverable_file_ids"] = packet["all_file_ids"][:recoverable_count]
            packet["non_recoverable_file_ids"] = packet["all_file_ids"][recoverable_count:]
            packet["file_ids"] = packet["recoverable_file_ids"]
            packet["packet_structure_valid"] = len(body) >= ids_start and (len(body) - ids_start) % 16 == 0 and recoverable_count <= len(packet["all_file_ids"])
        elif packet_type == "FileDesc" and len(body) >= 56:
            packet["file_id"] = body[:16].hex()
            packet["file_hash"] = body[16:32].hex()
            packet["file_hash16k"] = body[32:48].hex()
            packet["file_length"] = int.from_bytes(body[48:56], "little")
            packet["filename"] = body[56:].rstrip(b"\0").decode("ascii", "replace")
        elif packet_type == "IFSC" and len(body) >= 16:
            packet["file_id"] = body[:16].hex()
            checksum_body = body[16:]
            packet["slice_checksums"] = [{"md5": checksum_body[i:i + 16].hex(),
                                          "crc32": f"{int.from_bytes(checksum_body[i + 16:i + 20], 'little'):08x}"}
                                         for i in range(0, len(checksum_body) - 19, 20)]
        elif packet_type == "Creator":
            packet["creator"] = body.rstrip(b"\0").decode("ascii", "replace")
        elif packet_type in ("Main", "FileDesc"):
            packet["packet_structure_valid"] = False
        packets.append(packet)
        offset = marker + length
    if packets:
        valid_packets = [p for p in packets if p["packet_md5_valid"]]
        recovery = [p["recovery_exponent"] for p in valid_packets if "recovery_exponent" in p]
        mains = [p for p in valid_packets if p["type"] == "Main"]
        file_descriptions = {p["file_id"]: p for p in valid_packets if p["type"] == "FileDesc" and p.get("file_length") is not None}
        slice_size = mains[0].get("recovery_block_size") if mains else None
        recoverable_ids = set(mains[0].get("file_ids", [])) if mains else set()
        recoverable_descriptions = [p for p in file_descriptions.values() if not recoverable_ids or p["file_id"] in recoverable_ids]
        estimated_source_blocks = sum((p["file_length"] + slice_size - 1) // slice_size for p in recoverable_descriptions) if slice_size else None
        packet_counts = Counter(p["type"] for p in packets)
        packet_md5s = [p["packet_md5_valid"] for p in packets]
        result["par2"] = {"packets": packets, "set_ids": sorted({p["set_id"] for p in packets}),
                           "packet_count_by_type": dict(packet_counts),
                           "duplicate_packet_count": len(packets) - len({p["packet_md5"] for p in packets}),
                           "packet_md5_valid_count": sum(packet_md5s), "packet_md5_invalid_count": len(packet_md5s) - sum(packet_md5s),
                           "trailing_unparsed_bytes": len(data) - offset,
        "unparsed_bytes": unparsed_bytes,
                           "recoverable_file_count": mains[0].get("recoverable_file_count") if mains else None,
                           "recovery_block_size": slice_size,
                           "source_file_count_observed": len(recoverable_descriptions),
                           "source_block_count_estimate": estimated_source_blocks,
                           "source_files": recoverable_descriptions,
                           "unique_recovery_blocks_in_sample": len(set(recovery)),
                           "recovery_exponents": sorted(set(recovery))}
    return result


def file_kind(subject: str, yenc_name: str | None) -> str:
    name = yenc_name or subject
    if PAR_RE.search(name): return "par2"
    if RAR_RE.search(name): return "rar"
    return "other"


def make_findings(files: list[dict[str, Any]], articles: list[dict[str, Any]]) -> dict[str, Any]:
    article_by_id: dict[str, dict[str, Any]] = {}
    for item in articles:
        existing = article_by_id.get(item["id"])
        if existing is None or (not existing.get("metadata") and item.get("metadata")):
            article_by_id[item["id"]] = item
    groups: dict[str, dict[str, Any]] = defaultdict(lambda: {"nzb_bytes": 0, "observed_yenc_file_size_sum": 0, "yenc_size_file_count": 0, "parts": 0, "file_count": 0})
    software_counts: Counter[str] = Counter()
    per_file = []
    subjects_for_signals: list[str] = []
    for f in files:
        observed = [article_by_id[s["id"]] for s in f["segments"] if s["id"] in article_by_id]
        yencs = [a.get("metadata", {}).get("yenc", {}) for a in observed]
        first = next((y for y in yencs if y.get("begin")), {})
        name = first.get("name") or f["subject"]
        kind = file_kind(f["subject"], first.get("name"))
        subjects_for_signals.append(f["subject"])
        expected_bytes = sum(s["bytes"] or 0 for s in f["segments"])
        g = groups[kind]
        g["nzb_bytes"] += expected_bytes
        g["parts"] += len(f["segments"])
        totals = [y.get("total_size") for y in yencs if y.get("total_size")]
        g["file_count"] += 1
        if totals:
            g["observed_yenc_file_size_sum"] += max(totals)
            g["yenc_size_file_count"] += 1
        ypart_ranges = sorted({(y.get("part_begin"), y.get("part_end")) for y in yencs if y.get("part_begin") is not None and y.get("part_end") is not None})
        par_match = PAR_RE.search(name)
        per_file.append({"kind": kind, "name": name, "par2_volume_first": safe_int(par_match.group("first")) if par_match else None,
                         "par2_volume_count": safe_int(par_match.group("count")) if par_match else None,
                         "nzb_segment_bytes": expected_bytes,
                         "segment_count": len(f["segments"]), "observed_articles": len(observed),
                         "yenc_total_size": max(totals) if totals else None,
                         "yenc_crc32_values": sorted({y.get("crc32") for y in yencs if y.get("crc32")}),
                         "yenc_part_crc32_values": sorted({y.get("pcrc32") for y in yencs if y.get("pcrc32")}),
                         "yenc_observed_ranges": [{"begin": a, "end": b, "length": b - a + 1} for a, b in ypart_ranges],
                         "groups": f["groups"], "nzb_date": f["date"], "nzb_poster": f["poster"], "nzb_meta": f.get("nzb_meta", [])})
        for a in observed:
            for header, value in a.get("metadata", {}).get("headers", {}).items():
                if header in ("user-agent", "x-newsreader", "x-mailer", "x-poster"):
                    software_counts[f"{header}: {value}"] += 1
    rar_size = groups["rar"]["nzb_bytes"] or 0
    par_size = groups["par2"]["nzb_bytes"] or 0
    ratio = (par_size / rar_size * 100) if rar_size and groups["par2"]["file_count"] else None
    score: Counter[str] = Counter()
    evidence: dict[str, list[str]] = defaultdict(list)
    for clue, count in software_counts.items():
        text = clue.lower()
        for product, patterns in {
            "Postie": ("postie",), "ngPost": ("ngpost", "ng post"),
            "Nyuu": ("nyuu",), "yEncBinPoster": ("yencbinposter", "yenc bin poster"),
            "Newsbin": ("newsbin",), "Usenet-Upload": ("usenet-upload", "usenet upload"),
        }.items():
            if any(p in text for p in patterns):
                score[product] += count * 4
                evidence[product].append(f"Observed header value ({count} article(s)): {clue}")
    for item in per_file:
        if item["kind"] == "rar" and re.search(r"\.part0*1\.rar$", item["name"], re.I):
            evidence["Multipart RAR publisher"].append("RAR part numbering observed; this is format evidence, not uploader identification")
    uploader = sorted(score.items(), key=lambda kv: (-kv[1], kv[0]))
    if uploader:
        confidence = "low" if uploader[0][1] < 8 or (len(uploader) > 1 and uploader[0][1] == uploader[1][1]) else "medium"
    else:
        confidence = "none"
    obfuscation_terms = ("obfuscated", "(1/", "[1/", "_broken", "no-nfo", "no nfo")
    obscured_names = sum(1 for subject in subjects_for_signals if not re.search(r"(?i)\.(?:rar|par2)(?:\s|$)", subject))
    recovery_exponents = sorted({p["recovery_exponent"] for a in articles for p in a.get("metadata", {}).get("formats", {}).get("par2", {}).get("packets", []) if "recovery_exponent" in p})
    par_source_blocks = max((a.get("metadata", {}).get("formats", {}).get("par2", {}).get("source_block_count_estimate") or 0 for a in articles), default=0)
    format_signals = {
        "nzb_subjects_without_visible_rar_or_par2_extension": obscured_names,
        "obfuscation_terms_in_subjects": {term: sum(subject.lower().count(term.lower()) for subject in subjects_for_signals) for term in obfuscation_terms if any(term.lower() in s.lower() for s in subjects_for_signals)},
        "detected_rar_signature_samples": sum(1 for a in articles if a.get("metadata", {}).get("formats", {}).get("rar")),
        "detected_par2_packet_samples": sum(1 for a in articles if a.get("metadata", {}).get("formats", {}).get("par2")),
    }
    observed_dates = []
    for article in articles:
        date_value = (article.get("metadata", {}).get("headers", {}).get("injection-date")
                      or article.get("metadata", {}).get("headers", {}).get("nntp-posting-date")
                      or article.get("metadata", {}).get("headers", {}).get("date"))
        parsed = parse_date(date_value)
        if parsed:
            observed_dates.append(parsed)
    observed_dates.sort()
    return {"files": per_file, "totals": groups,
            "nzb_advertised_total_bytes": sum(item["nzb_bytes"] for item in groups.values()),
            "nzb_file_timestamp_span_seconds": nzb_timestamp_span(files),
            "par2_to_rar_percent": round(ratio, 3) if ratio is not None else None,
            "par2_to_rar_percent_basis": "All NZB advertised segment bytes; includes yEnc/article overhead and is not the PAR2 recovery percentage.",
            "par2_recovery": {"source_block_count": par_source_blocks or None,
                              "unique_recovery_blocks_in_samples": len(recovery_exponents),
                              "recovery_exponents": recovery_exponents,
                              "sample_recovery_to_source_percent": round(len(recovery_exponents) / par_source_blocks * 100, 3) if par_source_blocks else None,
                              "warning": "Sample recovery blocks are not the complete redundancy percentage unless all recovery volumes were inspected." if groups["par2"]["file_count"] else None},
            "sampled_article_timeline": {"first_utc": observed_dates[0].isoformat() if observed_dates else None,
                                          "last_utc": observed_dates[-1].isoformat() if observed_dates else None,
                                          "span_seconds": (observed_dates[-1] - observed_dates[0]).total_seconds() if len(observed_dates) > 1 else None,
                                          "timestamp_preference": "Injection-Date, NNTP-Posting-Date, then poster-controlled Date; interval is not upload speed."},
            "observed_software_headers": dict(software_counts),
            "posting_ip_exposure": {"exposed": bool(exposed_posting_ips(articles)), "addresses": exposed_posting_ips(articles)},
            "format_and_obfuscation_signals": format_signals,
            "uploader_candidates": [{"software": name, "score": points, "evidence": evidence[name], "confidence": confidence} for name, points in uploader],
            "fingerprint_limitations": ["NNTP headers can be forged, stripped, or rewritten by a provider.", "RAR/PAR2/yEnc structure identifies formats and posting patterns, not uniquely the uploader.", "A single NZB or sampled article is insufficient for reliable attribution."]}


def nzb_timestamp_span(files: list[dict[str, Any]]) -> float | None:
    dates = []
    for item in files:
        try:
            dates.append(datetime.fromtimestamp(int(item["date"]), timezone.utc))
        except (KeyError, ValueError, TypeError, OSError, OverflowError):
            continue
    return (max(dates) - min(dates)).total_seconds() if len(dates) > 1 else None


def load_servers(args: argparse.Namespace) -> list[dict[str, Any]]:
    if args.config:
        raw = json.loads(args.config.read_text())
        configs = raw if isinstance(raw, list) else raw.get("servers", [raw])
    elif args.host:
        configs = [{"name": args.server_name or args.host, "host": args.host, "port": args.port,
                    "tls": args.tls, "user": args.user, "password": None}]
    else:
        return []
    servers = []
    for index, config in enumerate(configs, 1):
        host = config.get("host")
        if not host:
            continue
        tls = bool(config.get("tls", False))
        user, password = config.get("user"), config.get("password")
        if user and not password:
            password = getpass.getpass(f"NNTP password for {config.get('name', host)}: ")
        servers.append({"name": config.get("name", f"server-{index}"), "host": host,
                        "port": config.get("port") or (563 if tls else 119), "tls": tls,
                        "user": user, "password": password})
    return servers


def choose_samples(files: list[dict[str, Any]], per_file: int, files_per_kind: int,
                   all_files: bool = False, all_segments: bool = False) -> list[tuple[str, str, int, int | None]]:
    selected: list[tuple[str, str, int, int | None]] = []
    seen: set[str] = set()
    candidates: dict[str, list[int]] = defaultdict(list)
    for file_index, item in enumerate(files):
        candidates[file_kind(item["subject"], None)].append(file_index)
    selected_file_indexes: set[int] = set(candidates["par2"])
    for kind in ("rar", "other"):
        indexes = candidates[kind]
        if all_files or len(indexes) <= files_per_kind:
            selected_file_indexes.update(indexes)
        elif files_per_kind == 1:
            selected_file_indexes.add(indexes[len(indexes) // 2])
        else:
            selected_file_indexes.update(indexes[round(i * (len(indexes) - 1) / (files_per_kind - 1))] for i in range(files_per_kind))
    for file_index, item in enumerate(files):
        if file_index not in selected_file_indexes:
            continue
        segments = sorted(item["segments"], key=lambda segment: segment["number"] or 0)
        if not segments:
            continue
        if file_kind(item["subject"], None) == "par2":
            for segment in segments:
                message_id = segment["id"]
                if message_id not in seen:
                    selected.append((message_id, item["subject"], file_index, segment["number"]))
                    seen.add(message_id)
            continue
        if all_segments:
            indexes = set(range(len(segments)))
        elif per_file == 1:
            indexes = {len(segments) // 2}
        elif per_file == 2:
            indexes = {0, len(segments) - 1}
        elif per_file == 3:
            indexes = {0, len(segments) // 2, len(segments) - 1}
        else:
            indexes = {round(i * (len(segments) - 1) / (per_file - 1)) for i in range(per_file)}
        for index in sorted(indexes):
            message_id = segments[index]["id"]
            if message_id not in seen:
                selected.append((message_id, item["subject"], file_index, segments[index]["number"]))
                seen.add(message_id)
    return selected


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("nzb", nargs="+", type=Path, help="Local NZB file(s)")
    parser.add_argument("--config", type=Path, help="Private JSON config with a servers array of name, host, port, tls, user, password")
    parser.add_argument("--host", help="Explicit NNTP host (never scans other hosts)")
    parser.add_argument("--server-name", help="Display name for --host")
    parser.add_argument("--port", type=int)
    parser.add_argument("--tls", action="store_true")
    parser.add_argument("--user")
    parser.add_argument("--samples-per-file", type=int, default=3, help="Sample first/middle/last IDs per non-PAR2 file (or more evenly spaced with a larger value); retrieve every PAR2 segment")
    parser.add_argument("--files-per-kind", type=int, default=12, help="Evenly sample this many RAR/other NZB files; all PAR2 files are included")
    parser.add_argument("--all-files", action="store_true", help="Inspect article metadata for every NZB file (still samples segment positions unless --all-segments is set)")
    parser.add_argument("--all-segments", action="store_true", help="Retrieve every segment of every selected file; can make many NNTP requests")
    parser.add_argument("--max-article-bytes", type=int, default=2_000_000, help="Maximum body bytes retained for decoding per sample; the NNTP response is drained to its terminator")
    parser.add_argument("--timeout", type=float, default=12.0)
    parser.add_argument("--output", type=Path, help="Write JSON to this path; otherwise stdout")
    args = parser.parse_args()
    all_files: list[dict[str, Any]] = []
    errors: list[str] = []
    for path in args.nzb:
        try:
            all_files.extend(parse_nzb(path))
        except (OSError, ET.ParseError, ValueError) as exc:
            errors.append(f"{path.name}: NZB parse failed ({type(exc).__name__})")
    samples: list[dict[str, Any]] = []
    server_summaries: list[dict[str, Any]] = []
    try:
        server_configs = load_servers(args)
        selected = choose_samples(all_files, max(1, args.samples_per_file), max(1, args.files_per_kind),
                                  all_files=args.all_files, all_segments=args.all_segments)
        for config in server_configs:
            server = None
            server_samples = []
            try:
                server = Nntp(config["host"], config["port"], config["user"], config["password"], config["tls"], args.timeout)
                greeting = getattr(server, "greeting", None)
                server_capabilities = server.capabilities()
                overview_fields = server.overview_format()
                for message_id, _subject, file_index, segment_number in selected:
                    try:
                        stat_result = server.status("STAT", message_id)
                        head_result = server.status("HEAD", message_id)
                        overview_rows = []
                        supports_message_id_overview = any(re.match(r"OVER\s+MSGID\b", value, re.IGNORECASE) for value in server_capabilities)
                        for group_name in all_files[file_index]["groups"]:
                            if not re.fullmatch(r"[A-Za-z0-9+._-]+", group_name):
                                overview_rows.append({"group": group_name, "available": False, "error": "invalid group name"})
                                continue
                            if supports_message_id_overview:
                                overview_rows.append(server.overview_message_id(group_name, message_id, overview_fields))
                            else:
                                server.file.write(f"GROUP {group_name}\r\n".encode("ascii"))
                                group_code, _ = server._response()
                                group_number = None
                                if group_code == 211:
                                    group_head = server.status("HEAD", message_id)
                                    group_match = re.match(r"(\d+)", group_head.get("message", ""))
                                    group_number = int(group_match.group(1)) if group_match else None
                                overview_rows.append(server.overview(group_name, group_number, overview_fields) if group_number else
                                                     {"group": group_name, "available": False, "error": "article number unavailable in selected group"})
                        headers, body = server.article(message_id, max(1024, args.max_article_bytes))
                        sample = {"id": message_id, "message_id_profile": message_id_profile(message_id),
                                  "file_index": file_index, "nzb_subject": _subject, "segment_number": segment_number,
                                  "server": config["name"], "available": bool(headers or body),
                                  "stat": stat_result, "head": head_result, "overview": overview_rows}
                        if headers or body:
                            metadata = parse_article(headers, body)
                            sample["_decoded_bytes"] = metadata.pop("_decoded_bytes")
                            sample["metadata"] = metadata
                        server_samples.append(sample)
                    except Exception as exc:
                        errors.append(f"NNTP sample failed on {config['name']} ({type(exc).__name__})")
                server_summaries.append({"name": config["name"], "host": config["host"], "port": config["port"], "tls": config["tls"], "greeting": server.greeting, "sampled_articles": len(server_samples), "capabilities": server_capabilities, "overview_format": overview_fields})
                samples.extend(server_samples)
            except (OSError, RuntimeError) as exc:
                errors.append(f"NNTP connection failed on {config['name']} ({type(exc).__name__})")
            finally:
                if server:
                    server.close()
    except (OSError, ValueError, RuntimeError, ET.ParseError, KeyError, TypeError) as exc:
        errors.append(f"NNTP configuration failed ({type(exc).__name__})")
    by_id: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for sample in samples:
        by_id[sample["id"]].append(sample)
    comparisons = []
    for message_id, observations in by_id.items():
        if len(observations) < 2:
            continue
        body_fingerprints = {item.get("metadata", {}).get("article_body_sha256") for item in observations}
        payload_fingerprints = {item.get("metadata", {}).get("yenc", {}).get("encoded_payload_sha256") for item in observations}
        decoded_fingerprints = {item.get("metadata", {}).get("yenc", {}).get("decoded_sha256") for item in observations}
        header_fingerprints = {item.get("metadata", {}).get("header_profile", {}).get("ordered_names_sha256") for item in observations}
        headers_by_server = {item["server"]: item.get("metadata", {}).get("headers", {}) for item in observations}
        common_header_names = set.intersection(*(set(values) for values in headers_by_server.values())) if headers_by_server else set()
        differing_headers = {name: {server_name: values.get(name) for server_name, values in headers_by_server.items()}
                             for name in common_header_names if len({values.get(name) for values in headers_by_server.values()}) > 1}
        comparisons.append({"message_id": message_id, "availability_by_server": {item["server"]: item["available"] for item in observations},
                            "yenc_metadata_matches": len(payload_fingerprints) == 1 and None not in payload_fingerprints,
                            "encoded_article_body_matches": len(body_fingerprints) == 1 and None not in body_fingerprints,
                            "encoded_payload_matches": len(payload_fingerprints) == 1 and None not in payload_fingerprints,
                            "decoded_segment_matches": len(decoded_fingerprints) == 1 and None not in decoded_fingerprints,
                            "decoded_body_hashes_by_server": {item["server"]: item.get("metadata", {}).get("yenc", {}).get("decoded_sha256") for item in observations},
                            "header_order_matches": len(header_fingerprints) == 1 and None not in header_fingerprints,
                            "common_header_values_match": not differing_headers,
                            "different_header_values_by_server": differing_headers,
                            "observed_header_names_by_server": {item["server"]: item.get("metadata", {}).get("header_profile", {}).get("ordered_names", []) for item in observations}})
    complete_par2 = []
    for file_index, nzb_file in enumerate(all_files):
        if file_kind(nzb_file["subject"], None) != "par2":
            continue
        primary = [sample for sample in samples if sample["file_index"] == file_index and sample.get("_decoded_bytes") is not None]
        if not primary:
            complete_par2.append({"file_index": file_index, "name": nzb_file["subject"], "server": None,
                                  "all_segments_retrieved": False, "decoded_bytes": 0, "metadata": {}})
            continue
        chosen_server = primary[0]["server"]
        chunks = {sample["id"]: sample["_decoded_bytes"] for sample in primary if sample["server"] == chosen_server}
        chosen_samples = [sample for sample in primary if sample["server"] == chosen_server]
        segments = sorted(nzb_file["segments"], key=lambda segment: segment["number"] or 0)
        is_complete = [segment["number"] for segment in segments] == list(range(1, len(segments) + 1)) and all(segment["id"] in chunks for segment in segments)
        assembled = b"".join(chunks[segment["id"]] for segment in segments if segment["id"] in chunks)
        yencs = [sample.get("metadata", {}).get("yenc", {}) for sample in chosen_samples]
        total_sizes = {yenc.get("total_size") for yenc in yencs}
        names = {yenc.get("name") for yenc in yencs}
        multipart = any(yenc.get("ypart") for yenc in yencs)
        yenc_structure_valid = is_complete and len(chosen_samples) == len(segments) and len(total_sizes) == 1 and None not in total_sizes and len(names) == 1 and None not in names
        yenc_structure_valid = yenc_structure_valid and all(
            yenc.get("header_size_matches") is True
            and yenc.get("decoded_size_matches_yend") is True
            and (yenc.get("decoded_size_matches_ypart_range") is True if multipart else True)
            and (yenc.get("end_part") == yenc.get("part") if multipart else yenc.get("part") is None)
            and (yenc.get("total_parts") in (None, len(segments)))
            and (yenc.get("crc32_matches") is True if multipart else yenc.get("crc32_matches") is not False)
            for yenc in yencs
        )
        if multipart and any(sample.get("segment_number") != yenc.get("part") for sample, yenc in zip(chosen_samples, yencs)):
            yenc_structure_valid = False
        if yenc_structure_valid and multipart:
            ranges = sorted((yenc.get("part_begin"), yenc.get("part_end")) for yenc in yencs)
            next_byte = 1
            for begin, end in ranges:
                if begin != next_byte or end is None or end < begin:
                    yenc_structure_valid = False
                    break
                next_byte = end + 1
            yenc_structure_valid = yenc_structure_valid and next_byte - 1 == next(iter(total_sizes))
            whole_file_crcs = {yenc.get("crc32", "").lower().zfill(8) for yenc in yencs if yenc.get("crc32")}
            if whole_file_crcs:
                assembled_crc = f"{binascii.crc32(assembled) & 0xffffffff:08x}"
                yenc_structure_valid = yenc_structure_valid and len(whole_file_crcs) == 1 and assembled_crc in whole_file_crcs
        elif yenc_structure_valid:
            yenc_structure_valid = len(segments) == 1 and len(assembled) == next(iter(total_sizes))
        complete_par2.append({"file_index": file_index, "name": nzb_file["subject"], "server": chosen_server,
                              "all_segments_retrieved": is_complete, "yenc_structure_valid": yenc_structure_valid, "decoded_bytes": len(assembled),
                              "metadata": inspect_binary_metadata(assembled)})
    findings = make_findings(all_files, samples)
    par2_packet_sets = [item["metadata"].get("par2", {}) for item in complete_par2]
    complete_packet_sets = [packet_set for packet_set in par2_packet_sets if packet_set]
    set_packets: dict[str, list[dict[str, Any]]] = defaultdict(list)
    par2_packets_valid = True
    for packet_set in complete_packet_sets:
        for packet in packet_set.get("packets", []):
            if packet.get("packet_md5_valid"):
                set_packets[packet["set_id"]].append(packet)
            else:
                par2_packets_valid = False
    set_recovery: dict[str, set[int]] = defaultdict(set)
    set_sources: dict[str, dict[str, dict[str, Any]]] = defaultdict(dict)
    set_mains: dict[str, dict[str, Any]] = {}
    set_block_sizes: dict[str, int] = {}
    packed_recovery_layout = False
    for set_id, packets in set_packets.items():
        for packet in packets:
            if not packet.get("packet_structure_valid", False):
                par2_packets_valid = False
            if packet["type"] == "Main":
                main_definition = (packet.get("recovery_block_size"), packet.get("recoverable_file_count"), tuple(packet.get("all_file_ids", [])))
                previous_main = set_mains.get(set_id)
                if previous_main is not None and (previous_main.get("recovery_block_size"), previous_main.get("recoverable_file_count"), tuple(previous_main.get("all_file_ids", []))) != main_definition:
                    par2_packets_valid = False
                set_mains[set_id] = packet
                if packet.get("recovery_block_size"):
                    set_block_sizes[set_id] = packet["recovery_block_size"]
            elif packet["type"] == "FileDesc" and packet.get("file_id"):
                previous = set_sources[set_id].get(packet["file_id"])
                if previous is not None and (previous.get("file_hash"), previous.get("file_hash16k"), previous.get("file_length"), previous.get("filename")) != (packet.get("file_hash"), packet.get("file_hash16k"), packet.get("file_length"), packet.get("filename")):
                    par2_packets_valid = False
                set_sources[set_id][packet["file_id"]] = packet
            elif packet["type"] in ("PkdMain", "PkdRecvS"):
                packed_recovery_layout = True
            elif packet["type"] == "RecvSlic" and "recovery_exponent" in packet:
                set_recovery[set_id].add(packet["recovery_exponent"])
    source_block_count = 0
    for set_id, main in set_mains.items():
        recoverable_ids = main.get("recoverable_file_ids", [])
        all_file_ids = main.get("all_file_ids", [])
        if main.get("recoverable_file_count") != len(main.get("recoverable_file_ids", [])) or sorted(all_file_ids) != sorted(set_sources[set_id]):
            par2_packets_valid = False
        block_size = set_block_sizes.get(set_id, 0)
        if block_size:
            for file_id in recoverable_ids:
                description = set_sources[set_id].get(file_id)
                if description:
                    size = description["file_length"]
                    source_block_count += (size + block_size - 1) // block_size
        for packet in set_packets[set_id]:
            if packet["type"] == "RecvSlic" and packet.get("payload_bytes") != block_size + 4:
                par2_packets_valid = False
    set_ids = sorted(set_packets)
    recovery_exponents = sorted(set().union(*(set_recovery[set_id] for set_id in set_ids))) if set_ids else []
    par2_file_indexes = {index for index, item in enumerate(all_files) if file_kind(item["subject"], None) == "par2"}
    complete_par2_inspection = bool(par2_file_indexes) and len(complete_par2) == len(par2_file_indexes) and all(item["all_segments_retrieved"] and item.get("yenc_structure_valid") is True for item in complete_par2) and par2_packets_valid and bool(set_ids) and all(set_id in set_mains for set_id in set_ids)
    if complete_par2_inspection:
        for item in complete_par2:
            par2 = item["metadata"].get("par2", {})
            if not par2.get("packets") or par2.get("packet_md5_invalid_count", 0) or par2.get("trailing_unparsed_bytes", 0) or par2.get("unparsed_bytes", 0) or par2.get("packet_count_by_type", {}).get("Creator", 0) == 0 or any(not packet.get("packet_structure_valid", False) for packet in par2.get("packets", [])):
                complete_par2_inspection = False
                break
    if len(set_ids) > 1:
        source_block_count = 0
    recovery_count = len(recovery_exponents) if len(set_ids) == 1 else None
    findings["par2_recovery"] = {"source_block_count": source_block_count or None,
                                  "unique_recovery_blocks": recovery_count,
                                  "recovery_exponents": recovery_exponents,
                                  "recovery_to_source_percent": round(recovery_count / source_block_count * 100, 3) if recovery_count is not None and source_block_count and complete_par2_inspection and not packed_recovery_layout else None,
                                  "set_ids": set_ids,
                                  "packed_recovery_layout_present": packed_recovery_layout,
                                  "complete_par2_inspection": complete_par2_inspection,
                                  "status": "not_present" if not par2_file_indexes else "complete" if complete_par2_inspection else "partial",
                                  "warning": None if not par2_file_indexes or complete_par2_inspection else "Partial packet sample; the observed recovery-block count is a lower bound, not the complete redundancy percentage."}
    described_rar_volumes = {}
    for packet_set in complete_packet_sets:
        for item in packet_set.get("source_files", []):
            if re.search(r"(?i)\.part\d+\.rar$|\.rar$", item.get("filename", "")):
                described_rar_volumes[item["filename"]] = item["file_length"]
    findings["par2_described_rar_files"] = {"count": len(described_rar_volumes),
                                             "total_bytes": sum(described_rar_volumes.values()),
                                             "files": described_rar_volumes,
                                             "complete_par2_packets": complete_par2_inspection}
    coverage: list[dict[str, Any]] = []
    for file_index, item in enumerate(all_files):
        inspected_numbers = sorted({sample["segment_number"] for sample in samples
                                    if sample["file_index"] == file_index and sample["segment_number"] is not None})
        observed_ids = {sample["id"] for sample in samples if sample["file_index"] == file_index}
        total_segments = len(item["segments"])
        coverage.append({"file_index": file_index, "subject": item["subject"],
                         "kind": file_kind(item["subject"], None),
                         "nzb_advertised_bytes": sum(segment["bytes"] for segment in item["segments"]),
                         "segment_count": total_segments,
                         "unique_articles_observed": len(observed_ids),
                         "article_observations_across_servers": sum(1 for sample in samples if sample["file_index"] == file_index),
                         "inspected_segment_count": len(inspected_numbers),
                         "inspected_segment_numbers": inspected_numbers,
                         "article_metadata_coverage_percent": round(len(inspected_numbers) / total_segments * 100, 3) if total_segments else 0.0})
    result = {"generated_at": datetime.now(timezone.utc).isoformat(), "source_nzbs": [p.name for p in args.nzb],
              "servers": server_summaries, "nzb_file_count": len(all_files), "sampled_article_count": len(samples),
              "nzb_files": all_files, "file_coverage": coverage,
              "nzb_optional_meta_elements": all_files[0].get("nzb_meta", []) if all_files else [],
              "server_comparisons": comparisons,
              "assembled_par2_files": complete_par2,
              "article_observations": [{key: value for key, value in sample.items() if key != "_decoded_bytes"} for sample in samples],
              "findings": findings,
              "not_collected_without_explicit_local_inputs": ["NFO/TXT/README and SFV/MD5/SHA sidecars", "SRR release records", "NZBGet history/logs/temp fragments", "Indexer records", "RAR encrypted-header internals and payload contents"],
              "errors": errors}
    rendered = json.dumps(result, indent=2, sort_keys=True)
    if args.output:
        args.output.write_text(rendered + "\n")
    else:
        print(rendered)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
