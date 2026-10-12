use serde::Serialize;
use sha2::{Digest, Sha256};
use std::collections::BTreeSet;

const SIGNATURE: &[u8; 6] = b"7z\xbc\xaf'\x1c";
const MAX_FRAGMENT_COUNT: usize = 32;
const MAX_RETAINED_BYTES: usize = 16 * 1024 * 1024;
const MAX_NEXT_HEADER_BYTES: usize = 1024 * 1024;
const MAX_PACK_STREAM_COUNT: usize = 32;
const MAX_PACKED_HEADER_BYTES: usize = 1024 * 1024;

#[derive(Debug, Serialize)]
pub struct SevenZipNextHeaderResolution {
    pub relationship: &'static str,
    pub resolution_status: &'static str,
    pub archive_start_file_offset: u64,
    pub next_header_file_start: Option<u64>,
    pub next_header_file_end_exclusive: Option<u64>,
    pub declared_next_header_size: Option<u64>,
    pub expected_crc32: Option<u32>,
    pub observed_crc32: Option<u32>,
    pub checksum_valid: Option<bool>,
    pub sha256: Option<String>,
    pub header_type: Option<u8>,
    pub header_kind: Option<&'static str>,
    pub covered_ranges: Vec<ByteRange>,
    pub missing_ranges: Vec<ByteRange>,
    pub source_fragment_indexes: Vec<usize>,
    pub conflicting_overlap_bytes: usize,
    pub encoded_header_pack_info: Option<EncodedHeaderPackInfo>,
}

#[derive(Debug, Serialize)]
pub struct EncodedHeaderPackInfo {
    pub parse_status: &'static str,
    pub pack_position: Option<u64>,
    pub declared_pack_stream_count: Option<u64>,
    pub streams: Vec<EncodedHeaderPackedStream>,
}

#[derive(Debug, Serialize)]
pub struct EncodedHeaderPackedStream {
    pub relationship: &'static str,
    pub stream_index: usize,
    pub declared_packed_size: u64,
    pub file_start: Option<u64>,
    pub file_end_exclusive: Option<u64>,
    pub expected_crc32: Option<u32>,
    pub observed_crc32: Option<u32>,
    pub checksum_valid: Option<bool>,
    pub sha256: Option<String>,
    pub resolution_status: &'static str,
    pub covered_ranges: Vec<ByteRange>,
    pub missing_ranges: Vec<ByteRange>,
    pub source_fragment_indexes: Vec<usize>,
    pub conflicting_overlap_bytes: usize,
}

#[derive(Debug, Serialize)]
pub struct ByteRange {
    pub start: u64,
    pub end_exclusive: u64,
}

struct Fragment<'a> {
    start: u64,
    bytes: &'a [u8],
}

