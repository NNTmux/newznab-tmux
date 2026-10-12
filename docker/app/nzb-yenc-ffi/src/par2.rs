use md5::{Digest, Md5};
use serde::Serialize;
use std::collections::{BTreeMap, BTreeSet};

const MAGIC: &[u8; 8] = b"PAR2\0PKT";
const MAX_PACKETS: usize = 20_000;
const MAX_OPTIONAL_HEX_BYTES: usize = 256;
const MAX_IFSC_PREVIEW_BYTES: usize = 4096;

#[derive(Debug, Serialize)]
pub struct Par2Metadata {
    pub parser_engine: &'static str,
    pub creator: Option<String>,
    pub packet_count: usize,
    pub packet_limit_reached: bool,
    pub trailing_unparsed_bytes: usize,
    pub unparsed_bytes: usize,
    pub invalid_packet_count: usize,
    pub source_block_size: Option<u64>,
    pub source_block_count: Option<u64>,
    pub main_packet: Option<MainPacket>,
    pub source_inventory_complete: bool,
    pub missing_file_description_ids: Vec<String>,
    pub unexpected_file_description_ids: Vec<String>,
    pub source_file_count: usize,
    pub source_file_bytes: u64,
    pub source_files: Vec<SourceFile>,
    pub input_file_slice_checksums: Vec<InputFileSliceChecksums>,
    pub recovery_set_ids: Vec<String>,
    pub recovery_sets: Vec<RecoverySetInventory>,
    pub unique_recovery_slice_count: usize,
    pub recovery_exponents: Vec<u32>,
    pub recovery_to_source_percent: Option<f64>,
    pub packed_layout_present: bool,
    pub recovery_percentage_basis: &'static str,
    pub optional_packet_summaries: BTreeMap<String, Vec<OptionalPacketSummary>>,
    pub packet_summaries: Vec<PacketSummary>,
    pub packet_type_counts: BTreeMap<String, usize>,
}

#[derive(Clone, Debug, Serialize)]
pub struct MainPacket {
    pub recoverable_file_count: u32,
    pub recoverable_file_ids: Vec<String>,
    pub non_recoverable_file_ids: Vec<String>,
    pub all_file_ids: Vec<String>,
}

#[derive(Clone, Debug, Serialize)]
pub struct SourceFile {
    pub recovery_set_id: String,
    pub filename: String,
    pub file_length: u64,
    pub md5: String,
    pub md5_first_16k: String,
    pub file_id: String,
}

#[derive(Clone, Debug, Serialize)]
pub struct InputFileSliceChecksums {
    pub recovery_set_id: String,
    pub file_id: String,
    pub slice_count: usize,
    pub checksum_records_sha256: String,
    pub checksum_records_preview_hex: String,
    pub preview_slice_count: usize,
    pub preview_truncated: bool,
    pub source_file_present: bool,
    pub expected_slice_count_from_file_length: Option<u64>,
    pub slice_count_matches_file_length: Option<bool>,
    pub duplicate_conflict: bool,
}

#[derive(Debug, Serialize)]
pub struct RecoverySetInventory {
    pub recovery_set_id: String,
    pub source_block_size: Option<u64>,
    pub main_packet: Option<MainPacket>,
    pub source_inventory_complete: bool,
    pub missing_file_description_ids: Vec<String>,
    pub unexpected_file_description_ids: Vec<String>,
    pub source_file_count: usize,
    pub source_file_bytes: u64,
    pub source_files: Vec<SourceFile>,
    pub input_file_slice_checksums: Vec<InputFileSliceChecksums>,
    pub unique_recovery_slice_count: usize,
    pub recovery_exponents: Vec<u32>,
}

#[derive(Debug, Serialize)]
pub struct OptionalPacketSummary {
    pub payload_bytes: usize,
    pub payload_sha256: String,
    pub payload_hex: String,
    pub payload_hex_truncated: bool,
}

#[derive(Debug, Serialize)]
pub struct PacketSummary {
    pub offset: usize,
    pub packet_bytes: usize,
    pub packet_type: String,
    pub set_id: String,
    pub packet_md5: String,
    pub checksum_valid: bool,
    pub payload_bytes: usize,
    pub payload_sha256: String,
    pub payload_prefix_hex: String,
    pub payload_prefix_truncated: bool,
}

