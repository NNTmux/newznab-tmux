use serde::Serialize;
use sha2::{Digest, Sha256};
use std::collections::BTreeSet;

const SIGNATURE: &[u8; 6] = b"7z\xbc\xaf'\x1c";
const MAX_FRAGMENT_COUNT: usize = 32;
const MAX_RETAINED_BYTES: usize = 16 * 1024 * 1024;
const MAX_NEXT_HEADER_BYTES: usize = 1024 * 1024;

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
    }
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