pub fn parse(input: &[u8]) -> Option<SevenZipNextHeaderResolution> {
    if input.len() < 12 {
        return None;
    }
    let fragment_count = usize::try_from(u32::from_le_bytes(input[0..4].try_into().ok()?)).ok()?;
    if fragment_count == 0 || fragment_count > MAX_FRAGMENT_COUNT {
        return None;
    }
    let declared_file_size = u64::from_le_bytes(input[4..12].try_into().ok()?);
    let mut cursor = 12usize;
    let mut fragments = Vec::with_capacity(fragment_count);
    let mut retained_bytes = 0usize;
    for _ in 0..fragment_count {
        let header_end = cursor.checked_add(12)?;
        let header = input.get(cursor..header_end)?;
        let start = u64::from_le_bytes(header[0..8].try_into().ok()?);
        let length = usize::try_from(u32::from_le_bytes(header[8..12].try_into().ok()?)).ok()?;
        retained_bytes = retained_bytes.checked_add(length)?;
        if retained_bytes > MAX_RETAINED_BYTES {
            return None;
        }
        let end = header_end.checked_add(length)?;
        let bytes = input.get(header_end..end)?;
        let fragment_end = start.checked_add(u64::try_from(length).ok()?)?;
        if fragment_end > declared_file_size {
            return Some(empty_result("fragment_outside_declared_file"));
        }
        fragments.push(Fragment { start, bytes });
        cursor = end;
    }
    if cursor != input.len() {
        return None;
    }

    let anchor = fragments
        .iter()
        .find(|fragment| fragment.start == 0 && fragment.bytes.len() >= 32);
    let Some(anchor) = anchor else {
        return Some(empty_result("unobserved"));
    };
    if declared_file_size < 32 {
        return Some(empty_result("declared_file_too_small_for_start_header"));
    }
    if anchor.bytes.get(..6)? != SIGNATURE {
        return Some(empty_result("unsupported_anchor"));
    }
    let mut conflicting_anchor_positions = [false; 32];
    for fragment in &fragments {
        let fragment_end = fragment
            .start
            .checked_add(u64::try_from(fragment.bytes.len()).ok()?)?;
        let intersect_start = fragment.start;
        let intersect_end = fragment_end.min(32);
        if intersect_start >= intersect_end {
            continue;
        }
        let source_start = usize::try_from(intersect_start.checked_sub(fragment.start)?).ok()?;
        let anchor_start = usize::try_from(intersect_start).ok()?;
        let length = usize::try_from(intersect_end.checked_sub(intersect_start)?).ok()?;
        let source = fragment
            .bytes
            .get(source_start..source_start.checked_add(length)?)?;
        for (offset, byte) in source.iter().enumerate() {
            let anchor_offset = anchor_start.checked_add(offset)?;
            if anchor.bytes.get(anchor_offset).copied() != Some(*byte) {
                conflicting_anchor_positions[anchor_offset] = true;
            }
        }
    }
    let conflicting_anchor_bytes = conflicting_anchor_positions
        .iter()
        .filter(|value| **value)
        .count();
    if conflicting_anchor_bytes > 0 {
        return Some(SevenZipNextHeaderResolution {
            resolution_status: "conflicting_anchor_overlaps",
            conflicting_overlap_bytes: conflicting_anchor_bytes,
            ..empty_result("conflicting_anchor_overlaps")
        });
    }
    if anchor.bytes.get(6).copied()? != 0 {
        return Some(empty_result("unsupported_major_version"));
    }
    let start_header_crc = le_u32(anchor.bytes, 8)?;
    if crc32fast::hash(anchor.bytes.get(12..32)?) != start_header_crc {
        return Some(empty_result("invalid_start_header_crc"));
    }
    let next_header_offset = le_u64(anchor.bytes, 12)?;
    let next_header_size = le_u64(anchor.bytes, 20)?;
    let expected_crc = le_u32(anchor.bytes, 28)?;
    let Some(next_header_start) = 32u64.checked_add(next_header_offset) else {
        return Some(empty_result("unsupported_integer_range"));
    };
    let Some(next_header_end) = next_header_start.checked_add(next_header_size) else {
        return Some(empty_result("unsupported_integer_range"));
    };

    if next_header_size == 0 {
        let status = if next_header_offset == 0 && expected_crc == 0 {
            "empty_archive_header"
        } else {
            "invalid_empty_header_pointer"
        };
        return Some(SevenZipNextHeaderResolution {
            relationship: "seven_zip_next_header_from_sampled_articles",
            resolution_status: status,
            archive_start_file_offset: 0,
            next_header_file_start: Some(next_header_start),
            next_header_file_end_exclusive: Some(next_header_end),
            declared_next_header_size: Some(next_header_size),
            expected_crc32: Some(expected_crc),
            observed_crc32: None,
            checksum_valid: None,
            sha256: None,
            header_type: None,
            header_kind: None,
            covered_ranges: Vec::new(),
            missing_ranges: Vec::new(),
            source_fragment_indexes: Vec::new(),
            conflicting_overlap_bytes: 0,
            encoded_header_pack_info: None,
        });
    }
    let Ok(header_length) = usize::try_from(next_header_size) else {
        return Some(empty_result("unsupported_integer_range"));
    };
    if header_length > MAX_NEXT_HEADER_BYTES {
        return Some(empty_result("budget_exceeded"));
    }
    if declared_file_size == 0 || next_header_end > declared_file_size {
        return Some(empty_result("pointer_outside_declared_file"));
    }

    let mut output = vec![0u8; header_length];
    let mut present = vec![false; header_length];
    let mut conflicted = vec![false; header_length];
    let mut contributors = BTreeSet::new();
    let mut conflicting_overlap_bytes = 0usize;
    for (index, fragment) in fragments.iter().enumerate() {
        let fragment_end = fragment
            .start
            .checked_add(u64::try_from(fragment.bytes.len()).ok()?)?;
        let intersect_start = fragment.start.max(next_header_start);
        let intersect_end = fragment_end.min(next_header_end);
        if intersect_start >= intersect_end {
            continue;
        }
        let fragment_offset = usize::try_from(intersect_start.checked_sub(fragment.start)?).ok()?;
        let output_offset =
            usize::try_from(intersect_start.checked_sub(next_header_start)?).ok()?;
        let intersection_length =
            usize::try_from(intersect_end.checked_sub(intersect_start)?).ok()?;
        let source = fragment
            .bytes
            .get(fragment_offset..fragment_offset.checked_add(intersection_length)?)?;
        let destination_end = output_offset.checked_add(intersection_length)?;
        let destination = output.get_mut(output_offset..destination_end)?;
        let covered = present.get_mut(output_offset..destination_end)?;
        for offset in 0..intersection_length {
            if covered[offset] {
                if destination[offset] != source[offset] {
                    let output_position = output_offset.checked_add(offset)?;
                    if !conflicted[output_position] {
                        conflicting_overlap_bytes += 1;
                        conflicted[output_position] = true;
                    }
                }
            } else {
                destination[offset] = source[offset];
                covered[offset] = true;
            }
        }
        contributors.insert(index);
    }

    let covered_ranges = ranges_for_presence(&present, next_header_start, true)?;
    let missing_ranges = ranges_for_presence(&present, next_header_start, false)?;
    let base = SevenZipNextHeaderResolution {
        relationship: "seven_zip_next_header_from_sampled_articles",
        resolution_status: "partially_covered",
        archive_start_file_offset: 0,
        next_header_file_start: Some(next_header_start),
        next_header_file_end_exclusive: Some(next_header_end),
        declared_next_header_size: Some(next_header_size),
        expected_crc32: Some(expected_crc),
        observed_crc32: None,
        checksum_valid: None,
        sha256: None,
        header_type: None,
        header_kind: None,
        covered_ranges,
        missing_ranges,
        source_fragment_indexes: contributors.into_iter().collect(),
        conflicting_overlap_bytes,
        encoded_header_pack_info: None,
    };
    if conflicting_overlap_bytes > 0 {
        return Some(SevenZipNextHeaderResolution {
            resolution_status: "conflicting_overlaps",
            ..base
        });
    }
    if present.iter().any(|is_present| !is_present) {
        return Some(base);
    }

    let observed_crc = crc32fast::hash(&output);
    let checksum_valid = observed_crc == expected_crc;
    let header_type = output.first().copied();
    let header_kind = header_type.map(|value| match value {
        0x00 => "end_marker",
        0x01 => "header",
        0x17 => "encoded_header",
        _ => "unknown",
    });
    let digest = Sha256::digest(&output);
    let encoded_header_pack_info = if checksum_valid && header_type == Some(0x17) {
        Some(parse_encoded_header_pack_info(
            &output,
            next_header_offset,
            declared_file_size,
            &fragments,
        ))
    } else {
        None
    };
    Some(SevenZipNextHeaderResolution {
        resolution_status: if checksum_valid {
            "resolved_checksum_valid"
        } else {
            "resolved_checksum_mismatch"
        },
        observed_crc32: Some(observed_crc),
        checksum_valid: Some(checksum_valid),
        sha256: Some(digest.iter().map(|byte| format!("{byte:02x}")).collect()),
        header_type,
        header_kind,
        encoded_header_pack_info,
        ..base
    })
}