#[derive(Default)]
struct Parsed {
    creator: Option<String>,
    sources_by_set: BTreeMap<(String, String), SourceFile>,
    block_sizes_by_set: BTreeMap<String, u64>,
    mains_by_set: BTreeMap<String, MainPacket>,
    ifsc_by_set_and_file: BTreeMap<(String, String), IfscPacket>,
    recovery_exponents: BTreeSet<u32>,
    recovery_exponents_by_set: BTreeMap<String, BTreeSet<u32>>,
    recovery_packets: Vec<(String, u32, usize)>,
    packet_definitions: BTreeMap<String, String>,
    optional_packets: BTreeMap<String, Vec<OptionalPacketSummary>>,
    packet_summaries: Vec<PacketSummary>,
    packet_type_counts: BTreeMap<String, usize>,
    packet_count: usize,
    invalid_packet_count: usize,
    unparsed_bytes: usize,
}

#[derive(Debug)]
struct IfscPacket {
    slice_count: usize,
    records_sha256: String,
    preview_hex: String,
    preview_slice_count: usize,
    duplicate_conflict: bool,
}

pub fn parse(data: &[u8]) -> Par2Metadata {
    let mut parsed = Parsed::default();
    let mut offset = 0usize;

    while offset.saturating_add(64) <= data.len() && parsed.packet_count < MAX_PACKETS {
        let Some(relative) = find_magic(data, offset) else {
            parsed.unparsed_bytes = parsed
                .unparsed_bytes
                .saturating_add(data.len().saturating_sub(offset));
            break;
        };
        parsed.unparsed_bytes = parsed
            .unparsed_bytes
            .saturating_add(relative.saturating_sub(offset));
        offset = relative;
        let Some(packet_len_u64) = read_u64(data, offset + 8) else {
            parsed.invalid_packet_count += 1;
            break;
        };
        let Ok(packet_len) = usize::try_from(packet_len_u64) else {
            parsed.invalid_packet_count += 1;
            break;
        };
        if packet_len < 64 || packet_len % 4 != 0 || packet_len > data.len().saturating_sub(offset)
        {
            parsed.invalid_packet_count += 1;
            break;
        }

        let packet = &data[offset..offset + packet_len];
        let packet_type_bytes = &packet[48..64];
        let packet_type = type_string(packet_type_bytes);
        let set_id = hex(&packet[32..48]);
        let packet_md5 = hex(&packet[16..32]);
        let checksum_valid = md5(&packet[32..]) == packet[16..32];
        let payload = &packet[64..];

        parsed.packet_count += 1;
        *parsed
            .packet_type_counts
            .entry(packet_type.clone())
            .or_default() += 1;
        let payload_prefix = &payload[..payload.len().min(MAX_OPTIONAL_HEX_BYTES)];
        parsed.packet_summaries.push(PacketSummary {
            offset,
            packet_bytes: packet_len,
            packet_type: packet_type.clone(),
            set_id: set_id.clone(),
            packet_md5,
            checksum_valid,
            payload_bytes: payload.len(),
            payload_sha256: sha256_hex(payload),
            payload_prefix_hex: hex(payload_prefix),
            payload_prefix_truncated: payload.len() > MAX_OPTIONAL_HEX_BYTES,
        });

        if !checksum_valid {
            parsed.invalid_packet_count += 1;
            offset += packet_len;
            continue;
        }

        if packet_type == "PAR 2.0\\0Main" || packet_type == "PAR 2.0\\0FileDesc" {
            let definition_key = if packet_type == "PAR 2.0\\0Main" {
                format!("{}:main", set_id)
            } else {
                let file_id = payload.get(..16).map(hex).unwrap_or_default();
                format!("{}:file:{file_id}", set_id)
            };
            let definition_hash = hex(&md5(payload));
            if parsed
                .packet_definitions
                .get(&definition_key)
                .is_some_and(|existing| existing != &definition_hash)
            {
                parsed.invalid_packet_count += 1;
            } else {
                parsed
                    .packet_definitions
                    .insert(definition_key, definition_hash);
            }
        }

        match packet_type.as_str() {
            "PAR 2.0\\0Main" if payload.len() >= 12 => {
                let block_size = read_u64(payload, 0).unwrap_or_default();
                let recoverable_file_count = read_u32(payload, 8).unwrap_or_default();
                let ids = payload[12..].chunks_exact(16).map(hex).collect::<Vec<_>>();
                if block_size > 0
                    && block_size % 4 == 0
                    && payload.len().saturating_sub(12) % 16 == 0
                    && usize::try_from(recoverable_file_count).is_ok_and(|count| count <= ids.len())
                {
                    let recoverable_count = recoverable_file_count as usize;
                    let main = MainPacket {
                        recoverable_file_count,
                        recoverable_file_ids: ids[..recoverable_count].to_vec(),
                        non_recoverable_file_ids: ids[recoverable_count..].to_vec(),
                        all_file_ids: ids,
                    };
                    parsed
                        .block_sizes_by_set
                        .entry(set_id.clone())
                        .or_insert(block_size);
                    parsed
                        .mains_by_set
                        .entry(set_id.clone())
                        .or_insert_with(|| main);
                } else {
                    parsed.invalid_packet_count += 1;
                }
            }
            "PAR 2.0\\0Main" => parsed.invalid_packet_count += 1,
            "PAR 2.0\\0FileDesc" if payload.len() >= 57 => {
                let file_id = hex(&payload[0..16]);
                let filename_raw = &payload[56..];
                let filename = filename_raw
                    .split(|byte| *byte == 0)
                    .next()
                    .unwrap_or_default();
                let source = SourceFile {
                    recovery_set_id: set_id.clone(),
                    filename: String::from_utf8_lossy(filename).into_owned(),
                    file_length: read_u64(payload, 48).unwrap_or_default(),
                    md5: hex(&payload[16..32]),
                    md5_first_16k: hex(&payload[32..48]),
                    file_id,
                };
                parsed
                    .sources_by_set
                    .entry((set_id.clone(), source.file_id.clone()))
                    .or_insert(source);
            }
            "PAR 2.0\\0FileDesc" => parsed.invalid_packet_count += 1,
            "PAR 2.0\\0RecvSlic" if payload.len() >= 4 => {
                if let Some(exponent) = read_u32(payload, 0) {
                    parsed
                        .recovery_packets
                        .push((set_id.clone(), exponent, payload.len()));
                }
            }
            "PAR 2.0\\0RecvSlic" => parsed.invalid_packet_count += 1,
            "PAR 2.0\\0Creator" => {
                let creator = payload.split(|byte| *byte == 0).next().unwrap_or_default();
                parsed.creator = Some(String::from_utf8_lossy(creator).trim().to_owned());
            }
            "PAR 2.0\\0IFSC" if payload.len() >= 16 && (payload.len() - 16) % 20 == 0 => {
                let file_id = hex(&payload[..16]);
                let records = &payload[16..];
                let preview_len = records.len().min(MAX_IFSC_PREVIEW_BYTES) / 20 * 20;
                let preview = &records[..preview_len];
                let key = (set_id.clone(), file_id.clone());
                let candidate = IfscPacket {
                    slice_count: records.len() / 20,
                    records_sha256: sha256_hex(records),
                    preview_hex: hex(preview),
                    preview_slice_count: preview.len() / 20,
                    duplicate_conflict: false,
                };
                if let Some(previous) = parsed.ifsc_by_set_and_file.get_mut(&key) {
                    if previous.records_sha256 != candidate.records_sha256 {
                        previous.duplicate_conflict = true;
                        parsed.invalid_packet_count += 1;
                    }
                } else {
                    parsed.ifsc_by_set_and_file.insert(key, candidate);
                }
            }
            "PAR 2.0\\0IFSC" => parsed.invalid_packet_count += 1,
            "PAR 2.0\\0UniFileN" | "PAR 2.0\\0CommASCI" | "PAR 2.0\\0CommUni"
            | "PAR 2.0\\0FileSlic" | "PAR 2.0\\0PkdMain" | "PAR 2.0\\0PkdRecvS"
            | "PAR 2.0\\0RFSC" => {
                let type_key = packet_type.clone();
                let prefix = &payload[..payload.len().min(MAX_OPTIONAL_HEX_BYTES)];
                parsed
                    .optional_packets
                    .entry(type_key)
                    .or_default()
                    .push(OptionalPacketSummary {
                        payload_bytes: payload.len(),
                        payload_sha256: sha256_hex(payload),
                        payload_hex: hex(prefix),
                        payload_hex_truncated: payload.len() > MAX_OPTIONAL_HEX_BYTES,
                    });
            }
            _ => {}
        }

        offset += packet_len;
    }

    let recovery_set_ids = parsed
        .packet_summaries
        .iter()
        .filter(|packet| packet.checksum_valid)
        .map(|packet| packet.set_id.clone())
        .collect::<BTreeSet<_>>();
    for (set_id, exponent, payload_len) in parsed.recovery_packets.clone() {
        let block_size_matches = parsed
            .block_sizes_by_set
            .get(&set_id)
            .and_then(|block_size| block_size.checked_add(4))
            .is_some_and(|expected| u64::try_from(payload_len).ok() == Some(expected));
        if !block_size_matches {
            parsed.invalid_packet_count += 1;
            continue;
        }
        parsed.recovery_exponents.insert(exponent);
        parsed
            .recovery_exponents_by_set
            .entry(set_id)
            .or_default()
            .insert(exponent);
    }
    let input_file_slice_checksums = parsed
        .ifsc_by_set_and_file
        .iter()
        .map(|((set_id, file_id), ifsc)| {
            let source = parsed
                .sources_by_set
                .get(&(set_id.clone(), file_id.clone()));
            let expected_slice_count = source.and_then(|source| {
                parsed
                    .block_sizes_by_set
                    .get(set_id)
                    .filter(|block_size| **block_size > 0)
                    .map(|block_size| source.file_length.div_ceil(*block_size))
            });
            InputFileSliceChecksums {
                recovery_set_id: set_id.clone(),
                file_id: file_id.clone(),
                slice_count: ifsc.slice_count,
                checksum_records_sha256: ifsc.records_sha256.clone(),
                checksum_records_preview_hex: ifsc.preview_hex.clone(),
                preview_slice_count: ifsc.preview_slice_count,
                preview_truncated: ifsc.preview_slice_count < ifsc.slice_count,
                source_file_present: source.is_some(),
                expected_slice_count_from_file_length: expected_slice_count,
                slice_count_matches_file_length: expected_slice_count
                    .map(|expected| expected == ifsc.slice_count as u64),
                duplicate_conflict: ifsc.duplicate_conflict,
            }
        })
        .collect::<Vec<_>>();
    let recovery_sets = recovery_set_ids
        .iter()
        .map(|set_id| {
            let source_files = parsed
                .sources_by_set
                .iter()
                .filter(|((candidate_set_id, _), _)| candidate_set_id == set_id)
                .map(|(_, source)| source.clone())
                .collect::<Vec<_>>();
            let described_file_ids = source_files
                .iter()
                .map(|source| source.file_id.clone())
                .collect::<BTreeSet<_>>();
            let main_packet = parsed.mains_by_set.get(set_id).cloned();
            let main_file_ids = main_packet
                .as_ref()
                .map(|main| main.all_file_ids.iter().cloned().collect::<BTreeSet<_>>())
                .unwrap_or_default();
            let missing_file_description_ids = main_file_ids
                .difference(&described_file_ids)
                .cloned()
                .collect::<Vec<_>>();
            let unexpected_file_description_ids = described_file_ids
                .difference(&main_file_ids)
                .cloned()
                .collect::<Vec<_>>();
            let source_inventory_complete = main_packet.is_some()
                && missing_file_description_ids.is_empty()
                && unexpected_file_description_ids.is_empty();
            let set_slice_checksums = input_file_slice_checksums
                .iter()
                .filter(|ifsc| &ifsc.recovery_set_id == set_id)
                .cloned()
                .collect::<Vec<_>>();
            RecoverySetInventory {
                recovery_set_id: set_id.clone(),
                source_block_size: parsed.block_sizes_by_set.get(set_id).copied(),
                main_packet,
                source_inventory_complete,
                missing_file_description_ids,
                unexpected_file_description_ids,
                source_file_count: source_files.len(),
                source_file_bytes: source_files.iter().map(|source| source.file_length).sum(),
                source_files,
                input_file_slice_checksums: set_slice_checksums,
                unique_recovery_slice_count: parsed
                    .recovery_exponents_by_set
                    .get(set_id)
                    .map_or(0, BTreeSet::len),
                recovery_exponents: parsed
                    .recovery_exponents_by_set
                    .get(set_id)
                    .map_or_else(Vec::new, |exponents| exponents.iter().copied().collect()),
            }
        })
        .collect::<Vec<_>>();
    let single_set = (recovery_sets.len() == 1).then(|| &recovery_sets[0]);
    let source_inventory_complete = single_set.is_some_and(|set| set.source_inventory_complete);
    let missing_file_description_ids = single_set
        .map(|set| set.missing_file_description_ids.clone())
        .unwrap_or_default();
    let unexpected_file_description_ids = single_set
        .map(|set| set.unexpected_file_description_ids.clone())
        .unwrap_or_default();
    let source_block_size = single_set.and_then(|set| set.source_block_size);
    let main_packet = single_set.and_then(|set| set.main_packet.clone());
    let source_file_count = recovery_sets.iter().map(|set| set.source_file_count).sum();
    let source_file_bytes = recovery_sets.iter().map(|set| set.source_file_bytes).sum();
    let source_files = recovery_sets
        .iter()
        .flat_map(|set| set.source_files.iter().cloned())
        .collect::<Vec<_>>();
    let source_block_count = single_set.map_or(0, |set| {
        let recoverable_file_ids = set
            .main_packet
            .as_ref()
            .map(|main| main.recoverable_file_ids.iter().collect::<BTreeSet<_>>())
            .unwrap_or_default();
        set.source_block_size
            .filter(|size| *size > 0)
            .map_or(0, |block_size| {
                set.source_files
                    .iter()
                    .filter(|source| recoverable_file_ids.contains(&source.file_id))
                    .map(|source| source.file_length.div_ceil(block_size))
                    .sum()
            })
    });
    let recovery_count = parsed.recovery_exponents.len();
    let packed_layout_present = parsed.optional_packets.contains_key("PAR 2.0\\0PkdMain")
        || parsed.optional_packets.contains_key("PAR 2.0\\0PkdRecvS");
    let recovery_percent = (source_block_count > 0
        && source_inventory_complete
        && main_packet.is_some()
        && parsed.invalid_packet_count == 0
        && !(parsed.packet_count >= MAX_PACKETS && offset < data.len())
        && !packed_layout_present)
        .then(|| (recovery_count as f64 / source_block_count as f64) * 100.0);

    Par2Metadata {
        parser_engine: "nntmux-rust-ffi",
        creator: parsed.creator,
        packet_count: parsed.packet_count,
        packet_limit_reached: parsed.packet_count >= MAX_PACKETS && offset < data.len(),
        trailing_unparsed_bytes: data.len().saturating_sub(offset),
        unparsed_bytes: parsed.unparsed_bytes,
        invalid_packet_count: parsed.invalid_packet_count,
        source_block_size,
        source_block_count: (source_block_count > 0).then_some(source_block_count),
        main_packet,
        source_inventory_complete,
        missing_file_description_ids,
        unexpected_file_description_ids,
        source_file_count,
        source_file_bytes,
        source_files,
        input_file_slice_checksums,
        recovery_set_ids: recovery_set_ids.into_iter().collect(),
        recovery_sets,
        unique_recovery_slice_count: recovery_count,
        recovery_exponents: parsed.recovery_exponents.into_iter().collect(),
        recovery_to_source_percent: recovery_percent,
        packed_layout_present,
        recovery_percentage_basis: if packed_layout_present {
            "unavailable-packed-recovery-layout"
        } else {
            "unique-valid-recovery-exponents-over-recoverable-source-blocks"
        },
        optional_packet_summaries: parsed.optional_packets,
        packet_summaries: parsed.packet_summaries,
        packet_type_counts: parsed.packet_type_counts,
    }
}

