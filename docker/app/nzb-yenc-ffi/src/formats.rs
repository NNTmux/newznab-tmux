use serde::Serialize;

#[derive(Debug, Serialize)]
pub struct FormatMetadata {
    pub parser_engine: &'static str,
    pub candidate_scan_limit_per_format: usize,
    pub candidate_scan_truncated_formats: Vec<&'static str>,
    pub signatures: Vec<SignatureMatch>,
    pub headers: Vec<FormatHeader>,
}

#[derive(Debug, Serialize)]
pub struct SignatureMatch {
    pub format: &'static str,
    pub offset: usize,
    pub basis: &'static str,
}

#[derive(Debug, Serialize)]
#[serde(tag = "format", rename_all = "snake_case")]
pub enum FormatHeader {
    Rar4 {
        offset: usize,
        main_header_available: bool,
        main_header_type: Option<u8>,
        main_header_size: Option<u16>,
        main_header_checksum_valid: Option<bool>,
        main_header_flags: Option<u16>,
        volume: Option<bool>,
        comment: Option<bool>,
        locked: Option<bool>,
        solid: Option<bool>,
        authenticity_present: Option<bool>,
        recovery_record_present: Option<bool>,
        first_volume: Option<bool>,
        encrypted_headers: Option<bool>,
    },
    Rar5 {
        offset: usize,
        first_block_header_available: bool,
        first_block_header_checksum_valid: Option<bool>,
        first_block_type: Option<u64>,
        first_block_flags: Option<u64>,
        main_archive_flags: Option<u64>,
        main_is_volume: Option<bool>,
        main_is_solid: Option<bool>,
        main_has_recovery_record: Option<bool>,
        main_is_locked: Option<bool>,
        main_volume_number: Option<u64>,
        encryption_algorithm_version: Option<u64>,
        encryption_flags: Option<u64>,
        kdf_count_log2: Option<u8>,
        kdf_iteration_count: Option<u64>,
        header_encryption_salt_hex: Option<String>,
        password_check_present: Option<bool>,
        password_check_data_hex: Option<String>,
        first_block_header_size: Option<u64>,
        first_block_header_size_vint_bytes: Option<u8>,
        first_block_header_size_encoding_valid: Option<bool>,
        first_block_extra_size: Option<u64>,
        first_block_data_size: Option<u64>,
        main_extra_record_types: Vec<u64>,
        main_extra_records_complete: bool,
        main_locator_records: Vec<Rar5LocatorRecord>,
        main_archive_metadata_records: Vec<Rar5ArchiveMetadata>,
    },
    SevenZip {
        offset: usize,
        start_header_available: bool,
        major_version: u8,
        minor_version: u8,
        start_header_crc32: u32,
        calculated_start_header_crc32: u32,
        start_header_crc_valid: bool,
        next_header_offset: u64,
        next_header_size: u64,
        next_header_crc32: u32,
        header_range_within_sample: bool,
        next_header_checksum_valid: Option<bool>,
        next_header_type: Option<u8>,
        next_header_kind: Option<&'static str>,
        next_header_sha256: Option<String>,
    },
    Iso9660PrimaryVolumeDescriptor {
        offset: usize,
        version: u8,
        system_identifier: String,
        system_identifier_bytes_sha256: String,
        volume_identifier: String,
        volume_identifier_bytes_sha256: String,
        volume_space_blocks: Option<u32>,
        dual_endian_volume_space_match: Option<bool>,
        volume_space_bytes: Option<u64>,
        volume_set_size: Option<u16>,
        dual_endian_volume_set_size_match: Option<bool>,
        volume_sequence_number: Option<u16>,
        dual_endian_volume_sequence_number_match: Option<bool>,
        logical_block_size: Option<u16>,
        dual_endian_logical_block_size_match: Option<bool>,
        path_table_size: Option<u32>,
        dual_endian_path_table_size_match: Option<bool>,
        type_l_path_table_location: Option<u32>,
        type_l_path_table_offset_bytes: Option<u64>,
        optional_type_l_path_table_location: Option<u32>,
        optional_type_l_path_table_offset_bytes: Option<u64>,
        type_m_path_table_location: Option<u32>,
        type_m_path_table_offset_bytes: Option<u64>,
        optional_type_m_path_table_location: Option<u32>,
        optional_type_m_path_table_offset_bytes: Option<u64>,
        root_directory_record: IsoRootDirectoryRecord,
        volume_set_identifier: String,
        volume_set_identifier_bytes_sha256: String,
        publisher_identifier: String,
        preparer_identifier: String,
        application_identifier: String,
        copyright_file_identifier: String,
        abstract_file_identifier: String,
        bibliographic_file_identifier: String,
        volume_dates: IsoVolumeDates,
        file_structure_version: Option<u8>,
        application_use_sha256: String,
        application_use_prefix_hex: String,
    },
    Zip {
        offset: usize,
        local_file_header_signature: bool,
        local_header_available: bool,
        structure_valid: bool,
        version_needed: Option<u16>,
        flags: Option<u16>,
        compression_method: Option<u16>,
        modification_time_dos: Option<u16>,
        modification_date_dos: Option<u16>,
        crc32: Option<u32>,
        compressed_size: Option<u32>,
        uncompressed_size: Option<u32>,
        filename_bytes_hex: Option<String>,
        filename_bytes_sha256: Option<String>,
        filename_hex_truncated: Option<bool>,
        filename_utf8_valid: Option<bool>,
        filename_bytes_length: Option<usize>,
        filename_utf8_flag_set: Option<bool>,
        filename_encoding_hint: Option<&'static str>,
        extra_field_bytes_length: Option<usize>,
        extra_field_bytes_sha256: Option<String>,
        extra_field_prefix_hex: Option<String>,
        extra_field_hex_truncated: Option<bool>,
        zip64_extra_payload_length: Option<usize>,
        data_descriptor_follows: Option<bool>,
        zip64_extra_present: Option<bool>,
        zip64_extra_valid: Option<bool>,
        zip64_size_sentinel_present: Option<bool>,
    },
    Gzip {
        offset: usize,
        fixed_header_available: bool,
        structure_valid: bool,
        compression_method: Option<u8>,
        flags: Option<u8>,
        modification_time: Option<u32>,
        extra_flags: Option<u8>,
        operating_system: Option<u8>,
        extra_field_declared_length: Option<usize>,
        extra_field_bytes_length: Option<usize>,
        extra_field_bytes_sha256: Option<String>,
        extra_field_prefix_hex: Option<String>,
        extra_field_hex_truncated: Option<bool>,
        original_filename_bytes_hex: Option<String>,
        original_filename_bytes_length: Option<usize>,
        original_filename_bytes_sha256: Option<String>,
        original_filename_hex_truncated: Option<bool>,
        comment_bytes_hex: Option<String>,
        comment_bytes_length: Option<usize>,
        comment_bytes_sha256: Option<String>,
        comment_hex_truncated: Option<bool>,
        header_crc16_present: Option<bool>,
        header_crc16_valid: Option<bool>,
    },
    Xz {
        offset: usize,
        stream_header_available: bool,
        structure_valid: bool,
        stream_flags_hex: Option<String>,
        check_id: Option<u8>,
        check_name: Option<&'static str>,
        check_id_supported: Option<bool>,
        reserved_flags_valid: Option<bool>,
        header_crc32: Option<u32>,
        calculated_header_crc32: Option<u32>,
        header_crc32_valid: Option<bool>,
    },
}

