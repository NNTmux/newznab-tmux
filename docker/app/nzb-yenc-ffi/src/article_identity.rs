use serde::Serialize;

const MAGIC: &[u8; 4] = b"AI01";
const MAX_REQUEST_BYTES: usize = 8_192;
const MAX_HEADER_BYTES: usize = 65_536;
const MAX_OVERVIEW_BYTES: usize = 32_768;
const MAX_OCCURRENCES: usize = 4_096;

#[derive(Debug, Serialize)]
pub struct ArticleIdentity {
    pub parser_engine: &'static str,
    pub schema_version: u8,
    pub comparison_basis: &'static str,
    pub request: IdentityValue,
    pub head_message_id: IdentitySource,
    pub overview_message_id: IdentitySource,
    pub overview_attempted: bool,
    pub overview_row_structure_status: &'static str,
    pub overview_article_number: Option<String>,
    pub relations: IdentityRelations,
    pub overview_response_code: Option<i32>,
    pub interpretation: &'static str,
}

#[derive(Debug, Serialize)]
pub struct IdentityValue {
    pub raw_hex: String,
    pub token: Option<String>,
    pub syntax_status: &'static str,
}

#[derive(Debug, Serialize)]
pub struct IdentitySource {
    pub availability: &'static str,
    pub parse_status: &'static str,
    pub occurrence_count: usize,
    pub candidates: Vec<IdentityOccurrence>,
    pub matches_request: Option<bool>,
}

#[derive(Debug, Serialize)]
pub struct IdentityOccurrence {
    pub source_line_indices: Vec<usize>,
    pub source_overview_row_index: Option<usize>,
    pub source_overview_column_index: Option<usize>,
    pub unfolded_value_hex: String,
    pub token: Option<String>,
    pub token_hex: Option<String>,
    pub syntax_status: &'static str,
}

#[derive(Debug, Serialize)]
pub struct IdentityRelations {
    pub head_matches_overview: Option<bool>,
}

struct PackedFields<'a> {
    request: &'a [u8],
    headers: &'a [u8],
    overview: Option<&'a [u8]>,
}

pub fn parse(
    packed: &[u8],
    overview_response_code: i32,
    overview_attempted: bool,
) -> ArticleIdentity {
    let Some(fields) = unpack(packed) else {
        return invalid_input(overview_response_code, overview_attempted);
    };

    let request_status = if fields.request.len() > MAX_REQUEST_BYTES {
        "budget_exceeded"
    } else {
        message_id_status(fields.request)
    };
    let request = IdentityValue {
        raw_hex: hex(fields.request),
        token: (request_status == "valid")
            .then(|| String::from_utf8(fields.request.to_vec()).ok())
            .flatten(),
        syntax_status: request_status,
    };

    let head_candidates = parse_header_message_ids(fields.headers);
    let head_status =
        if fields.headers.len() > MAX_HEADER_BYTES || head_candidates.len() == MAX_OCCURRENCES {
            "budget_exceeded"
        } else {
            source_parse_status(&head_candidates)
        };
    let head_available = fields.headers.len() <= MAX_HEADER_BYTES;
    let head_matches_request = unique_valid_token(&head_candidates)
        .zip((request_status == "valid").then_some(fields.request))
        .map(|(candidate, request)| candidate == hex(request));
    let head_message_id = IdentitySource {
        availability: if head_available {
            "available"
        } else {
            "budget_exceeded"
        },
        parse_status: head_status,
        occurrence_count: head_candidates.len(),
        candidates: head_candidates,
        matches_request: head_matches_request,
    };

    let overview_availability =
        overview_availability(fields.overview, overview_response_code, overview_attempted);
    let (overview_candidates, overview_row_structure_status, overview_article_number) =
        if overview_availability == "available" {
            parse_overview_message_id(fields.overview.expect("available OVER row"))
        } else {
            (Vec::new(), overview_availability, None)
        };
    let overview_status =
        if overview_availability == "available" && overview_row_structure_status != "valid" {
            overview_row_structure_status
        } else if overview_availability == "available" {
            source_parse_status(&overview_candidates)
        } else {
            overview_availability
        };
    let overview_matches_request = (overview_row_structure_status == "valid")
        .then(|| unique_valid_token(&overview_candidates))
        .flatten()
        .zip((request_status == "valid").then_some(fields.request))
        .map(|(candidate, request)| candidate == hex(request));
    let overview_message_id = IdentitySource {
        availability: overview_availability,
        parse_status: overview_status,
        occurrence_count: overview_candidates.len(),
        candidates: overview_candidates,
        matches_request: overview_matches_request,
    };

    let head_overview_match = (overview_row_structure_status == "valid")
        .then(|| unique_valid_token(&head_message_id.candidates))
        .flatten()
        .zip(unique_valid_token(&overview_message_id.candidates))
        .map(|(head, overview)| head == overview);

    ArticleIdentity {
        parser_engine: "nntmux-rust-article-identity",
        schema_version: 1,
        comparison_basis: "exact_message_id_octets_case_sensitive",
        request,
        head_message_id,
        overview_message_id,
        overview_attempted,
        overview_row_structure_status,
        overview_article_number,
        relations: IdentityRelations {
            head_matches_overview: head_overview_match,
        },
        overview_response_code: (overview_response_code >= 0).then_some(overview_response_code),
        interpretation: "mismatches are descriptive only; RFC 3977 permits the lookup Message-ID to differ from the article Message-ID header",
    }
}