fn find_magic(data: &[u8], from: usize) -> Option<usize> {
    data.get(from..)?
        .windows(MAGIC.len())
        .position(|window| window == MAGIC)
        .map(|position| from + position)
}

fn read_u32(data: &[u8], offset: usize) -> Option<u32> {
    Some(u32::from_le_bytes(
        data.get(offset..offset + 4)?.try_into().ok()?,
    ))
}

fn read_u64(data: &[u8], offset: usize) -> Option<u64> {
    Some(u64::from_le_bytes(
        data.get(offset..offset + 8)?.try_into().ok()?,
    ))
}

fn type_string(bytes: &[u8]) -> String {
    let end = bytes
        .iter()
        .position(|byte| *byte == 0)
        .unwrap_or(bytes.len());
    let mut output = String::new();
    for byte in &bytes[..end] {
        output.push(*byte as char);
    }
    if end < bytes.len() {
        output.push_str("\\0");
        for byte in &bytes[end + 1..] {
            if *byte == 0 {
                break;
            }
            output.push(*byte as char);
        }
    }
    output
}

fn md5(bytes: &[u8]) -> [u8; 16] {
    Md5::digest(bytes).into()
}

fn hex(bytes: &[u8]) -> String {
    const DIGITS: &[u8; 16] = b"0123456789abcdef";
    let mut output = String::with_capacity(bytes.len() * 2);
    for byte in bytes {
        output.push(DIGITS[(byte >> 4) as usize] as char);
        output.push(DIGITS[(byte & 0x0f) as usize] as char);
    }
    output
}