#[derive(Debug, Serialize)]
pub struct IsoRootDirectoryRecord {
    pub available: bool,
    pub record_length: Option<u8>,
    pub extended_attribute_record_length: Option<u8>,
    pub extent_location: Option<u32>,
    pub extent_offset_bytes: Option<u64>,
    pub dual_endian_extent_location_match: Option<bool>,
    pub data_length: Option<u32>,
    pub dual_endian_data_length_match: Option<bool>,
    pub recording_date_bytes_hex: Option<String>,
    pub file_flags: Option<u8>,
    pub directory_flag_set: Option<bool>,
    pub reserved_flags_clear: Option<bool>,
    pub file_unit_size: Option<u8>,
    pub interleave_gap_size: Option<u8>,
    pub volume_sequence_number: Option<u16>,
    pub dual_endian_volume_sequence_number_match: Option<bool>,
    pub file_identifier_length: Option<u8>,
    pub file_identifier_hex: Option<String>,
    pub is_root_directory_identifier: Option<bool>,
    pub basic_structure_checks_pass: bool,
}

#[derive(Debug, Serialize)]
pub struct IsoVolumeDates {
    pub creation_raw_bytes_hex: String,
    pub creation_ascii_digits_valid: bool,
    pub creation_unspecified: bool,
    pub creation_timezone_quarters: Option<i8>,
    pub modification_raw_bytes_hex: String,
    pub modification_ascii_digits_valid: bool,
    pub modification_unspecified: bool,
    pub modification_timezone_quarters: Option<i8>,
    pub expiration_raw_bytes_hex: String,
    pub expiration_ascii_digits_valid: bool,
    pub expiration_unspecified: bool,
    pub expiration_timezone_quarters: Option<i8>,
    pub effective_raw_bytes_hex: String,
    pub effective_ascii_digits_valid: bool,
    pub effective_unspecified: bool,
    pub effective_timezone_quarters: Option<i8>,
}

#[derive(Debug, Serialize)]
pub struct Rar5LocatorRecord {
    pub record_size: u64,
    pub flags: Option<u64>,
    pub quick_open_offset_relative_to_main_header: Option<u64>,
    pub quick_open_target_sample_offset: Option<u64>,
    pub recovery_record_offset_relative_to_main_header: Option<u64>,
    pub recovery_record_target_sample_offset: Option<u64>,
    pub record_fields_complete: bool,
}

#[derive(Debug, Serialize)]
pub struct Rar5ArchiveMetadata {
    pub record_size: u64,
    pub flags: Option<u64>,
    pub archive_name: Option<String>,
    pub archive_name_bytes_hex: Option<String>,
    pub archive_name_bytes_length: Option<usize>,
    pub archive_name_bytes_sha256: Option<String>,
    pub archive_name_declared_length: Option<usize>,
    pub archive_name_buffer_sha256: Option<String>,
    pub archive_name_hex_truncated: Option<bool>,
    pub archive_name_display_truncated: Option<bool>,
    pub archive_name_utf8_valid: Option<bool>,
    pub archive_creation_time_raw: Option<u64>,
    pub archive_creation_time_encoding: Option<&'static str>,
    pub record_fields_complete: bool,
}

const MAX_RAR5_MAIN_EXTRA_RECORDS: usize = 4096;
const MAX_RAR5_ARCHIVE_NAME_DISPLAY_BYTES: usize = 4096;
const MAX_RAR5_ARCHIVE_NAME_HEX_BYTES: usize = 256;
const MAX_SIGNATURE_CANDIDATES_PER_FORMAT: usize = 16;
const MAX_GZIP_OPTIONAL_FIELD_BYTES: usize = 65_536;

const RAR4: &[u8] = b"Rar!\x1a\x07\x00";
const RAR5: &[u8] = b"Rar!\x1a\x07\x01\x00";
const SEVEN_ZIP: &[u8] = b"7z\xbc\xaf'\x1c";
const ZIP: &[u8] = b"PK\x03\x04";
const GZIP: &[u8] = b"\x1f\x8b\x08";
const XZ: &[u8] = b"\xfd7zXZ\0";
#[cfg(test)]
const ISO_PVD_OFFSET: usize = 32_768;

pub fn parse(data: &[u8]) -> FormatMetadata {
    let mut signatures = Vec::new();
    let mut headers = Vec::new();
    let mut candidate_scan_truncated_formats = Vec::new();

    for (format, signature) in [
        ("RAR 5", RAR5),
        ("RAR 4", RAR4),
        ("7-Zip", SEVEN_ZIP),
        ("ZIP", ZIP),
        ("gzip", GZIP),
        ("XZ", XZ),
    ] {
        let maximum_end = if matches!(format, "RAR 4" | "RAR 5") {
            1_048_576 + signature.len()
        } else {
            data.len()
        };
        let (offsets, truncated) = find_all_with_limit(
            data,
            signature,
            maximum_end,
            MAX_SIGNATURE_CANDIDATES_PER_FORMAT,
        );
        if truncated {
            candidate_scan_truncated_formats.push(format);
        }
        for offset in offsets {
            signatures.push(SignatureMatch {
                format,
                offset,
                basis: "decoded sample bytes",
            });
            match format {
                "RAR 4" => headers.push(parse_rar4(data, offset)),
                "RAR 5" => headers.push(parse_rar5(data, offset)),
                "7-Zip" => headers.push(parse_7z(data, offset)),
                "ZIP" => headers.push(parse_zip(data, offset)),
                "gzip" => headers.push(parse_gzip(data, offset)),
                "XZ" => headers.push(parse_xz(data, offset)),
                _ => {}
            }
        }
    }

    if let Some(offset) = find_iso_pvd(data) {
        signatures.push(SignatureMatch {
            format: "ISO 9660 primary volume descriptor",
            offset,
            basis: "type-1 descriptor with CD001 identifier and version 1 in the bounded descriptor sequence; detailed field validity is reported separately",
        });
        headers.push(parse_iso_pvd(data, offset));
    }

    FormatMetadata {
        parser_engine: "nntmux-rust-ffi",
        candidate_scan_limit_per_format: MAX_SIGNATURE_CANDIDATES_PER_FORMAT,
        candidate_scan_truncated_formats,
        signatures,
        headers,
    }
}

fn parse_rar4(data: &[u8], offset: usize) -> FormatHeader {
    let header = offset + RAR4.len();
    let header_size = data
        .get(header + 5..header + 7)
        .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]]));
    let header_end = header_size.and_then(|size| header.checked_add(usize::from(size)));
    let full_header = header_end.and_then(|end| data.get(header..end));
    let header_type = data.get(header + 2).copied();
    let checksum_valid = full_header.and_then(|block| {
        if block.len() < 13 || header_type != Some(0x73) {
            return None;
        }
        let expected = u16::from_le_bytes([block[0], block[1]]);
        let flags = u16::from_le_bytes([block[3], block[4]]);
        // Legacy embedded comments are excluded from the main-header CRC.
        // Other main headers checksum all bytes declared by HEAD_SIZE.
        let checksum_end = if flags & 0x0002 != 0 { 13 } else { block.len() };
        Some(crc32fast::hash(&block[2..checksum_end]) as u16 == expected)
    });
    let flags = full_header
        .filter(|block| block.len() >= 13 && header_type == Some(0x73))
        .map(|bytes| u16::from_le_bytes([bytes[3], bytes[4]]));
    let main_header_available =
        full_header.is_some_and(|block| block.len() >= 13 && header_type == Some(0x73));
    FormatHeader::Rar4 {
        offset,
        main_header_available,
        main_header_type: header_type,
        main_header_size: header_size,
        main_header_checksum_valid: checksum_valid,
        main_header_flags: flags,
        volume: flags.map(|value| value & 0x0001 != 0),
        comment: flags.map(|value| value & 0x0002 != 0),
        locked: flags.map(|value| value & 0x0004 != 0),
        solid: flags.map(|value| value & 0x0008 != 0),
        authenticity_present: flags.map(|value| value & 0x0020 != 0),
        recovery_record_present: flags.map(|value| value & 0x0040 != 0),
        first_volume: flags.map(|value| value & 0x0100 != 0),
        encrypted_headers: flags.map(|value| value & 0x0080 != 0),
    }
}