fn unpack(data: &[u8]) -> Option<PackedFields<'_>> {
    if data.len() > 131_072 || data.get(..4)? != MAGIC {
        return None;
    }
    let mut cursor = 4usize;
    let request = next_field(data, &mut cursor)?;
    let headers = next_field(data, &mut cursor)?;
    let overview = next_field(data, &mut cursor)?;
    if cursor != data.len() || request.is_none() || headers.is_none() {
        return None;
    }
    let request = request?;
    let headers = headers?;
    Some(PackedFields {
        request,
        headers,
        overview,
    })
}

fn next_field<'a>(data: &'a [u8], cursor: &mut usize) -> Option<Option<&'a [u8]>> {
    let length_bytes = data.get(*cursor..cursor.checked_add(4)?)?;
    *cursor += 4;
    let length = u32::from_le_bytes(length_bytes.try_into().ok()?);
    if length == u32::MAX {
        return Some(None);
    }
    let length = usize::try_from(length).ok()?;
    let end = cursor.checked_add(length)?;
    let value = data.get(*cursor..end)?;
    *cursor = end;
    Some(Some(value))
}

fn invalid_input(overview_response_code: i32, overview_attempted: bool) -> ArticleIdentity {
    ArticleIdentity {
        parser_engine: "nntmux-rust-article-identity",
        schema_version: 1,
        comparison_basis: "exact_message_id_octets_case_sensitive",
        request: IdentityValue {
            raw_hex: String::new(),
            token: None,
            syntax_status: "invalid_input",
        },
        head_message_id: empty_source("invalid_input", "unavailable"),
        overview_message_id: empty_source("invalid_input", "unavailable"),
        overview_attempted,
        overview_row_structure_status: "invalid_input",
        overview_article_number: None,
        relations: IdentityRelations {
            head_matches_overview: None,
        },
        overview_response_code: (overview_response_code >= 0).then_some(overview_response_code),
        interpretation: "mismatches are descriptive only; RFC 3977 permits the lookup Message-ID to differ from the article Message-ID header",
    }
}

fn empty_source(parse_status: &'static str, availability: &'static str) -> IdentitySource {
    IdentitySource {
        availability,
        parse_status,
        occurrence_count: 0,
        candidates: Vec::new(),
        matches_request: None,
    }
}

fn parse_header_message_ids(headers: &[u8]) -> Vec<IdentityOccurrence> {
    if headers.len() > MAX_HEADER_BYTES {
        return Vec::new();
    }
    let mut candidates = Vec::new();
    let mut pending_name = Vec::new();
    let mut pending_value = Vec::new();
    let mut pending_lines = Vec::new();
    let mut line_number = 0usize;
    let mut have_field = false;

    for raw_line in headers.split(|byte| *byte == b'\n') {
        line_number += 1;
        let line = raw_line.strip_suffix(b"\r").unwrap_or(raw_line);
        if line.is_empty() {
            break;
        }
        if matches!(line.first(), Some(b' ' | b'\t')) {
            if have_field {
                pending_lines.push(line_number);
                pending_value.extend_from_slice(line);
            } else {
                flush_header_candidate(
                    &pending_name,
                    &pending_value,
                    &pending_lines,
                    &mut candidates,
                );
                pending_name.clear();
                pending_value.clear();
                pending_lines.clear();
            }
            continue;
        }

        if have_field {
            flush_header_candidate(
                &pending_name,
                &pending_value,
                &pending_lines,
                &mut candidates,
            );
            pending_name.clear();
            pending_value.clear();
            pending_lines.clear();
        }
        have_field = false;
        let Some(colon) = line.iter().position(|byte| *byte == b':') else {
            continue;
        };
        pending_name.extend_from_slice(&line[..colon]);
        pending_value.extend_from_slice(&line[colon + 1..]);
        pending_lines.push(line_number);
        have_field = true;
        if candidates.len() >= MAX_OCCURRENCES {
            break;
        }
    }
    if have_field && candidates.len() < MAX_OCCURRENCES {
        flush_header_candidate(
            &pending_name,
            &pending_value,
            &pending_lines,
            &mut candidates,
        );
    }

    candidates
}

fn flush_header_candidate(
    name: &[u8],
    value: &[u8],
    lines: &[usize],
    candidates: &mut Vec<IdentityOccurrence>,
) {
    if !name.eq_ignore_ascii_case(b"Message-ID") || candidates.len() >= MAX_OCCURRENCES {
        return;
    }
    candidates.push(make_occurrence(lines.to_vec(), None, None, value, true));
}

