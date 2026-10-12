use serde::Serialize;

#[derive(Debug, Serialize)]
pub struct FormatMetadata {
    pub parser_engine: &'static str,
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
        first_block_extra_size: Option<u64>,
        first_block_data_size: Option<u64>,
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
    },
    Iso9660PrimaryVolumeDescriptor {
        offset: usize,
        version: u8,
        system_identifier: String,
        volume_identifier: String,
        volume_space_blocks: Option<u32>,
        dual_endian_volume_space_match: Option<bool>,
        logical_block_size: Option<u16>,
        dual_endian_logical_block_size_match: Option<bool>,
        volume_set_identifier: String,
        publisher_identifier: String,
        preparer_identifier: String,
        application_identifier: String,
    },
    Zip {
        offset: usize,
        local_file_header_signature: bool,
    },
    Gzip {
        offset: usize,
    },
    Xz {
        offset: usize,
    },
}

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

    for (format, signature) in [
        ("RAR 5", RAR5),
        ("RAR 4", RAR4),
        ("7-Zip", SEVEN_ZIP),
        ("ZIP", ZIP),
        ("gzip", GZIP),
        ("XZ", XZ),
    ] {
        let found = if matches!(format, "RAR 4" | "RAR 5") {
            find_with_limit(data, signature, 1_048_576 + signature.len())
        } else {
            find(data, signature)
        };
        if let Some(offset) = found {
            signatures.push(SignatureMatch {
                format,
                offset,
                basis: "decoded sample bytes",
            });
            match format {
                "RAR 4" => headers.push(parse_rar4(data, offset)),
                "RAR 5" => headers.push(parse_rar5(data, offset)),
                "7-Zip" => headers.push(parse_7z(data, offset)),
                "ZIP" => headers.push(FormatHeader::Zip {
                    offset,
                    local_file_header_signature: true,
                }),
                "gzip" => headers.push(FormatHeader::Gzip { offset }),
                "XZ" => headers.push(FormatHeader::Xz { offset }),
                _ => {}
            }
        }
    }

    if let Some(offset) = find_iso_pvd(data) {
        signatures.push(SignatureMatch {
            format: "ISO 9660 primary volume descriptor",
            offset,
            basis: "validated type-1 descriptor in the bounded ISO descriptor sequence",
        });
        headers.push(parse_iso_pvd(data, offset));
    }

    FormatMetadata {
        parser_engine: "nntmux-rust-ffi",
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
        (block.len() >= 13 && header_type == Some(0x73)).then(|| {
            let expected = u16::from_le_bytes([block[0], block[1]]);
            let calculated = crc32fast::hash(&block[2..13]) as u16;
            expected == calculated
        })
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
        return empty_rar5(offset);
    };
    let expected_crc = u32::from_le_bytes(crc_bytes.try_into().expect("four-byte CRC"));
    let mut cursor = block_start + 4;
    let Some(header_size) = read_vint(data, &mut cursor) else {
        return empty_rar5(offset);
    };
    let Ok(header_size_usize) = usize::try_from(header_size) else {
        return empty_rar5(offset);
    };
    let header_end = match cursor.checked_add(header_size_usize) {
        Some(end) if end <= data.len() => end,
        _ => return empty_rar5(offset),
    };
    let calculated_crc = crc32fast::hash(&data[block_start + 4..header_end]);
    let Some(block_type) = read_vint_bounded(data, &mut cursor, header_end) else {
        return empty_rar5(offset);
    };
    let Some(block_flags) = read_vint_bounded(data, &mut cursor, header_end) else {
        return empty_rar5(offset);
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
    let typed_header_end = extra_size
        .and_then(|size| usize::try_from(size).ok())
        .and_then(|size| header_end.checked_sub(size))
        .filter(|end| *end >= cursor);
    let extra_area_valid = typed_header_end.is_some();
    let typed_header_end = typed_header_end.unwrap_or(cursor);
    let mut main_archive_flags = None;
    let mut main_volume_number = None;
    let mut algorithm_version = None;
    let mut encryption_flags = None;
    let mut kdf_count = None;
    let mut salt = None;
    let mut password_check_data = None;
    let mut typed_header_complete = extra_size.is_some() && data_size.is_some() && extra_area_valid;

    match block_type {
        1 => {
            main_archive_flags = read_vint_bounded(data, &mut cursor, typed_header_end);
            typed_header_complete &= main_archive_flags.is_some();
            if main_archive_flags.is_some_and(|flags| flags & 0x02 != 0) {
                main_volume_number = read_vint_bounded(data, &mut cursor, typed_header_end);
                typed_header_complete &= main_volume_number.is_some();
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
        first_block_extra_size: extra_size,
        first_block_data_size: data_size,
    }
}

fn empty_rar5(offset: usize) -> FormatHeader {
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
        first_block_header_size: None,
        first_block_extra_size: None,
        first_block_data_size: None,
    }
}

fn read_vint(data: &[u8], cursor: &mut usize) -> Option<u64> {
    let mut value = 0u64;
    for shift in (0..70).step_by(7) {
        let byte = *data.get(*cursor)?;
        *cursor = cursor.checked_add(1)?;
        let part = byte & 0x7f;
        if shift == 63 && part > 1 {
            return None;
        }
        value |= u64::from(part) << shift;
        if byte & 0x80 == 0 {
            return Some(value);
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
    let next_header_checksum_valid = if header.is_some() && header_range_within_sample {
        archive_start
            .and_then(|base| base.checked_add(32))
            .and_then(|base| base.checked_add(next_header_offset))
            .and_then(|start| usize::try_from(start).ok())
            .zip(usize::try_from(next_header_size).ok())
            .and_then(|(start, size)| data.get(start..start.checked_add(size)?))
            .map(|next_header| crc32fast::hash(next_header) == next_header_crc)
    } else {
        None
    };
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
    }
}

fn find_iso_pvd(data: &[u8]) -> Option<usize> {
    for sector in 16usize..256 {
        let offset = sector * 2048;
        let Some(descriptor) = data.get(offset..offset.saturating_add(2048)) else {
            break;
        };
        if &descriptor[1..6] != b"CD001" || descriptor[6] != 1 {
            return None;
        }
        match descriptor[0] {
            1 => return Some(offset),
            255 => return None,
            _ => {}
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
        volume_identifier: ascii_field(pvd, 40, 32),
        volume_space_blocks: pvd
            .get(80..84)
            .map(|bytes| u32::from_le_bytes(bytes.try_into().expect("four-byte ISO field"))),
        dual_endian_volume_space_match: pvd.get(80..88).map(|bytes| {
            u32::from_le_bytes(bytes[0..4].try_into().expect("four-byte ISO field"))
                == u32::from_be_bytes(bytes[4..8].try_into().expect("four-byte ISO field"))
        }),
        logical_block_size: pvd
            .get(128..130)
            .map(|bytes| u16::from_le_bytes([bytes[0], bytes[1]])),
        dual_endian_logical_block_size_match: pvd.get(128..132).map(|bytes| {
            u16::from_le_bytes([bytes[0], bytes[1]]) == u16::from_be_bytes([bytes[2], bytes[3]])
        }),
        volume_set_identifier: ascii_field(pvd, 190, 128),
        publisher_identifier: ascii_field(pvd, 318, 128),
        preparer_identifier: ascii_field(pvd, 446, 128),
        application_identifier: ascii_field(pvd, 574, 128),
    }
}

fn ascii_field(data: &[u8], offset: usize, length: usize) -> String {
    let Some(field) = data.get(offset..offset.saturating_add(length)) else {
        return String::new();
    };
    String::from_utf8_lossy(field)
        .trim_matches(|character: char| character == '\0' || character == ' ')
        .to_owned()
}

fn find(data: &[u8], signature: &[u8]) -> Option<usize> {
    data.windows(signature.len())
        .position(|window| window == signature)
}

fn find_with_limit(data: &[u8], signature: &[u8], maximum_end: usize) -> Option<usize> {
    data.get(..data.len().min(maximum_end))?
        .windows(signature.len())
        .position(|window| window == signature)
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
}