fn parse_rar5(data: &[u8], offset: usize) -> FormatHeader {
    let block_start = offset + RAR5.len();
    let Some(crc_bytes) = data.get(block_start..block_start + 4) else {
        return empty_rar5(offset, None, None, None);
    };
    let expected_crc = u32::from_le_bytes(crc_bytes.try_into().expect("four-byte CRC"));
    let mut cursor = block_start + 4;
    let Some((header_size, header_size_vint_bytes)) = read_vint_with_len(data, &mut cursor) else {
        return empty_rar5(offset, None, None, None);
    };
    // The RAR5 specification limits this vint to three bytes (2 MiB maximum).
    let header_size_encoding_valid = header_size_vint_bytes <= 3 && header_size <= 0x1f_ffff;
    if !header_size_encoding_valid {
        return empty_rar5(
            offset,
            Some(header_size),
            Some(header_size_vint_bytes),
            Some(false),
        );
    }
    let Ok(header_size_usize) = usize::try_from(header_size) else {
        return empty_rar5(
            offset,
            Some(header_size),
            Some(header_size_vint_bytes),
            Some(true),
        );
    };
    let header_end = match cursor.checked_add(header_size_usize) {
        Some(end) if end <= data.len() => end,
        _ => {
            return empty_rar5(
                offset,
                Some(header_size),
                Some(header_size_vint_bytes),
                Some(true),
            );
        }
    };
    let calculated_crc = crc32fast::hash(&data[block_start + 4..header_end]);
    let Some(block_type) = read_vint_bounded(data, &mut cursor, header_end) else {
        return empty_rar5(
            offset,
            Some(header_size),
            Some(header_size_vint_bytes),
            Some(true),
        );
    };
    let Some(block_flags) = read_vint_bounded(data, &mut cursor, header_end) else {
        return empty_rar5(
            offset,
            Some(header_size),
            Some(header_size_vint_bytes),
            Some(true),
        );
    };
    let extra_size = if block_flags & 0x01 != 0 {
        read_vint_bounded(data, &mut cursor, header_end)
    } else {
        Some(0)
    };
    let data_size = if block_flags & 0x02 != 0 {
        read_vint_bounded(data, &mut cursor, header_end)
    } else {
        Some(0)
    };
    // RAR5 stores typed header fields before the optional extra area. Keeping
    // that boundary explicit prevents extra-record bytes from satisfying
    // required fields in a truncated typed header.
    let extra_area_start = extra_size
        .and_then(|size| usize::try_from(size).ok())
        .and_then(|size| header_end.checked_sub(size));
    let extra_area_valid = extra_area_start.is_some_and(|start| start >= cursor);
    let typed_header_end = extra_area_start
        .filter(|start| *start >= cursor)
        .unwrap_or(cursor);
    let mut main_archive_flags = None;
    let mut main_volume_number = None;
    let mut algorithm_version = None;
    let mut encryption_flags = None;
    let mut kdf_count = None;
    let mut salt = None;
    let mut password_check_data = None;
    let mut main_extra_record_types = Vec::new();
    let mut main_extra_records_complete = extra_size.is_some() && extra_area_valid;
    let mut main_locator_records = Vec::new();
    let mut main_archive_metadata_records = Vec::new();
    let mut typed_header_complete = extra_size.is_some() && data_size.is_some() && extra_area_valid;

    match block_type {
        1 => {
            main_archive_flags = read_vint_bounded(data, &mut cursor, typed_header_end);
            typed_header_complete &= main_archive_flags.is_some();
            if main_archive_flags.is_some_and(|flags| flags & 0x02 != 0) {
                main_volume_number = read_vint_bounded(data, &mut cursor, typed_header_end);
                typed_header_complete &= main_volume_number.is_some();
            }
            if extra_area_valid {
                (
                    main_extra_record_types,
                    main_locator_records,
                    main_archive_metadata_records,
                    main_extra_records_complete,
                ) = parse_rar5_main_extra_records(data, typed_header_end, header_end, block_start);
            }
        }
        4 => {
            algorithm_version = read_vint_bounded(data, &mut cursor, typed_header_end);
            encryption_flags = read_vint_bounded(data, &mut cursor, typed_header_end);
            if cursor < typed_header_end {
                kdf_count = data.get(cursor).copied();
                cursor = cursor.saturating_add(1);
            }
            if cursor.saturating_add(16) <= typed_header_end {
                salt = Some(hex(&data[cursor..cursor + 16]));
                cursor += 16;
            }
            if encryption_flags.is_some_and(|flags| flags & 0x01 != 0)
                && cursor.saturating_add(12) <= typed_header_end
            {
                password_check_data = Some(hex(&data[cursor..cursor + 12]));
            }
            typed_header_complete &= algorithm_version.is_some()
                && encryption_flags.is_some()
                && kdf_count.is_some()
                && salt.is_some()
                && (encryption_flags.is_none_or(|flags| flags & 0x01 == 0)
                    || password_check_data.is_some());
        }
        _ => {}
    }
    let calculated_crc_is_valid = expected_crc == calculated_crc;

    FormatHeader::Rar5 {
        offset,
        first_block_header_available: typed_header_complete,
        first_block_header_checksum_valid: Some(calculated_crc_is_valid),
        first_block_type: Some(block_type),
        first_block_flags: Some(block_flags),
        main_archive_flags,
        main_is_volume: main_archive_flags.map(|flags| flags & 0x01 != 0),
        main_is_solid: main_archive_flags.map(|flags| flags & 0x04 != 0),
        main_has_recovery_record: main_archive_flags.map(|flags| flags & 0x08 != 0),
        main_is_locked: main_archive_flags.map(|flags| flags & 0x10 != 0),
        main_volume_number,
        encryption_algorithm_version: algorithm_version,
        encryption_flags,
        kdf_count_log2: kdf_count,
        kdf_iteration_count: kdf_count.and_then(|count| 1u64.checked_shl(u32::from(count))),
        header_encryption_salt_hex: salt,
        password_check_present: encryption_flags.map(|flags| flags & 0x01 != 0),
        password_check_data_hex: password_check_data,
        first_block_header_size: Some(header_size),
        first_block_header_size_vint_bytes: Some(header_size_vint_bytes),
        first_block_header_size_encoding_valid: Some(true),
        first_block_extra_size: extra_size,
        first_block_data_size: data_size,
        main_extra_record_types,
        main_extra_records_complete,
        main_locator_records,
        main_archive_metadata_records,
    }
}

fn empty_rar5(
    offset: usize,
    header_size: Option<u64>,
    header_size_vint_bytes: Option<u8>,
    header_size_encoding_valid: Option<bool>,
) -> FormatHeader {
    FormatHeader::Rar5 {
        offset,
        first_block_header_available: false,
        first_block_header_checksum_valid: None,
        first_block_type: None,
        first_block_flags: None,
        main_archive_flags: None,
        main_is_volume: None,
        main_is_solid: None,
        main_has_recovery_record: None,
        main_is_locked: None,
        main_volume_number: None,
        encryption_algorithm_version: None,
        encryption_flags: None,
        kdf_count_log2: None,
        kdf_iteration_count: None,
        header_encryption_salt_hex: None,
        password_check_present: None,
        password_check_data_hex: None,
        first_block_header_size: header_size,
        first_block_header_size_vint_bytes: header_size_vint_bytes,
        first_block_header_size_encoding_valid: header_size_encoding_valid,
        first_block_extra_size: None,
        first_block_data_size: None,
        main_extra_record_types: Vec::new(),
        main_extra_records_complete: false,
        main_locator_records: Vec::new(),
        main_archive_metadata_records: Vec::new(),
    }
}

