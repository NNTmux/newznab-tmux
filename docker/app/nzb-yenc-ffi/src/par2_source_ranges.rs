use md5::{Digest, Md5};
use serde::Serialize;
use std::collections::BTreeSet;

const MAGIC: &[u8; 4] = b"P2SR";
const MAX_FRAGMENTS: usize = 32;
const MAX_SLICES: usize = 16;
const MAX_FRAGMENT_BYTES: usize = 16 * 1024 * 1024;
const MAX_HASH_WORK_BYTES: usize = 32 * 1024 * 1024;
const ZEROES: [u8; 4096] = [0; 4096];

#[derive(Debug, Serialize)]
pub struct SourceSliceResolution {
    pub relationship: &'static str,
    pub resolution_status: &'static str,
    pub declared_file_size: u64,
    pub slices: Vec<SliceResult>,
}

#[derive(Debug, Serialize)]
pub struct SliceResult {
    pub slice_index: u32,
    pub source_byte_start: u64,
    pub source_byte_end_exclusive: u64,
    pub source_bytes_in_slice: u64,
    pub zero_padding_bytes: u64,
    pub expected_md5: String,
    pub observed_md5: Option<String>,
    pub md5_matches: Option<bool>,
    pub expected_crc32: u32,
    pub observed_crc32: Option<u32>,
    pub crc32_matches: Option<bool>,
    pub checksum_valid: Option<bool>,
    pub status: &'static str,
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

struct ExpectedSlice {
    index: u32,
    start: u64,
    end: u64,
    padding: u64,
    md5: [u8; 16],
    crc32: u32,
}

pub fn parse(input: &[u8]) -> Option<SourceSliceResolution> {
    if input.len() < 20 || input.get(..4)? != MAGIC {
        return None;
    }
    let fragment_count = usize::try_from(read_u32(input, 4)?).ok()?;
    let slice_count = usize::try_from(read_u32(input, 8)?).ok()?;
    let declared_file_size = read_u64(input, 12)?;
    if fragment_count > MAX_FRAGMENTS || slice_count == 0 || slice_count > MAX_SLICES {
        return None;
    }

    let mut cursor = 20usize;
    let mut fragments = Vec::with_capacity(fragment_count);
    let mut retained_bytes = 0usize;
    for _ in 0..fragment_count {
        let start = read_u64(input, cursor)?;
        let length = usize::try_from(read_u32(input, cursor.checked_add(8)?)?).ok()?;
        cursor = cursor.checked_add(12)?;
        retained_bytes = retained_bytes.checked_add(length)?;
        if retained_bytes > MAX_FRAGMENT_BYTES {
            return None;
        }
        let end = cursor.checked_add(length)?;
        let bytes = input.get(cursor..end)?;
        let fragment_end = start.checked_add(u64::try_from(length).ok()?)?;
        fragments.push(Fragment { start, bytes });
        if fragment_end > declared_file_size {
            return Some(unresolved(
                declared_file_size,
                Vec::new(),
                "fragment_outside_declared_file",
            ));
        }
        cursor = end;
    }

    let mut expected_slices = Vec::with_capacity(slice_count);
    for _ in 0..slice_count {
        let index = read_u32(input, cursor)?;
        let start = read_u64(input, cursor.checked_add(4)?)?;
        let end = read_u64(input, cursor.checked_add(12)?)?;
        let padding = read_u64(input, cursor.checked_add(20)?)?;
        let md5_start = cursor.checked_add(28)?;
        let md5_end = md5_start.checked_add(16)?;
        let md5: [u8; 16] = input.get(md5_start..md5_end)?.try_into().ok()?;
        let crc32 = read_u32(input, md5_end)?;
        cursor = md5_end.checked_add(4)?;
        if end <= start || end > declared_file_size {
            return Some(unresolved(
                declared_file_size,
                Vec::new(),
                "invalid_slice_range",
            ));
        }
        expected_slices.push(ExpectedSlice {
            index,
            start,
            end,
            padding,
            md5,
            crc32,
        });
    }
    if cursor != input.len() {
        return None;
    }

    let mut hash_work_bytes = 0usize;
    let slices = expected_slices
        .into_iter()
        .map(|expected| resolve_slice(&fragments, expected, &mut hash_work_bytes))
        .collect::<Vec<_>>();
    let resolution_status = if slices.iter().any(|slice| slice.status == "budget_exceeded") {
        "budget_exceeded"
    } else if slices
        .iter()
        .any(|slice| slice.status == "resolved_checksum_mismatch")
    {
        "checksum_mismatch"
    } else if slices
        .iter()
        .any(|slice| slice.status == "conflicting_overlaps")
    {
        "conflicting_overlaps"
    } else if slices
        .iter()
        .any(|slice| slice.status == "partially_covered")
    {
        "partially_covered"
    } else if slices
        .iter()
        .all(|slice| slice.status == "resolved_checksum_valid")
    {
        "all_sampled_slices_valid"
    } else if slices
        .iter()
        .any(|slice| slice.status == "resolved_checksum_valid")
    {
        "partially_resolved"
    } else {
        "unobserved"
    };

    Some(SourceSliceResolution {
        relationship: "par2_ifsc_slice_to_sampled_source_bytes",
        resolution_status,
        declared_file_size,
        slices,
    })
}

fn resolve_slice(
    fragments: &[Fragment<'_>],
    expected: ExpectedSlice,
    hash_work_bytes: &mut usize,
) -> SliceResult {
    let source_length_u64 = expected.end - expected.start;
    let expected_block_length = source_length_u64.checked_add(expected.padding);
    let expected_md5 = hex(&expected.md5);
    let mut result = SliceResult {
        slice_index: expected.index,
        source_byte_start: expected.start,
        source_byte_end_exclusive: expected.end,
        source_bytes_in_slice: source_length_u64,
        zero_padding_bytes: expected.padding,
        expected_md5,
        observed_md5: None,
        md5_matches: None,
        expected_crc32: expected.crc32,
        observed_crc32: None,
        crc32_matches: None,
        checksum_valid: None,
        status: "unobserved",
        covered_ranges: Vec::new(),
        missing_ranges: Vec::new(),
        source_fragment_indexes: Vec::new(),
        conflicting_overlap_bytes: 0,
    };

    let Ok(source_length) = usize::try_from(source_length_u64) else {
        result.status = "budget_exceeded";
        return result;
    };
    let Some(block_length) = expected_block_length.and_then(|value| usize::try_from(value).ok())
    else {
        result.status = "budget_exceeded";
        return result;
    };
    if source_length == 0
        || block_length == 0
        || block_length > MAX_HASH_WORK_BYTES
        || hash_work_bytes
            .checked_add(block_length)
            .is_none_or(|total| total > MAX_HASH_WORK_BYTES)
    {
        result.status = "budget_exceeded";
        return result;
    }
    *hash_work_bytes += block_length;

    let mut bytes = vec![0u8; source_length];
    let mut present = vec![false; source_length];
    let mut conflicted = vec![false; source_length];
    let mut contributors = BTreeSet::new();
    for (fragment_index, fragment) in fragments.iter().enumerate() {
        let Some(fragment_end) = fragment.start.checked_add(fragment.bytes.len() as u64) else {
            result.status = "fragment_outside_declared_file";
            return result;
        };
        let intersect_start = fragment.start.max(expected.start);
        let intersect_end = fragment_end.min(expected.end);
        if intersect_start >= intersect_end {
            continue;
        }
        let Ok(fragment_offset) = usize::try_from(intersect_start - fragment.start) else {
            result.status = "fragment_outside_declared_file";
            return result;
        };
        let Ok(output_offset) = usize::try_from(intersect_start - expected.start) else {
            result.status = "budget_exceeded";
            return result;
        };
        let Ok(length) = usize::try_from(intersect_end - intersect_start) else {
            result.status = "budget_exceeded";
            return result;
        };
        let Some(source) = fragment
            .bytes
            .get(fragment_offset..fragment_offset + length)
        else {
            result.status = "fragment_outside_declared_file";
            return result;
        };
        let Some(destination_end) = output_offset.checked_add(length) else {
            result.status = "budget_exceeded";
            return result;
        };
        let Some(destination) = bytes.get_mut(output_offset..destination_end) else {
            result.status = "budget_exceeded";
            return result;
        };
        let Some(covered) = present.get_mut(output_offset..destination_end) else {
            result.status = "budget_exceeded";
            return result;
        };
        for offset in 0..length {
            if covered[offset] {
                if destination[offset] != source[offset] && !conflicted[output_offset + offset] {
                    conflicted[output_offset + offset] = true;
                    result.conflicting_overlap_bytes += 1;
                }
            } else {
                destination[offset] = source[offset];
                covered[offset] = true;
            }
        }
        contributors.insert(fragment_index);
    }

    result.covered_ranges = ranges_for_presence(&present, expected.start, true);
    result.missing_ranges = ranges_for_presence(&present, expected.start, false);
    result.source_fragment_indexes = contributors.into_iter().collect();
    if result.conflicting_overlap_bytes > 0 {
        result.status = "conflicting_overlaps";
        return result;
    }
    if result.missing_ranges.len() > 0 {
        result.status = if result.covered_ranges.is_empty() {
            "unobserved"
        } else {
            "partially_covered"
        };
        return result;
    }

    let mut md5 = Md5::new();
    md5.update(&bytes);
    let mut crc32 = crc32fast::Hasher::new();
    crc32.update(&bytes);
    let mut padding_remaining = expected.padding;
    while padding_remaining > 0 {
        let count = usize::try_from(padding_remaining.min(ZEROES.len() as u64))
            .ok()
            .unwrap_or(0);
        if count == 0 {
            result.status = "budget_exceeded";
            return result;
        }
        let padding = &ZEROES[..count];
        md5.update(padding);
        crc32.update(padding);
        padding_remaining -= count as u64;
    }

    let observed_md5 = md5.finalize();
    let observed_crc32 = crc32.finalize();
    let md5_matches = observed_md5.as_slice() == &expected.md5[..];
    let crc32_matches = observed_crc32 == expected.crc32;
    result.observed_md5 = Some(hex(&observed_md5));
    result.observed_crc32 = Some(observed_crc32);
    result.md5_matches = Some(md5_matches);
    result.crc32_matches = Some(crc32_matches);
    result.checksum_valid = Some(md5_matches && crc32_matches);
    result.status = if md5_matches && crc32_matches {
        "resolved_checksum_valid"
    } else {
        "resolved_checksum_mismatch"
    };
    result
}

fn ranges_for_presence(present: &[bool], base: u64, wanted: bool) -> Vec<ByteRange> {
    let mut ranges = Vec::new();
    let mut index = 0usize;
    while index < present.len() {
        if present[index] != wanted {
            index += 1;
            continue;
        }
        let start = index;
        while index < present.len() && present[index] == wanted {
            index += 1;
        }
        if let (Ok(start_offset), Ok(end_offset)) = (u64::try_from(start), u64::try_from(index)) {
            if let (Some(start), Some(end_exclusive)) =
                (base.checked_add(start_offset), base.checked_add(end_offset))
            {
                ranges.push(ByteRange {
                    start,
                    end_exclusive,
                });
            }
        }
    }
    ranges
}

fn unresolved(
    declared_file_size: u64,
    slices: Vec<SliceResult>,
    status: &'static str,
) -> SourceSliceResolution {
    SourceSliceResolution {
        relationship: "par2_ifsc_slice_to_sampled_source_bytes",
        resolution_status: status,
        declared_file_size,
        slices,
    }
}

fn read_u32(input: &[u8], offset: usize) -> Option<u32> {
    Some(u32::from_le_bytes(
        input.get(offset..offset.checked_add(4)?)?.try_into().ok()?,
    ))
}

fn read_u64(input: &[u8], offset: usize) -> Option<u64> {
    Some(u64::from_le_bytes(
        input.get(offset..offset.checked_add(8)?)?.try_into().ok()?,
    ))
}

fn hex(bytes: &[u8]) -> String {
    const DIGITS: &[u8; 16] = b"0123456789abcdef";
    let mut output = String::with_capacity(bytes.len().saturating_mul(2));
    for byte in bytes {
        output.push(DIGITS[(byte >> 4) as usize] as char);
        output.push(DIGITS[(byte & 0x0f) as usize] as char);
    }
    output
}