fn empty_result(status: &'static str) -> SevenZipNextHeaderResolution {
    SevenZipNextHeaderResolution {
        relationship: "seven_zip_next_header_from_sampled_articles",
        resolution_status: status,
        archive_start_file_offset: 0,
        next_header_file_start: None,
        next_header_file_end_exclusive: None,
        declared_next_header_size: None,
        expected_crc32: None,
        observed_crc32: None,
        checksum_valid: None,
        sha256: None,
        header_type: None,
        header_kind: None,
        covered_ranges: Vec::new(),
        missing_ranges: Vec::new(),
        source_fragment_indexes: Vec::new(),
        conflicting_overlap_bytes: 0,
        encoded_header_pack_info: None,
    }
}

fn parse_encoded_header_pack_info(
    header: &[u8],
    next_header_offset: u64,
    declared_file_size: u64,
    fragments: &[Fragment<'_>],
) -> EncodedHeaderPackInfo {
    if header.first().copied() != Some(0x17) {
        return empty_pack_info("not_an_encoded_header", None, None);
    }

    let mut cursor = 1usize;
    if read_byte(header, &mut cursor) != Some(0x06) {
        return empty_pack_info("encoded_header_does_not_start_with_pack_info", None, None);
    }
    let Some(pack_position) = read_7z_uint64(header, &mut cursor) else {
        return empty_pack_info("truncated_pack_position", None, None);
    };
    let Some(stream_count) = read_7z_uint64(header, &mut cursor) else {
        return empty_pack_info("truncated_pack_stream_count", Some(pack_position), None);
    };
    let Ok(stream_count_usize) = usize::try_from(stream_count) else {
        return empty_pack_info(
            "pack_stream_count_exceeds_supported_range",
            Some(pack_position),
            Some(stream_count),
        );
    };
    if stream_count_usize > MAX_PACK_STREAM_COUNT {
        return empty_pack_info(
            "pack_stream_count_budget_exceeded",
            Some(pack_position),
            Some(stream_count),
        );
    }

    let mut sizes = None;
    let mut checksums = None;
    let mut ended = false;
    while cursor < header.len() {
        let Some(property) = read_byte(header, &mut cursor) else {
            break;
        };
        match property {
            0x00 => {
                ended = true;
                break;
            }
            0x09 if sizes.is_none() => {
                let mut parsed_sizes = Vec::with_capacity(stream_count_usize);
                for _ in 0..stream_count_usize {
                    let Some(size) = read_7z_uint64(header, &mut cursor) else {
                        return empty_pack_info(
                            "truncated_pack_sizes",
                            Some(pack_position),
                            Some(stream_count),
                        );
                    };
                    parsed_sizes.push(size);
                }
                sizes = Some(parsed_sizes);
            }
            0x09 => {
                return empty_pack_info(
                    "duplicate_pack_size_property",
                    Some(pack_position),
                    Some(stream_count),
                );
            }
            0x0A if checksums.is_none() => {
                if sizes.is_none() {
                    return empty_pack_info(
                        "pack_crc_before_pack_sizes",
                        Some(pack_position),
                        Some(stream_count),
                    );
                }
                match read_7z_digests(header, &mut cursor, stream_count_usize) {
                    Ok(parsed_checksums) => checksums = Some(parsed_checksums),
                    Err(status) => {
                        return empty_pack_info(status, Some(pack_position), Some(stream_count));
                    }
                }
            }
            0x0A => {
                return empty_pack_info(
                    "duplicate_pack_crc_property",
                    Some(pack_position),
                    Some(stream_count),
                );
            }
            _ => {
                return empty_pack_info(
                    "unsupported_pack_info_property",
                    Some(pack_position),
                    Some(stream_count),
                );
            }
        }
    }
    if !ended {
        return empty_pack_info(
            "missing_pack_info_end_marker",
            Some(pack_position),
            Some(stream_count),
        );
    }
    let Some(sizes) = sizes else {
        return empty_pack_info(
            "pack_sizes_missing",
            Some(pack_position),
            Some(stream_count),
        );
    };
    let checksums = checksums.unwrap_or_else(|| vec![None; stream_count_usize]);
    let Some(total_packed_bytes) = sizes
        .iter()
        .try_fold(0u64, |total, size| total.checked_add(*size))
    else {
        return empty_pack_info(
            "packed_size_sum_overflow",
            Some(pack_position),
            Some(stream_count),
        );
    };
    let Some(packed_end_relative_to_signature_header) =
        pack_position.checked_add(total_packed_bytes)
    else {
        return empty_pack_info(
            "packed_range_overflow",
            Some(pack_position),
            Some(stream_count),
        );
    };
    if packed_end_relative_to_signature_header > next_header_offset {
        return empty_pack_info(
            "packed_ranges_cross_next_header",
            Some(pack_position),
            Some(stream_count),
        );
    }

    let packed_byte_budget_exceeded = total_packed_bytes > MAX_PACKED_HEADER_BYTES as u64;
    let mut streams = Vec::with_capacity(stream_count_usize);
    let mut range_offset = 0u64;
    for (stream_index, size) in sizes.into_iter().enumerate() {
        let stream_start = 32u64
            .checked_add(pack_position)
            .and_then(|start| start.checked_add(range_offset));
        let stream_end = stream_start.and_then(|start| start.checked_add(size));
        let expected_crc32 = checksums.get(stream_index).copied().flatten();
        let stream = match (stream_start, stream_end) {
            (Some(start), Some(end)) if end <= declared_file_size => {
                if packed_byte_budget_exceeded {
                    empty_packed_stream(
                        stream_index,
                        size,
                        Some(start),
                        Some(end),
                        expected_crc32,
                        "packed_stream_byte_budget_exceeded",
                    )
                } else {
                    resolve_packed_stream(fragments, stream_index, size, start, end, expected_crc32)
                }
            }
            (Some(start), Some(end)) => empty_packed_stream(
                stream_index,
                size,
                Some(start),
                Some(end),
                expected_crc32,
                "packed_range_outside_declared_file",
            ),
            _ => empty_packed_stream(
                stream_index,
                size,
                None,
                None,
                expected_crc32,
                "packed_range_overflow",
            ),
        };
        streams.push(stream);
        let Some(next_range_offset) = range_offset.checked_add(size) else {
            return empty_pack_info(
                "packed_range_overflow",
                Some(pack_position),
                Some(stream_count),
            );
        };
        range_offset = next_range_offset;
    }

    EncodedHeaderPackInfo {
        parse_status: if packed_byte_budget_exceeded {
            "pack_info_parsed_packed_stream_byte_budget_exceeded"
        } else {
            "pack_info_parsed"
        },
        pack_position: Some(pack_position),
        declared_pack_stream_count: Some(stream_count),
        streams,
    }
}

fn resolve_packed_stream(
    fragments: &[Fragment<'_>],
    stream_index: usize,
    declared_size: u64,
    start: u64,
    end: u64,
    expected_crc32: Option<u32>,
) -> EncodedHeaderPackedStream {
    let Ok(length) = usize::try_from(declared_size) else {
        return empty_packed_stream(
            stream_index,
            declared_size,
            Some(start),
            Some(end),
            expected_crc32,
            "unsupported_integer_range",
        );
    };
    let mut output = vec![0u8; length];
    let mut present = vec![false; length];
    let mut conflicted = vec![false; length];
    let mut contributors = BTreeSet::new();
    let mut conflicting_overlap_bytes = 0usize;

    for (index, fragment) in fragments.iter().enumerate() {
        let Some(fragment_end) = fragment
            .start
            .checked_add(u64::try_from(fragment.bytes.len()).unwrap_or(u64::MAX))
        else {
            continue;
        };
        let intersect_start = fragment.start.max(start);
        let intersect_end = fragment_end.min(end);
        if intersect_start >= intersect_end {
            continue;
        }
        let (Some(fragment_offset), Some(output_offset), Some(intersection_length)) = (
            usize::try_from(intersect_start.saturating_sub(fragment.start)).ok(),
            usize::try_from(intersect_start.saturating_sub(start)).ok(),
            usize::try_from(intersect_end.saturating_sub(intersect_start)).ok(),
        ) else {
            continue;
        };
        let Some(source) = fragment
            .bytes
            .get(fragment_offset..fragment_offset.saturating_add(intersection_length))
        else {
            continue;
        };
        let destination_end = output_offset.saturating_add(intersection_length);
        let (Some(destination), Some(covered)) = (
            output.get_mut(output_offset..destination_end),
            present.get_mut(output_offset..destination_end),
        ) else {
            continue;
        };
        for offset in 0..intersection_length {
            if covered[offset] {
                if destination[offset] != source[offset] && !conflicted[output_offset + offset] {
                    conflicting_overlap_bytes += 1;
                    conflicted[output_offset + offset] = true;
                }
            } else {
                destination[offset] = source[offset];
                covered[offset] = true;
            }
        }
        contributors.insert(index);
    }

    let covered_ranges = ranges_for_presence(&present, start, true).unwrap_or_default();
    let missing_ranges = ranges_for_presence(&present, start, false).unwrap_or_default();
    if conflicting_overlap_bytes > 0 {
        return EncodedHeaderPackedStream {
            relationship: "seven_zip_encoded_header_packed_stream_from_sampled_articles",
            stream_index,
            declared_packed_size: declared_size,
            file_start: Some(start),
            file_end_exclusive: Some(end),
            expected_crc32,
            observed_crc32: None,
            checksum_valid: None,
            sha256: None,
            resolution_status: "conflicting_overlaps",
            covered_ranges,
            missing_ranges,
            source_fragment_indexes: contributors.into_iter().collect(),
            conflicting_overlap_bytes,
        };
    }
    if present.iter().any(|is_present| !is_present) {
        return EncodedHeaderPackedStream {
            relationship: "seven_zip_encoded_header_packed_stream_from_sampled_articles",
            stream_index,
            declared_packed_size: declared_size,
            file_start: Some(start),
            file_end_exclusive: Some(end),
            expected_crc32,
            observed_crc32: None,
            checksum_valid: None,
            sha256: None,
            resolution_status: if covered_ranges.is_empty() {
                "unobserved"
            } else {
                "partially_covered"
            },
            covered_ranges,
            missing_ranges,
            source_fragment_indexes: contributors.into_iter().collect(),
            conflicting_overlap_bytes: 0,
        };
    }

    let observed_crc32 = crc32fast::hash(&output);
    let digest = Sha256::digest(&output);
    EncodedHeaderPackedStream {
        relationship: "seven_zip_encoded_header_packed_stream_from_sampled_articles",
        stream_index,
        declared_packed_size: declared_size,
        file_start: Some(start),
        file_end_exclusive: Some(end),
        expected_crc32,
        observed_crc32: Some(observed_crc32),
        checksum_valid: expected_crc32.map(|expected| expected == observed_crc32),
        sha256: Some(digest.iter().map(|byte| format!("{byte:02x}")).collect()),
        resolution_status: match expected_crc32 {
            Some(_) if expected_crc32 == Some(observed_crc32) => "resolved_checksum_valid",
            Some(_) => "resolved_checksum_mismatch",
            None => "resolved_without_declared_crc",
        },
        covered_ranges,
        missing_ranges,
        source_fragment_indexes: contributors.into_iter().collect(),
        conflicting_overlap_bytes: 0,
    }
}

fn empty_packed_stream(
    stream_index: usize,
    declared_size: u64,
    file_start: Option<u64>,
    file_end_exclusive: Option<u64>,
    expected_crc32: Option<u32>,
    resolution_status: &'static str,
) -> EncodedHeaderPackedStream {
    EncodedHeaderPackedStream {
        relationship: "seven_zip_encoded_header_packed_stream_from_sampled_articles",
        stream_index,
        declared_packed_size: declared_size,
        file_start,
        file_end_exclusive,
        expected_crc32,
        observed_crc32: None,
        checksum_valid: None,
        sha256: None,
        resolution_status,
        covered_ranges: Vec::new(),
        missing_ranges: Vec::new(),
        source_fragment_indexes: Vec::new(),
        conflicting_overlap_bytes: 0,
    }
}

fn empty_pack_info(
    parse_status: &'static str,
    pack_position: Option<u64>,
    declared_pack_stream_count: Option<u64>,
) -> EncodedHeaderPackInfo {
    EncodedHeaderPackInfo {
        parse_status,
        pack_position,
        declared_pack_stream_count,
        streams: Vec::new(),
    }
}

fn read_7z_digests(
    data: &[u8],
    cursor: &mut usize,
    stream_count: usize,
) -> Result<Vec<Option<u32>>, &'static str> {
    let Some(all_defined) = read_byte(data, cursor) else {
        return Err("truncated_pack_crc_definition_flag");
    };
    let mut defined = vec![false; stream_count];
    match all_defined {
        0 => {
            let bitmap_size = stream_count.saturating_add(7) / 8;
            for bitmap_index in 0..bitmap_size {
                let Some(bitmap) = read_byte(data, cursor) else {
                    return Err("truncated_pack_crc_definition_bitmap");
                };
                for bit in 0..8 {
                    let stream_index = bitmap_index.saturating_mul(8).saturating_add(bit);
                    if stream_index < stream_count {
                        defined[stream_index] = bitmap & (0x80 >> bit) != 0;
                    }
                }
            }
        }
        _ => defined.fill(true),
    }

    let mut checksums = Vec::with_capacity(stream_count);
    for is_defined in defined {
        if !is_defined {
            checksums.push(None);
            continue;
        }
        let Some(end) = cursor.checked_add(4) else {
            return Err("truncated_pack_crc_values");
        };
        let Some(bytes) = data.get(*cursor..end) else {
            return Err("truncated_pack_crc_values");
        };
        checksums.push(Some(u32::from_le_bytes(
            bytes.try_into().map_err(|_| "truncated_pack_crc_values")?,
        )));
        *cursor = end;
    }
    Ok(checksums)
}

fn read_7z_uint64(data: &[u8], cursor: &mut usize) -> Option<u64> {
    let first = read_byte(data, cursor)?;
    let mut value = 0u64;
    for index in 0..8 {
        let mask = 0x80u8 >> index;
        if first & mask == 0 {
            value |= u64::from(first & (mask - 1)) << (index * 8);
            return Some(value);
        }
        value |= u64::from(read_byte(data, cursor)?) << (index * 8);
    }
    Some(value)
}

fn read_byte(data: &[u8], cursor: &mut usize) -> Option<u8> {
    let value = *data.get(*cursor)?;
    *cursor = cursor.checked_add(1)?;
    Some(value)
}

fn ranges_for_presence(present: &[bool], start: u64, value: bool) -> Option<Vec<ByteRange>> {
    let mut ranges = Vec::new();
    let mut index = 0usize;
    while index < present.len() {
        if present[index] != value {
            index += 1;
            continue;
        }
        let range_start = index;
        while index < present.len() && present[index] == value {
            index += 1;
        }
        ranges.push(ByteRange {
            start: start.checked_add(u64::try_from(range_start).ok()?)?,
            end_exclusive: start.checked_add(u64::try_from(index).ok()?)?,
        });
    }
    Some(ranges)
}

fn le_u32(bytes: &[u8], offset: usize) -> Option<u32> {
    Some(u32::from_le_bytes(
        bytes.get(offset..offset.checked_add(4)?)?.try_into().ok()?,
    ))
}

fn le_u64(bytes: &[u8], offset: usize) -> Option<u64> {
    Some(u64::from_le_bytes(
        bytes.get(offset..offset.checked_add(8)?)?.try_into().ok()?,
    ))
}