fn parse_rar5_main_extra_records(
    data: &[u8],
    start: usize,
    end: usize,
    main_header_offset: usize,
) -> (
    Vec<u64>,
    Vec<Rar5LocatorRecord>,
    Vec<Rar5ArchiveMetadata>,
    bool,
) {
    let mut cursor = start;
    let mut record_types = Vec::new();
    let mut locator_records = Vec::new();
    let mut metadata_records = Vec::new();
    let mut complete = true;

    while cursor < end && record_types.len() < MAX_RAR5_MAIN_EXTRA_RECORDS {
        let Some(record_size) = read_vint_bounded(data, &mut cursor, end) else {
            complete = false;
            break;
        };
        let Some(record_end) = usize::try_from(record_size)
            .ok()
            .and_then(|size| cursor.checked_add(size))
            .filter(|record_end| *record_end <= end && *record_end > cursor)
        else {
            complete = false;
            break;
        };
        let Some(record_type) = read_vint_bounded(data, &mut cursor, record_end) else {
            complete = false;
            break;
        };
        record_types.push(record_type);
        match record_type {
            1 => {
                let locator = parse_rar5_locator_record(
                    data,
                    cursor,
                    record_end,
                    record_size,
                    main_header_offset,
                );
                complete &= locator.record_fields_complete;
                locator_records.push(locator);
            }
            2 => {
                let metadata = parse_rar5_archive_metadata(data, cursor, record_end, record_size);
                complete &= metadata.record_fields_complete;
                metadata_records.push(metadata);
            }
            _ => {}
        }
        cursor = record_end;
    }
    if cursor < end {
        complete = false;
    }

    (record_types, locator_records, metadata_records, complete)
}

fn parse_rar5_locator_record(
    data: &[u8],
    mut cursor: usize,
    end: usize,
    record_size: u64,
    main_header_offset: usize,
) -> Rar5LocatorRecord {
    let flags = read_vint_bounded(data, &mut cursor, end);
    let quick_open_offset = match flags.filter(|value| value & 0x01 != 0) {
        Some(_) => read_vint_bounded(data, &mut cursor, end),
        None => None,
    };
    let recovery_record_offset = match flags.filter(|value| value & 0x02 != 0) {
        Some(_) if quick_open_offset.is_some() || flags.is_some_and(|value| value & 0x01 == 0) => {
            read_vint_bounded(data, &mut cursor, end)
        }
        _ => None,
    };
    let record_fields_complete = flags.is_some_and(|value| value & !0x03 == 0)
        && (flags.is_none_or(|value| value & 0x01 == 0) || quick_open_offset.is_some())
        && (flags.is_none_or(|value| value & 0x02 == 0) || recovery_record_offset.is_some())
        && cursor == end;
    let sample_target = |offset: Option<u64>| {
        offset
            .filter(|value| *value > 0)
            .and_then(|value| u64::try_from(main_header_offset).ok()?.checked_add(value))
    };

    Rar5LocatorRecord {
        record_size,
        flags,
        quick_open_offset_relative_to_main_header: quick_open_offset,
        quick_open_target_sample_offset: sample_target(quick_open_offset),
        recovery_record_offset_relative_to_main_header: recovery_record_offset,
        recovery_record_target_sample_offset: sample_target(recovery_record_offset),
        record_fields_complete,
    }
}

fn parse_rar5_archive_metadata(
    data: &[u8],
    mut cursor: usize,
    end: usize,
    record_size: u64,
) -> Rar5ArchiveMetadata {
    let Some(flags) = read_vint_bounded(data, &mut cursor, end) else {
        return Rar5ArchiveMetadata {
            record_size,
            flags: None,
            archive_name: None,
            archive_name_bytes_hex: None,
            archive_name_bytes_length: None,
            archive_name_bytes_sha256: None,
            archive_name_declared_length: None,
            archive_name_buffer_sha256: None,
            archive_name_hex_truncated: None,
            archive_name_display_truncated: None,
            archive_name_utf8_valid: None,
            archive_creation_time_raw: None,
            archive_creation_time_encoding: None,
            record_fields_complete: false,
        };
    };

    let mut archive_name = None;
    let mut archive_name_bytes_hex = None;
    let mut archive_name_bytes_length = None;
    let mut archive_name_bytes_sha256 = None;
    let mut archive_name_declared_length = None;
    let mut archive_name_buffer_sha256 = None;
    let mut archive_name_hex_truncated = None;
    let mut archive_name_display_truncated = None;
    let mut archive_name_utf8_valid = None;
    let mut archive_creation_time_raw = None;
    let mut archive_creation_time_encoding = None;
    let mut complete = flags & !0x0f == 0;
    if flags & 0x08 != 0 && flags & 0x04 == 0 {
        complete = false;
    }
    let mut fields_aligned = true;

    if flags & 0x01 != 0 {
        if let Some(name_length) = read_vint_bounded(data, &mut cursor, end) {
            if let Some(name_end) = usize::try_from(name_length)
                .ok()
                .and_then(|length| cursor.checked_add(length))
                .filter(|name_end| *name_end <= end)
            {
                let raw_name = &data[cursor..name_end];
                let name_end_at_nul = raw_name
                    .iter()
                    .position(|byte| *byte == 0)
                    .unwrap_or(raw_name.len());
                let name_bytes = &raw_name[..name_end_at_nul];
                archive_name_bytes_hex = Some(hex(
                    &name_bytes[..name_bytes.len().min(MAX_RAR5_ARCHIVE_NAME_HEX_BYTES)]
                ));
                archive_name_bytes_length = Some(name_bytes.len());
                archive_name_bytes_sha256 = Some(sha256_hex(name_bytes));
                archive_name_declared_length = Some(raw_name.len());
                archive_name_buffer_sha256 = Some(sha256_hex(raw_name));
                archive_name_hex_truncated =
                    Some(name_bytes.len() > MAX_RAR5_ARCHIVE_NAME_HEX_BYTES);
                archive_name_display_truncated =
                    Some(name_bytes.len() > MAX_RAR5_ARCHIVE_NAME_DISPLAY_BYTES);
                archive_name_utf8_valid = Some(std::str::from_utf8(name_bytes).is_ok());
                if raw_name.first() != Some(&0) {
                    archive_name = Some(
                        String::from_utf8_lossy(
                            &name_bytes
                                [..name_bytes.len().min(MAX_RAR5_ARCHIVE_NAME_DISPLAY_BYTES)],
                        )
                        .into_owned(),
                    );
                }
                cursor = name_end;
            } else {
                complete = false;
                fields_aligned = false;
            }
        } else {
            complete = false;
            fields_aligned = false;
        }
    }

    if flags & 0x02 != 0 && fields_aligned {
        let (time_size, encoding) = if flags & 0x04 != 0 {
            if flags & 0x08 != 0 {
                (8, "unix_nanoseconds")
            } else {
                (4, "unix_seconds")
            }
        } else {
            (8, "windows_filetime_100ns_since_1601")
        };
        if let Some(time_end) = cursor
            .checked_add(time_size)
            .filter(|time_end| *time_end <= end)
        {
            let time_bytes = &data[cursor..time_end];
            archive_creation_time_raw = Some(if time_size == 4 {
                u64::from(u32::from_le_bytes(
                    time_bytes.try_into().expect("four-byte time"),
                ))
            } else {
                u64::from_le_bytes(time_bytes.try_into().expect("eight-byte time"))
            });
            archive_creation_time_encoding = Some(encoding);
        } else {
            complete = false;
        }
    }

    Rar5ArchiveMetadata {
        record_size,
        flags: Some(flags),
        archive_name,
        archive_name_bytes_hex,
        archive_name_bytes_length,
        archive_name_bytes_sha256,
        archive_name_declared_length,
        archive_name_buffer_sha256,
        archive_name_hex_truncated,
        archive_name_display_truncated,
        archive_name_utf8_valid,
        archive_creation_time_raw,
        archive_creation_time_encoding,
        record_fields_complete: complete,
    }
}

fn read_vint(data: &[u8], cursor: &mut usize) -> Option<u64> {
    read_vint_with_len(data, cursor).map(|(value, _)| value)
}