fn sha256_hex(bytes: &[u8]) -> String {
    use sha2::{Digest as Sha2Digest, Sha256 as Sha2};

    hex(&Sha2::digest(bytes))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn packet(kind: &[u8], payload: &[u8]) -> Vec<u8> {
        let padded_payload_length = payload.len().div_ceil(4) * 4;
        let mut packet = Vec::new();
        packet.extend_from_slice(MAGIC);
        let packet_length = 64 + padded_payload_length as u64;
        packet.extend_from_slice(&packet_length.to_le_bytes());
        packet.extend_from_slice(&[0; 16]);
        packet.extend_from_slice(&[b'S'; 16]);
        let mut packet_type = [0; 16];
        packet_type[..kind.len()].copy_from_slice(kind);
        packet.extend_from_slice(&packet_type);
        packet.extend_from_slice(payload);
        packet.resize(64 + padded_payload_length, 0);
        let digest = md5(&packet[32..]);
        packet[16..32].copy_from_slice(&digest);
        packet
    }

    #[test]
    fn parses_file_lengths_recovery_counts_and_checksum_validation() {
        let file_id = [b'F'; 16];
        let input = single_file_set(file_id, 4096, 5000, b"payload.rar", Some(0));
        let metadata = parse(&input);

        assert_eq!(metadata.packet_count, 4);
        assert_eq!(metadata.invalid_packet_count, 0);
        assert_eq!(metadata.source_files[0].filename, "payload.rar");
        assert_eq!(metadata.source_files[0].file_length, 5000);
        assert_eq!(metadata.source_block_count, Some(2));
        assert_eq!(metadata.recovery_to_source_percent, Some(50.0));
        assert!(metadata.source_inventory_complete);
        assert_eq!(metadata.recovery_sets.len(), 1);
        assert_eq!(metadata.recovery_sets[0].unique_recovery_slice_count, 1);
    }

    #[test]
    fn relates_ifsc_to_file_length_within_its_recovery_set() {
        let file_id = [b'F'; 16];
        let input = single_file_set(file_id, 4096, 5000, b"file.rar", None);
        let metadata = parse(&input);

        assert_eq!(metadata.input_file_slice_checksums.len(), 1);
        let checksums = &metadata.input_file_slice_checksums[0];
        assert_eq!(checksums.slice_count, 2);
        assert_eq!(checksums.expected_slice_count_from_file_length, Some(2));
        assert_eq!(checksums.slice_count_matches_file_length, Some(true));
        assert_eq!(metadata.source_files[0].filename, "file.rar");
        assert!(metadata.source_inventory_complete);
    }

    fn single_file_set(
        file_id: [u8; 16],
        block_size: u64,
        file_length: u64,
        filename: &[u8],
        recovery_exponent: Option<u32>,
    ) -> Vec<u8> {
        let mut main = Vec::new();
        main.extend_from_slice(&block_size.to_le_bytes());
        main.extend_from_slice(&1u32.to_le_bytes());
        main.extend_from_slice(&file_id);
        let mut description = Vec::new();
        description.extend_from_slice(&file_id);
        description.extend_from_slice(&[b'M'; 16]);
        description.extend_from_slice(&[b'N'; 16]);
        description.extend_from_slice(&file_length.to_le_bytes());
        description.extend_from_slice(filename);
        let mut ifsc = file_id.to_vec();
        let slice_count = file_length.div_ceil(block_size) as usize;
        ifsc.extend(std::iter::repeat_n(b'C', slice_count * 20));
        let mut packets = vec![
            packet(b"PAR 2.0\0Main", &main),
            packet(b"PAR 2.0\0FileDesc", &description),
            packet(b"PAR 2.0\0IFSC", &ifsc),
        ];
        if let Some(exponent) = recovery_exponent {
            let mut recovery = Vec::with_capacity(block_size as usize + 4);
            recovery.extend_from_slice(&exponent.to_le_bytes());
            recovery.extend(std::iter::repeat_n(b'R', block_size as usize));
            packets.push(packet(b"PAR 2.0\0RecvSlic", &recovery));
        }

        packets.concat()
    }

    #[test]
    fn keeps_recovery_sets_separate_and_disables_mixed_set_percentage() {
        let first_set_id = [b'S'; 16];
        let second_set_id = [b'T'; 16];
        let first_file_id = [b'F'; 16];
        let second_file_id = [b'G'; 16];
        let mut first = single_file_set(first_file_id, 4096, 5000, b"one.rar", Some(0));
        let mut second = single_file_set(second_file_id, 8192, 9000, b"two.rar", Some(1));
        set_packet_ids(&mut first, first_set_id, first_file_id);
        set_packet_ids(&mut second, second_set_id, second_file_id);

        let metadata = parse(&[first, second].concat());

        assert_eq!(metadata.recovery_sets.len(), 2);
        assert_eq!(metadata.recovery_sets[0].source_block_size, Some(4096));
        assert_eq!(metadata.recovery_sets[1].source_block_size, Some(8192));
        assert!(
            metadata
                .recovery_sets
                .iter()
                .all(|set| set.source_inventory_complete)
        );
        assert_eq!(metadata.recovery_sets[0].recovery_exponents, vec![0]);
        assert_eq!(metadata.recovery_sets[1].recovery_exponents, vec![1]);
        assert!(!metadata.source_inventory_complete);
        assert_eq!(metadata.source_block_count, None);
        assert_eq!(metadata.recovery_to_source_percent, None);
    }

    fn set_packet_ids(data: &mut [u8], set_id: [u8; 16], file_id: [u8; 16]) {
        let mut offset = 0;
        while let Some(relative) = find_magic(data, offset) {
            let packet_length = read_u64(data, relative + 8).unwrap() as usize;
            data[relative + 32..relative + 48].copy_from_slice(&set_id);
            if &data[relative + 48..relative + 64] == b"PAR 2.0\0FileDesc" {
                data[relative + 64..relative + 80].copy_from_slice(&file_id);
            } else if &data[relative + 48..relative + 64] == b"PAR 2.0\0IFSC\0\0\0\0" {
                data[relative + 64..relative + 80].copy_from_slice(&file_id);
            }
            let packet_md5 = md5(&data[relative + 32..relative + packet_length]);
            data[relative + 16..relative + 32].copy_from_slice(&packet_md5);
            offset = relative + packet_length;
        }
    }

    #[test]
    fn sha256_helper_matches_known_answer() {
        assert_eq!(
            sha256_hex(b"abc"),
            "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"
        );
    }
}