fn parse_overview_message_id(
    row: &[u8],
) -> (Vec<IdentityOccurrence>, &'static str, Option<String>) {
    let row = row.strip_suffix(b"\r\n").unwrap_or(row);
    let row = row.strip_suffix(b"\n").unwrap_or(row);
    if row.iter().any(|byte| matches!(byte, b'\r' | b'\n' | 0)) {
        return (Vec::new(), "malformed_row", None);
    }
    let fields = row.split(|byte| *byte == b'\t').collect::<Vec<_>>();
    let Some(article_number) = fields.first() else {
        return (Vec::new(), "malformed_row", None);
    };
    if article_number.is_empty() || !article_number.iter().all(u8::is_ascii_digit) {
        return (Vec::new(), "malformed_row", None);
    }
    let article_number = String::from_utf8_lossy(article_number).into_owned();
    if fields.len() <= 4 {
        return (
            Vec::new(),
            "missing_message_id_column",
            Some(article_number),
        );
    }
    // OVER preserves the field value's outer whitespace as spaces, including
    // whitespace originally present after the Message-ID header colon.
    (
        vec![make_occurrence(
            Vec::new(),
            Some(0),
            Some(4),
            fields[4],
            true,
        )],
        "valid",
        Some(article_number),
    )
}

fn make_occurrence(
    source_line_indices: Vec<usize>,
    source_overview_row_index: Option<usize>,
    source_overview_column_index: Option<usize>,
    raw_value: &[u8],
    trim_space: bool,
) -> IdentityOccurrence {
    let token = if trim_space {
        trim_ascii_space(raw_value)
    } else {
        raw_value
    };
    let status = message_id_status(token);
    IdentityOccurrence {
        source_line_indices,
        source_overview_row_index,
        source_overview_column_index,
        unfolded_value_hex: hex(raw_value),
        token: (status == "valid")
            .then(|| String::from_utf8(token.to_vec()).ok())
            .flatten(),
        token_hex: (status == "valid").then(|| hex(token)),
        syntax_status: status,
    }
}

fn overview_availability(row: Option<&[u8]>, response_code: i32, attempted: bool) -> &'static str {
    if row.is_some_and(|value| value.len() > MAX_OVERVIEW_BYTES) {
        return "budget_exceeded";
    }
    if !attempted {
        return "not_attempted";
    }
    if response_code == 224 {
        return if row.is_some() {
            "available"
        } else {
            "empty_response"
        };
    }
    if response_code < 0 {
        return "failed";
    }
    if matches!(response_code, 500 | 501 | 503) {
        return "unsupported";
    }
    "unavailable"
}

fn source_parse_status(candidates: &[IdentityOccurrence]) -> &'static str {
    match candidates.len() {
        0 => "missing",
        1 if candidates[0].syntax_status == "valid" => "valid",
        1 => "malformed",
        _ => "duplicate",
    }
}

fn unique_valid_token(candidates: &[IdentityOccurrence]) -> Option<&str> {
    (candidates.len() == 1 && candidates[0].syntax_status == "valid")
        .then(|| candidates[0].token_hex.as_deref())
        .flatten()
}

fn message_id_status(value: &[u8]) -> &'static str {
    if value.len() < 3
        || value.len() > 250
        || value.first() != Some(&b'<')
        || value.last() != Some(&b'>')
    {
        return "malformed";
    }
    let core = &value[1..value.len() - 1];
    if !core.is_ascii() || core.iter().any(|byte| !(33..=126).contains(byte)) {
        return "malformed";
    }
    let Some(separator) = core.iter().position(|byte| *byte == b'@') else {
        return "malformed";
    };
    if !is_dot_atom(&core[..separator]) || !is_id_right(&core[separator + 1..]) {
        return "malformed";
    }
    "valid"
}

fn is_dot_atom(value: &[u8]) -> bool {
    if value.is_empty() {
        return false;
    }
    let mut segment_len = 0usize;
    for byte in value {
        if *byte == b'.' {
            if segment_len == 0 {
                return false;
            }
            segment_len = 0;
        } else if is_atext(*byte) {
            segment_len += 1;
        } else {
            return false;
        }
    }
    segment_len > 0
}

fn is_atext(byte: u8) -> bool {
    byte.is_ascii_alphanumeric() || b"!#$%&'*+-/=?^_`{|}~".contains(&byte)
}

fn is_id_right(value: &[u8]) -> bool {
    if value.first() == Some(&b'[') && value.last() == Some(&b']') {
        return value[1..value.len() - 1].iter().all(|byte| {
            (33..=61).contains(byte) || (63..=90).contains(byte) || (94..=126).contains(byte)
        });
    }
    is_dot_atom(value)
}

fn trim_ascii_space(mut value: &[u8]) -> &[u8] {
    while matches!(value.first(), Some(b' ' | b'\t')) {
        value = &value[1..];
    }
    while matches!(value.last(), Some(b' ' | b'\t')) {
        value = &value[..value.len() - 1];
    }
    value
}

fn hex(bytes: &[u8]) -> String {
    const DIGITS: &[u8; 16] = b"0123456789abcdef";
    let mut out = String::with_capacity(bytes.len().saturating_mul(2));
    for byte in bytes {
        out.push(DIGITS[(byte >> 4) as usize] as char);
        out.push(DIGITS[(byte & 0x0f) as usize] as char);
    }
    out
}