fn read_vint_with_len(data: &[u8], cursor: &mut usize) -> Option<(u64, u8)> {
    let mut value = 0u64;
    for (index, shift) in (0..70).step_by(7).enumerate() {
        let byte = *data.get(*cursor)?;
        *cursor = cursor.checked_add(1)?;
        let part = byte & 0x7f;
        if shift == 63 && part > 1 {
            return None;
        }
        value |= u64::from(part) << shift;
        if byte & 0x80 == 0 {
            return Some((value, u8::try_from(index + 1).ok()?));
        }
    }
    None
}

fn read_vint_bounded(data: &[u8], cursor: &mut usize, end: usize) -> Option<u64> {
    if *cursor >= end {
        return None;
    }
    let bounded_data = data.get(..end)?;
    read_vint(bounded_data, cursor)
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
    use sha2::{Digest, Sha256};

    let digest = Sha256::digest(bytes);
    hex(&digest)
}

fn parse_zip(data: &[u8], offset: usize) -> FormatHeader {
    let header_start = offset.checked_add(ZIP.len());
    let fixed_header =
        header_start.and_then(|start| start.checked_add(26).and_then(|end| data.get(start..end)));
    let version_needed = fixed_header.map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]]));
    let flags = fixed_header.map(|bytes| u16::from_le_bytes([bytes[2], bytes[3]]));
    let compression_method = fixed_header.map(|bytes| u16::from_le_bytes([bytes[4], bytes[5]]));
    let modification_time_dos = fixed_header.map(|bytes| u16::from_le_bytes([bytes[6], bytes[7]]));
    let modification_date_dos = fixed_header.map(|bytes| u16::from_le_bytes([bytes[8], bytes[9]]));
    let crc32 = fixed_header.map(|bytes| le_u32(bytes, 10));
    let compressed_size = fixed_header.map(|bytes| le_u32(bytes, 14));
    let uncompressed_size = fixed_header.map(|bytes| le_u32(bytes, 18));
    let filename_length =
        fixed_header.map(|bytes| u16::from_le_bytes([bytes[22], bytes[23]]) as usize);
    let extra_length =
        fixed_header.map(|bytes| u16::from_le_bytes([bytes[24], bytes[25]]) as usize);
    let variable_fields = header_start
        .and_then(|start| start.checked_add(26))
        .zip(filename_length)
        .zip(extra_length)
        .and_then(|((start, filename_len), extra_len)| {
            let filename_end = start.checked_add(filename_len)?;
            let extra_end = filename_end.checked_add(extra_len)?;
            Some((
                data.get(start..filename_end)?,
                data.get(filename_end..extra_end)?,
            ))
        });
    let filename = variable_fields.map(|(name, _)| name);
    let extra = variable_fields.map(|(_, extra)| extra);
    let zip64_payload = extra.and_then(inspect_zip_extra_fields);
    let zip64_extra_present = zip64_payload.as_ref().map(Option::is_some);
    let zip64_extra_payload_length = zip64_payload
        .as_ref()
        .and_then(|payload| payload.map(<[u8]>::len));
    let zip64_size_sentinel_present = compressed_size
        .zip(uncompressed_size)
        .map(|(compressed, uncompressed)| compressed == u32::MAX || uncompressed == u32::MAX);
    let zip64_extra_valid = zip64_size_sentinel_present.zip(zip64_payload.as_ref()).map(
        |(has_size_sentinel, payload)| {
            if !has_size_sentinel {
                return true;
            }
            let Some(payload) = payload else {
                return false;
            };
            let required_size_bytes = usize::from(uncompressed_size == Some(u32::MAX)) * 8
                + usize::from(compressed_size == Some(u32::MAX)) * 8;
            payload.len() >= required_size_bytes
        },
    );
    let local_header_available = fixed_header.is_some();
    let structure_valid = local_header_available
        && variable_fields.is_some()
        && zip64_extra_present.is_some()
        && zip64_extra_valid == Some(true);

    FormatHeader::Zip {
        offset,
        local_file_header_signature: true,
        local_header_available,
        structure_valid,
        version_needed,
        flags,
        compression_method,
        modification_time_dos,
        modification_date_dos,
        crc32,
        compressed_size,
        uncompressed_size,
        filename_bytes_hex: filename.map(|bytes| hex(&bytes[..bytes.len().min(256)])),
        filename_bytes_sha256: filename.map(sha256_hex),
        filename_hex_truncated: filename.map(|bytes| bytes.len() > 256),
        filename_utf8_valid: filename.map(|bytes| std::str::from_utf8(bytes).is_ok()),
        filename_bytes_length: filename.map(<[u8]>::len),
        filename_utf8_flag_set: flags.map(|value| value & 0x0800 != 0),
        filename_encoding_hint: flags.map(|value| {
            if value & 0x0800 != 0 {
                "utf8"
            } else {
                "cp437_or_legacy"
            }
        }),
        extra_field_bytes_length: extra.map(<[u8]>::len),
        extra_field_bytes_sha256: extra.map(sha256_hex),
        extra_field_prefix_hex: extra.map(|bytes| hex(&bytes[..bytes.len().min(128)])),
        extra_field_hex_truncated: extra.map(|bytes| bytes.len() > 128),
        zip64_extra_payload_length,
        data_descriptor_follows: flags.map(|value| value & 0x0008 != 0),
        zip64_extra_present,
        zip64_extra_valid,
        zip64_size_sentinel_present,
    }
}

fn inspect_zip_extra_fields(mut extra: &[u8]) -> Option<Option<&[u8]>> {
    let mut zip64_payload = None;
    while !extra.is_empty() {
        let header = extra.get(..4)?;
        let header_id = u16::from_le_bytes([header[0], header[1]]);
        let data_size = u16::from_le_bytes([header[2], header[3]]) as usize;
        let record_end = 4usize.checked_add(data_size)?;
        let payload = extra.get(4..record_end)?;
        if header_id == 0x0001 {
            if zip64_payload.is_some() {
                return None;
            }
            zip64_payload = Some(payload);
        }
        extra = extra.get(record_end..)?;
    }
    Some(zip64_payload)
}

fn parse_gzip(data: &[u8], offset: usize) -> FormatHeader {
    let fixed = offset.checked_add(10).and_then(|end| data.get(offset..end));
    let compression_method = fixed.and_then(|bytes| bytes.get(2).copied());
    let flags = fixed.and_then(|bytes| bytes.get(3).copied());
    let modification_time = fixed.map(|bytes| le_u32(bytes, 4));
    let extra_flags = fixed.and_then(|bytes| bytes.get(8).copied());
    let operating_system = fixed.and_then(|bytes| bytes.get(9).copied());
    let mut cursor = offset.saturating_add(10);
    let mut optional_fields_valid = fixed.is_some();
    let mut extra_field_declared_length = None;
    let mut extra_field = None;
    let mut original_filename = None;
    let mut comment = None;
    if let Some(flags_value) = flags {
        if flags_value & 0x04 != 0 {
            if let Some(length_bytes) = data.get(cursor..cursor.saturating_add(2)) {
                let length = u16::from_le_bytes([length_bytes[0], length_bytes[1]]) as usize;
                extra_field_declared_length = Some(length);
                cursor = cursor.saturating_add(2);
                if length <= MAX_GZIP_OPTIONAL_FIELD_BYTES {
                    if let Some(extra_end) =
                        cursor.checked_add(length).filter(|end| *end <= data.len())
                    {
                        extra_field = data.get(cursor..extra_end);
                        cursor = extra_end;
                    } else {
                        optional_fields_valid = false;
                    }
                } else {
                    optional_fields_valid = false;
                }
            } else {
                optional_fields_valid = false;
            }
        }
        if optional_fields_valid && flags_value & 0x08 != 0 {
            match take_nul_terminated(data, cursor, MAX_GZIP_OPTIONAL_FIELD_BYTES) {
                Some((field, next)) => {
                    original_filename = Some(field);
                    cursor = next;
                }
                None => optional_fields_valid = false,
            }
        }
        if optional_fields_valid && flags_value & 0x10 != 0 {
            match take_nul_terminated(data, cursor, MAX_GZIP_OPTIONAL_FIELD_BYTES) {
                Some((field, next)) => {
                    comment = Some(field);
                    cursor = next;
                }
                None => optional_fields_valid = false,
            }
        }
    }
    let header_crc16_present = flags.map(|value| value & 0x02 != 0);
    let header_crc16_valid = if header_crc16_present == Some(true) && optional_fields_valid {
        let checksum_bytes = data.get(cursor..cursor.saturating_add(2));
        checksum_bytes.map(|bytes| {
            let expected = u16::from_le_bytes([bytes[0], bytes[1]]);
            let calculated = crc32fast::hash(&data[offset..cursor]) as u16;
            expected == calculated
        })
    } else {
        None
    };
    if header_crc16_present == Some(true) && header_crc16_valid.is_none() {
        optional_fields_valid = false;
    }
    let reserved_flags_valid = flags.is_some_and(|value| value & 0xe0 == 0);

    FormatHeader::Gzip {
        offset,
        fixed_header_available: fixed.is_some(),
        structure_valid: fixed.is_some()
            && optional_fields_valid
            && compression_method == Some(8)
            && reserved_flags_valid,
        compression_method,
        flags,
        modification_time,
        extra_flags,
        operating_system,
        extra_field_declared_length,
        extra_field_bytes_length: extra_field.map(<[u8]>::len),
        extra_field_bytes_sha256: extra_field.map(sha256_hex),
        extra_field_prefix_hex: extra_field.map(|bytes| hex(&bytes[..bytes.len().min(128)])),
        extra_field_hex_truncated: extra_field.map(|bytes| bytes.len() > 128),
        original_filename_bytes_hex: original_filename
            .map(|bytes| hex(&bytes[..bytes.len().min(256)])),
        original_filename_bytes_length: original_filename.map(<[u8]>::len),
        original_filename_bytes_sha256: original_filename.map(sha256_hex),
        original_filename_hex_truncated: original_filename.map(|bytes| bytes.len() > 256),
        comment_bytes_hex: comment.map(|bytes| hex(&bytes[..bytes.len().min(256)])),
        comment_bytes_length: comment.map(<[u8]>::len),
        comment_bytes_sha256: comment.map(sha256_hex),
        comment_hex_truncated: comment.map(|bytes| bytes.len() > 256),
        header_crc16_present,
        header_crc16_valid,
    }
}

fn take_nul_terminated(data: &[u8], start: usize, maximum: usize) -> Option<(&[u8], usize)> {
    let end = start.checked_add(maximum)?.min(data.len());
    let relative_end = data.get(start..end)?.iter().position(|byte| *byte == 0)?;
    let field_end = start.checked_add(relative_end)?;
    Some((data.get(start..field_end)?, field_end.checked_add(1)?))
}

fn parse_xz(data: &[u8], offset: usize) -> FormatHeader {
    let header = offset.checked_add(12).and_then(|end| data.get(offset..end));
    let stream_flags = header.and_then(|bytes| bytes.get(6..8));
    let stream_flags_hex = stream_flags.map(hex);
    let check_id = stream_flags.and_then(|bytes| bytes.get(1).copied());
    let check_name = check_id.and_then(|id| match id {
        0 => Some("none"),
        1 => Some("crc32"),
        4 => Some("crc64"),
        10 => Some("sha256"),
        _ => None,
    });
    let reserved_flags_valid = stream_flags.map(|bytes| bytes[0] == 0 && bytes[1] & 0xf0 == 0);
    let check_id_supported = check_id.map(|id| check_name.is_some() && id <= 15);
    let header_crc32 = header.map(|bytes| le_u32(bytes, 8));
    let calculated_header_crc32 = stream_flags.map(crc32fast::hash);
    let header_crc32_valid = header_crc32
        .zip(calculated_header_crc32)
        .map(|(expected, calculated)| expected == calculated);

    FormatHeader::Xz {
        offset,
        stream_header_available: header.is_some(),
        structure_valid: header.is_some() && reserved_flags_valid == Some(true),
        stream_flags_hex,
        check_id,
        check_name,
        check_id_supported,
        reserved_flags_valid,
        header_crc32,
        calculated_header_crc32,
        header_crc32_valid,
    }
}

fn parse_7z(data: &[u8], offset: usize) -> FormatHeader {
    let start = offset + SEVEN_ZIP.len();
    let header = start.checked_add(26).and_then(|end| data.get(start..end));
    let major_version = header
        .and_then(|bytes| bytes.first())
        .copied()
        .unwrap_or_default();
    let minor_version = header
        .and_then(|bytes| bytes.get(1))
        .copied()
        .unwrap_or_default();
    let start_crc = header.map(|bytes| le_u32(bytes, 2)).unwrap_or_default();
    let next_header_offset = header.map(|bytes| le_u64(bytes, 6)).unwrap_or_default();
    let next_header_size = header.map(|bytes| le_u64(bytes, 14)).unwrap_or_default();
    let next_header_crc = header.map(|bytes| le_u32(bytes, 22)).unwrap_or_default();
    let calculated_start_header_crc = header
        .map(|bytes| crc32fast::hash(&bytes[6..26]))
        .unwrap_or_default();
    let archive_start = u64::try_from(offset).ok();
    let header_range_within_sample = next_header_offset
        .checked_add(next_header_size)
        .and_then(|range_end| {
            archive_start.map(|base| base.checked_add(32)?.checked_add(range_end))
        })
        .flatten()
        .is_some_and(|end| end <= data.len() as u64);
    let next_header = if header.is_some() && header_range_within_sample {
        archive_start
            .and_then(|base| base.checked_add(32))
            .and_then(|base| base.checked_add(next_header_offset))
            .and_then(|start| usize::try_from(start).ok())
            .zip(usize::try_from(next_header_size).ok())
            .and_then(|(start, size)| data.get(start..start.checked_add(size)?))
    } else {
        None
    };
    let next_header_checksum_valid =
        next_header.map(|bytes| crc32fast::hash(bytes) == next_header_crc);
    let next_header_type = next_header.and_then(|bytes| bytes.first().copied());
    let next_header_kind = next_header_type.map(|header_type| match header_type {
        0x01 => "plain_header",
        0x17 => "encoded_header",
        _ => "other_or_invalid",
    });
    let next_header_sha256 = next_header.map(sha256_hex);
    FormatHeader::SevenZip {
        offset,
        start_header_available: header.is_some(),
        major_version,
        minor_version,
        start_header_crc32: start_crc,
        calculated_start_header_crc32: calculated_start_header_crc,
        start_header_crc_valid: header.is_some() && start_crc == calculated_start_header_crc,
        next_header_offset,
        next_header_size,
        next_header_crc32: next_header_crc,
        header_range_within_sample,
        next_header_checksum_valid,
        next_header_type,
        next_header_kind,
        next_header_sha256,
    }
}

fn find_iso_pvd(data: &[u8]) -> Option<usize> {
    for sector in 16usize..256 {
        let offset = sector * 2048;
        let Some(descriptor) = data.get(offset..offset.saturating_add(2048)) else {
            break;
        };
        if &descriptor[1..6] != b"CD001" {
            return None;
        }
        match descriptor[0] {
            1 if descriptor[6] == 1 => return Some(offset),
            2 if matches!(descriptor[6], 1 | 2) => {}
            0 | 3 if descriptor[6] == 1 => {}
            255 if descriptor[6] == 1 => return None,
            _ => return None,
        }
    }
    None
}

fn parse_iso_pvd(data: &[u8], offset: usize) -> FormatHeader {
    let pvd = &data[offset..offset + 2048];
    FormatHeader::Iso9660PrimaryVolumeDescriptor {
        offset,
        version: pvd.get(6).copied().unwrap_or_default(),
        system_identifier: ascii_field(pvd, 8, 32),
        system_identifier_bytes_sha256: sha256_hex(pvd.get(8..40).unwrap_or_default()),
        volume_identifier: ascii_field(pvd, 40, 32),
        volume_identifier_bytes_sha256: sha256_hex(pvd.get(40..72).unwrap_or_default()),
        volume_space_blocks: pvd
            .get(80..84)
            .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
        dual_endian_volume_space_match: pvd.get(80..88).map(|bytes| {
            u32::from_le_bytes(bytes[0..4].try_into().expect("four-byte ISO field"))
                == u32::from_be_bytes(bytes[4..8].try_into().expect("four-byte ISO field"))
        }),
        volume_space_bytes: iso_block_offset(
            pvd.get(80..84)
                .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
            pvd.get(128..130)
                .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        ),
        volume_set_size: pvd
            .get(120..122)
            .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        dual_endian_volume_set_size_match: pvd.get(120..124).map(|bytes| {
            u16::from_le_bytes([bytes[0], bytes[1]]) == u16::from_be_bytes([bytes[2], bytes[3]])
        }),
        volume_sequence_number: pvd
            .get(124..126)
            .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        dual_endian_volume_sequence_number_match: pvd.get(124..128).map(|bytes| {
            u16::from_le_bytes([bytes[0], bytes[1]]) == u16::from_be_bytes([bytes[2], bytes[3]])
        }),
        logical_block_size: pvd
            .get(128..130)
            .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        dual_endian_logical_block_size_match: pvd.get(128..132).map(|bytes| {
            u16::from_le_bytes([bytes[0], bytes[1]]) == u16::from_be_bytes([bytes[2], bytes[3]])
        }),
        path_table_size: pvd
            .get(132..136)
            .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
        dual_endian_path_table_size_match: pvd.get(132..140).map(|bytes| {
            u32::from_le_bytes(bytes[0..4].try_into().expect("four-byte ISO field"))
                == u32::from_be_bytes(bytes[4..8].try_into().expect("four-byte ISO field"))
        }),
        type_l_path_table_location: pvd
            .get(140..144)
            .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
        type_l_path_table_offset_bytes: iso_block_offset(
            pvd.get(140..144)
                .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
            pvd.get(128..130)
                .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        ),
        optional_type_l_path_table_location: pvd
            .get(144..148)
            .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
        optional_type_l_path_table_offset_bytes: iso_block_offset(
            pvd.get(144..148)
                .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
            pvd.get(128..130)
                .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        ),
        type_m_path_table_location: pvd
            .get(148..152)
            .map(|bytes| u32::from_be_bytes(bytes.try_into().expect("four-byte ISO field"))),
        type_m_path_table_offset_bytes: iso_block_offset(
            pvd.get(148..152)
                .map(|bytes| u32::from_be_bytes(bytes.try_into().expect("four-byte ISO field"))),
            pvd.get(128..130)
                .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        ),
        optional_type_m_path_table_location: pvd
            .get(152..156)
            .map(|bytes| u32::from_be_bytes(bytes.try_into().expect("four-byte ISO field"))),
        optional_type_m_path_table_offset_bytes: iso_block_offset(
            pvd.get(152..156)
                .map(|bytes| u32::from_be_bytes(bytes.try_into().expect("four-byte ISO field"))),
            pvd.get(128..130)
                .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        ),
        root_directory_record: parse_iso_root_directory_record(pvd),
        volume_set_identifier: ascii_field(pvd, 190, 128),
        volume_set_identifier_bytes_sha256: sha256_hex(pvd.get(190..318).unwrap_or_default()),
        publisher_identifier: ascii_field(pvd, 318, 128),
        preparer_identifier: ascii_field(pvd, 446, 128),
        application_identifier: ascii_field(pvd, 574, 128),
        copyright_file_identifier: ascii_field(pvd, 702, 37),
        abstract_file_identifier: ascii_field(pvd, 739, 37),
        bibliographic_file_identifier: ascii_field(pvd, 776, 37),
        volume_dates: parse_iso_volume_dates(pvd),
        file_structure_version: pvd.get(881).copied(),
        application_use_sha256: sha256_hex(pvd.get(883..1395).unwrap_or_default()),
        application_use_prefix_hex: hex_prefix(pvd.get(883..1395).unwrap_or_default(), 64),
    }
}

fn parse_iso_root_directory_record(pvd: &[u8]) -> IsoRootDirectoryRecord {
    let record = pvd.get(156..190);
    let logical_block_size = pvd
        .get(128..130)
        .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]]));
    let record_length = record.and_then(|bytes| bytes.first().copied());
    let file_identifier_length = record.and_then(|bytes| bytes.get(32).copied());
    let file_identifier = record.and_then(|bytes| {
        file_identifier_length.and_then(|length| {
            let end = 33 + usize::from(length);
            (end <= bytes.len()).then(|| &bytes[33..end])
        })
    });
    let extent_match = record.and_then(|bytes| bytes.get(2..10)).map(|bytes| {
        u32::from_le_bytes(bytes[0..4].try_into().expect("four-byte ISO field"))
            == u32::from_be_bytes(bytes[4..8].try_into().expect("four-byte ISO field"))
    });
    let data_length_match = record.and_then(|bytes| bytes.get(10..18)).map(|bytes| {
        u32::from_le_bytes(bytes[0..4].try_into().expect("four-byte ISO field"))
            == u32::from_be_bytes(bytes[4..8].try_into().expect("four-byte ISO field"))
    });
    let volume_sequence_match = record.and_then(|bytes| bytes.get(28..32)).map(|bytes| {
        u16::from_le_bytes([bytes[0], bytes[1]]) == u16::from_be_bytes([bytes[2], bytes[3]])
    });
    let flags = record.and_then(|bytes| bytes.get(25).copied());
    let directory_flag_set = flags.map(|value| value & 0x02 != 0);
    let reserved_flags_clear = flags.map(|value| value & 0x60 == 0);
    let basic_structure_checks_pass = record.is_some_and(|bytes| {
        bytes.len() == 34
            && record_length == Some(34)
            && extent_match == Some(true)
            && data_length_match == Some(true)
            && volume_sequence_match == Some(true)
            && file_identifier_length == Some(1)
            && file_identifier == Some(&[0][..])
            && directory_flag_set == Some(true)
            && reserved_flags_clear == Some(true)
    });

    IsoRootDirectoryRecord {
        available: record.is_some(),
        record_length,
        extended_attribute_record_length: record.and_then(|bytes| bytes.get(1).copied()),
        extent_location: record.and_then(|bytes| {
            bytes
                .get(2..6)
                .map(|value| u32::from_le_bytes(value.try_into().expect("four-byte ISO field")))
        }),
        extent_offset_bytes: iso_block_offset(
            record.and_then(|bytes| {
                bytes
                    .get(2..6)
                    .map(|value| u32::from_le_bytes(value.try_into().expect("four-byte ISO field")))
            }),
            logical_block_size,
        ),
        dual_endian_extent_location_match: extent_match,
        data_length: record.and_then(|bytes| {
            bytes
                .get(10..14)
                .map(|value| u32::from_le_bytes(value.try_into().expect("four-byte ISO field")))
        }),
        dual_endian_data_length_match: data_length_match,
        recording_date_bytes_hex: record.and_then(|bytes| bytes.get(18..25)).map(hex),
        file_flags: flags,
        directory_flag_set,
        reserved_flags_clear,
        file_unit_size: record.and_then(|bytes| bytes.get(26).copied()),
        interleave_gap_size: record.and_then(|bytes| bytes.get(27).copied()),
        volume_sequence_number: record.and_then(|bytes| {
            bytes
                .get(28..30)
                .map(|value| u16::from_le_bytes([value[0], value[1]]))
        }),
        dual_endian_volume_sequence_number_match: volume_sequence_match,
        file_identifier_length,
        file_identifier_hex: file_identifier.map(hex),
        is_root_directory_identifier: file_identifier.map(|value| value == [0]),
        basic_structure_checks_pass,
    }
}

fn iso_block_offset(location: Option<u32>, logical_block_size: Option<u16>) -> Option<u64> {
    Some(u64::from(location?).checked_mul(u64::from(logical_block_size?))?)
}

fn parse_iso_volume_dates(pvd: &[u8]) -> IsoVolumeDates {
    let creation = parse_iso_volume_date(pvd.get(813..830).unwrap_or_default());
    let modification = parse_iso_volume_date(pvd.get(830..847).unwrap_or_default());
    let expiration = parse_iso_volume_date(pvd.get(847..864).unwrap_or_default());
    let effective = parse_iso_volume_date(pvd.get(864..881).unwrap_or_default());
    IsoVolumeDates {
        creation_raw_bytes_hex: creation.0,
        creation_ascii_digits_valid: creation.1,
        creation_unspecified: creation.2,
        creation_timezone_quarters: creation.3,
        modification_raw_bytes_hex: modification.0,
        modification_ascii_digits_valid: modification.1,
        modification_unspecified: modification.2,
        modification_timezone_quarters: modification.3,
        expiration_raw_bytes_hex: expiration.0,
        expiration_ascii_digits_valid: expiration.1,
        expiration_unspecified: expiration.2,
        expiration_timezone_quarters: expiration.3,
        effective_raw_bytes_hex: effective.0,
        effective_ascii_digits_valid: effective.1,
        effective_unspecified: effective.2,
        effective_timezone_quarters: effective.3,
    }
}

fn parse_iso_volume_date(value: &[u8]) -> (String, bool, bool, Option<i8>) {
    let raw = hex(value);
    let digits = value.get(..16);
    let digits_valid =
        value.len() == 17 && digits.is_some_and(|bytes| bytes.iter().all(u8::is_ascii_digit));
    let unspecified = digits_valid
        && digits.is_some_and(|bytes| bytes.iter().all(|byte| *byte == b'0'))
        && value[16] == 0;
    let timezone = value
        .get(16)
        .map(|offset| *offset as i8)
        .filter(|offset| (-48..=52).contains(offset));
    (raw, digits_valid, unspecified, timezone)
}

fn hex_prefix(bytes: &[u8], maximum_bytes: usize) -> String {
    hex(&bytes[..bytes.len().min(maximum_bytes)])
}

fn ascii_field(data: &[u8], offset: usize, length: usize) -> String {
    let Some(field) = data.get(offset..offset.saturating_add(length)) else {
        return String::new();
    };
    String::from_utf8_lossy(field)
        .trim_matches(|character: char| character == '\0' || character == ' ')
        .to_owned()
}

fn find_all_with_limit(
    data: &[u8],
    signature: &[u8],
    maximum_end: usize,
    maximum_matches: usize,
) -> (Vec<usize>, bool) {
    if signature.is_empty() || maximum_matches == 0 {
        return (Vec::new(), false);
    }
    let mut matches = data
        .get(..data.len().min(maximum_end))
        .into_iter()
        .flat_map(|bounded_data| bounded_data.windows(signature.len()).enumerate())
        .filter_map(|(offset, window)| (window == signature).then_some(offset))
        .take(maximum_matches.saturating_add(1))
        .collect::<Vec<_>>();
    let truncated = matches.len() > maximum_matches;
    matches.truncate(maximum_matches);
    (matches, truncated)
}

fn le_u32(data: &[u8], offset: usize) -> u32 {
    u32::from_le_bytes(
        data[offset..offset + 4]
            .try_into()
            .expect("validated four-byte field"),
    )
}

fn le_u64(data: &[u8], offset: usize) -> u64 {
    u64::from_le_bytes(
        data[offset..offset + 8]
            .try_into()
            .expect("validated eight-byte field"),
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn parses_7z_header_and_iso_identifiers_from_sample_bytes() {
        let mut seven_zip = vec![0u8; 32];
        seven_zip[..6].copy_from_slice(SEVEN_ZIP);
        seven_zip[6] = 0;
        seven_zip[7] = 4;
        seven_zip[12..20].copy_from_slice(&0u64.to_le_bytes());
        seven_zip[20..28].copy_from_slice(&0u64.to_le_bytes());
        let crc = crc32fast::hash(&seven_zip[12..32]);
        seven_zip[8..12].copy_from_slice(&crc.to_le_bytes());
        let parsed_7z = parse(&seven_zip);
        assert_eq!(parsed_7z.signatures[0].format, "7-Zip");
        assert!(matches!(
            parsed_7z.headers[0],
            FormatHeader::SevenZip {
                start_header_crc_valid: true,
                ..
            }
        ));

        let mut iso = vec![0u8; ISO_PVD_OFFSET + 2048];
        iso[ISO_PVD_OFFSET] = 1;
        iso[ISO_PVD_OFFSET + 1..ISO_PVD_OFFSET + 6].copy_from_slice(b"CD001");
        iso[ISO_PVD_OFFSET + 6] = 1;
        iso[ISO_PVD_OFFSET + 574..ISO_PVD_OFFSET + 586].copy_from_slice(b"MKISOFS     ");
        let parsed_iso = parse(&iso);
        assert!(
            parsed_iso
                .signatures
                .iter()
                .any(|item| item.format == "ISO 9660 primary volume descriptor")
        );
        assert!(matches!(
            parsed_iso.headers[0],
            FormatHeader::Iso9660PrimaryVolumeDescriptor { .. }
        ));
    }

    #[test]
    fn extracts_visible_rar5_header_encryption_parameters() {
        let mut rar = RAR5.to_vec();
        rar.extend_from_slice(&[0; 4]);
        rar.push(33); // Header data length: type, flags, version, encryption flags, KDF, salt, check.
        rar.extend_from_slice(&[4, 0, 0, 1, 15]);
        rar.extend_from_slice(&[0xAA; 16]);
        rar.extend_from_slice(&[0xCC; 12]);
        let header_crc = crc32fast::hash(&rar[12..]);
        rar[8..12].copy_from_slice(&header_crc.to_le_bytes());

        let metadata = parse(&rar);
        let FormatHeader::Rar5 {
            first_block_header_checksum_valid,
            kdf_count_log2,
            kdf_iteration_count,
            password_check_present,
            header_encryption_salt_hex,
            password_check_data_hex,
            ..
        } = &metadata.headers[0]
        else {
            panic!("expected RAR5 metadata");
        };
        assert_eq!(*first_block_header_checksum_valid, Some(true));
        assert_eq!(*kdf_count_log2, Some(15));
        assert_eq!(*kdf_iteration_count, Some(32_768));
        assert_eq!(*password_check_present, Some(true));
        assert_eq!(
            header_encryption_salt_hex.as_deref(),
            Some("aa".repeat(16).as_str())
        );
        assert_eq!(
            password_check_data_hex.as_deref(),
            Some("cc".repeat(12).as_str())
        );
    }

    #[test]
    fn rar5_extra_area_does_not_satisfy_missing_main_archive_flags() {
        let mut rar = RAR5.to_vec();
        rar.extend_from_slice(&[0; 4]);
        // MAIN type, extra-area flag, one-byte extra record (size/type/payload).
        rar.push(6); // Header data length.
        rar.extend_from_slice(&[1, 1, 3, 2, 1, 0]);
        let header_crc = crc32fast::hash(&rar[12..]);
        rar[8..12].copy_from_slice(&header_crc.to_le_bytes());

        let metadata = parse(&rar);
        let FormatHeader::Rar5 {
            first_block_header_available,
            main_archive_flags,
            main_extra_records_complete,
            ..
        } = &metadata.headers[0]
        else {
            panic!("expected RAR5 metadata");
        };
        assert!(!first_block_header_available);
        assert_eq!(*main_archive_flags, None);
        assert!(!main_extra_records_complete);
    }
}
