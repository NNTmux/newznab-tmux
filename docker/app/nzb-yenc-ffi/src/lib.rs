//! C ABI shim that exposes nzb-decode's yEnc decoder under RapidYenc's symbol
//! names, so `App\Services\Yenc\NativePayloadDecoder` loads it through FFI
//! unchanged (`YENC_NATIVE_LIBRARY=/path/to/libnzbyenc.so`).
//!
//! NNTmux hands the decoder only the encoded payload between `=ybegin`/`=ypart`
//! and `=yend`, while nzb-decode decodes whole articles. The payload is
//! therefore wrapped in a minimal header and footer.
//!
//! nzb-decode reads `=yend` and `=ypart` wherever a line starts with them, but
//! inside a payload `=y` is just an escaped byte (encoders never emit it, yet
//! NNTmux must decode it). Line endings carry no data, so they are removed
//! first; that leaves the payload's first bytes as its only line start, and any
//! leading `=y` escapes there are decoded before handing over the rest. Removing
//! them first also joins escapes split across a line break, as the PHP decoder does.

use std::ffi::{CString, c_char, c_int, c_void};
use std::panic::catch_unwind;
use std::ptr;
use std::slice;

mod formats;
mod par2;
mod par2_source_ranges;
mod seven_zip_ranges;

/// nzb-decode 0.1.3, packed as RapidYenc packs its version (major << 16 | minor << 8 | patch).
const VERSION: c_int = 3 | (1 << 8);

/// Returned instead of a length when decoding fails; NativePayloadDecoder rejects
/// any length larger than its input.
const DECODE_FAILED: usize = usize::MAX;

const HEADER: &[u8] = b"=ybegin line=128 name=payload\r\n";
const FOOTER: &[u8] = b"\r\n=yend\r\n";
const MAX_METADATA_INPUT_BYTES: usize = 67_108_864;
const MAX_SEVEN_ZIP_RANGE_INPUT_BYTES: usize = 16 * 1024 * 1024 + 4096;
const MAX_PAR2_SOURCE_RANGE_INPUT_BYTES: usize = 16 * 1024 * 1024 + 16 * 1024;

/// Decode a yEnc payload (CR/LF line endings are skipped, `=X` escapes resolved).
pub fn decode_payload(payload: &[u8]) -> Option<Vec<u8>> {
    let mut article = Vec::with_capacity(HEADER.len() + payload.len() + FOOTER.len());
    article.extend_from_slice(HEADER);
    let body_start = article.len();
    article.extend(
        payload
            .iter()
            .copied()
            .filter(|&b| b != b'\r' && b != b'\n'),
    );

    let mut decoded = Vec::new();
    let mut skip = body_start;
    while article.len() >= skip + 2 && article[skip] == b'=' && article[skip + 1] == b'y' {
        decoded.push(b'y'.wrapping_sub(106));
        skip += 2;
    }
    article.drain(body_start..skip);
    article.extend_from_slice(FOOTER);

    let result = nzb_decode::decode_yenc(&article).ok()?;
    if decoded.is_empty() {
        return Some(result.data);
    }
    decoded.extend_from_slice(&result.data);

    Some(decoded)
}

#[unsafe(no_mangle)]
pub extern "C" fn rapidyenc_decode_init() {}

#[unsafe(no_mangle)]
pub extern "C" fn rapidyenc_version() -> c_int {
    VERSION
}

/// Return PAR2 packet metadata as a newly allocated UTF-8 JSON C string.
/// The caller must release it with `nntmux_metadata_string_free`.
///
/// # Safety
/// `data` must be readable for `length` bytes. Inputs over the bounded parser
/// budget are rejected by returning a null pointer.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn nntmux_par2_metadata_json(data: *const u8, length: usize) -> *mut c_char {
    if length > MAX_METADATA_INPUT_BYTES || (length > 0 && data.is_null()) {
        return ptr::null_mut();
    }
    let input = if length == 0 {
        &[]
    } else {
        // SAFETY: the caller guarantees `data` is readable for `length` bytes.
        unsafe { slice::from_raw_parts(data, length) }
    };
    let encoded = catch_unwind(|| serde_json::to_string(&par2::parse(input)));
    match encoded {
        Ok(Ok(json)) => CString::new(json)
            .map(CString::into_raw)
            .unwrap_or(ptr::null_mut()),
        _ => ptr::null_mut(),
    }
}

/// Return bounded format-signature and header metadata as UTF-8 JSON.
/// The caller must release it with `nntmux_metadata_string_free`.
///
/// # Safety
/// `data` must be readable for `length` bytes. Inputs over the bounded parser
/// budget are rejected by returning a null pointer.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn nntmux_format_metadata_json(
    data: *const u8,
    length: usize,
) -> *mut c_char {
    if length > MAX_METADATA_INPUT_BYTES || (length > 0 && data.is_null()) {
        return ptr::null_mut();
    }
    let input = if length == 0 {
        &[]
    } else {
        // SAFETY: the caller guarantees `data` is readable for `length` bytes.
        unsafe { slice::from_raw_parts(data, length) }
    };
    let encoded = catch_unwind(|| serde_json::to_string(&formats::parse(input)));
    match encoded {
        Ok(Ok(json)) => CString::new(json)
            .map(CString::into_raw)
            .unwrap_or(ptr::null_mut()),
        _ => ptr::null_mut(),
    }
}

/// Resolve one 7-Zip next-header range and any encoded-header PackInfo ranges
/// from a compact list of sampled file ranges. Input is: fragment count (u32 LE),
/// declared file size (u64 LE), then repeated file offset (u64 LE), byte length
/// (u32 LE), and raw decoded bytes.
/// The caller must release the returned UTF-8 JSON with `nntmux_metadata_string_free`.
///
/// # Safety
/// `data` must be readable for `length` bytes. Inputs over the range budget are rejected.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn nntmux_seven_zip_ranges_json(
    data: *const u8,
    length: usize,
) -> *mut c_char {
    if length > MAX_SEVEN_ZIP_RANGE_INPUT_BYTES || data.is_null() || length == 0 {
        return ptr::null_mut();
    }
    // SAFETY: the caller guarantees `data` is readable for `length` bytes.
    let input = unsafe { slice::from_raw_parts(data, length) };
    let encoded = catch_unwind(|| {
        seven_zip_ranges::parse(input).and_then(|metadata| serde_json::to_string(&metadata).ok())
    });
    match encoded {
        Ok(Some(json)) => CString::new(json)
            .map(CString::into_raw)
            .unwrap_or(ptr::null_mut()),
        _ => ptr::null_mut(),
    }
}

/// Verify bounded PAR2 IFSC slices against decoded yEnc article fragments.
/// The input is a packed P2SR record; the caller must free the returned JSON.
///
/// # Safety
/// `data` must be readable for `length` bytes. Inputs over the range budget are rejected.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn nntmux_par2_source_ranges_json(
    data: *const u8,
    length: usize,
) -> *mut c_char {
    if length > MAX_PAR2_SOURCE_RANGE_INPUT_BYTES || data.is_null() || length == 0 {
        return ptr::null_mut();
    }
    // SAFETY: the caller guarantees `data` is readable for `length` bytes.
    let input = unsafe { slice::from_raw_parts(data, length) };
    let encoded = catch_unwind(|| {
        par2_source_ranges::parse(input).and_then(|metadata| serde_json::to_string(&metadata).ok())
    });
    match encoded {
        Ok(Some(json)) => CString::new(json)
            .map(CString::into_raw)
            .unwrap_or(ptr::null_mut()),
        _ => ptr::null_mut(),
    }
}

/// Release a metadata JSON string returned by this library.
///
/// # Safety
/// `value` must be null or a pointer returned by one of the metadata functions.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn nntmux_metadata_string_free(value: *mut c_char) {
    if !value.is_null() {
        // SAFETY: the pointer was produced by `CString::into_raw` in this library.
        unsafe { drop(CString::from_raw(value)) };
    }
}

/// Decode `src_length` bytes from `src` into `dest` and return the decoded length.
///
/// `is_raw` and `state` exist for RapidYenc compatibility; NNTmux always passes
/// 0 and NULL, and dot-unstuffing has already happened by then.
///
/// # Safety
///
/// `src` must be readable and `dest` writable for `src_length` bytes. Decoded
/// yEnc is never longer than its input.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn rapidyenc_decode_ex(
    _is_raw: c_int,
    src: *const c_void,
    dest: *mut c_void,
    src_length: usize,
    _state: *mut c_int,
) -> usize {
    if src_length == 0 {
        return 0;
    }
    if src.is_null() || dest.is_null() {
        return DECODE_FAILED;
    }
    // SAFETY: the caller guarantees `src` is readable for `src_length` bytes.
    let input = unsafe { slice::from_raw_parts(src.cast::<u8>(), src_length) };

    match catch_unwind(|| decode_payload(input)) {
        Ok(Some(decoded)) if decoded.len() <= src_length => {
            // SAFETY: `dest` is writable for `src_length` bytes and `decoded` fits.
            unsafe { ptr::copy_nonoverlapping(decoded.as_ptr(), dest.cast::<u8>(), decoded.len()) };
            decoded.len()
        }
        _ => DECODE_FAILED,
    }
}

#[unsafe(no_mangle)]
pub extern "C" fn rapidyenc_crc_init() {}

/// CRC-32 (the same digest as PHP's `crc32b`), continuing from `init_crc`.
///
/// # Safety
///
/// `src` must be readable for `src_length` bytes.
#[unsafe(no_mangle)]
pub unsafe extern "C" fn rapidyenc_crc(
    src: *const c_void,
    src_length: usize,
    init_crc: u32,
) -> u32 {
    if src_length == 0 || src.is_null() {
        return init_crc;
    }
    // SAFETY: the caller guarantees `src` is readable for `src_length` bytes.
    let data = unsafe { slice::from_raw_parts(src.cast::<u8>(), src_length) };
    let mut hasher = crc32fast::Hasher::new_with_initial(init_crc);
    hasher.update(data);

    hasher.finalize()
}

#[cfg(test)]
mod tests {
    use super::*;

    fn decode_via_abi(payload: &[u8]) -> Result<Vec<u8>, usize> {
        let mut output = vec![0u8; payload.len()];
        let written = unsafe {
            rapidyenc_decode_ex(
                0,
                payload.as_ptr().cast(),
                output.as_mut_ptr().cast(),
                payload.len(),
                ptr::null_mut(),
            )
        };
        if written > payload.len() {
            return Err(written);
        }
        output.truncate(written);

        Ok(output)
    }

    #[test]
    fn passes_the_known_answer_check_native_payload_decoder_runs_on_load() {
        assert_eq!(
            decode_via_abi(b"klm=@=J=M=}..\x0b==").unwrap(),
            b"ABC\xd6\xe0\xe3\x13\x04\x04\xe1\xd3"
        );
    }

    #[test]
    fn skips_line_endings_in_raw_payloads() {
        assert_eq!(decode_via_abi(b"klm\r\nklm\nklm").unwrap(), b"ABCABCABC");
    }

    #[test]
    fn round_trips_every_byte_value_through_the_encoder() {
        let original: Vec<u8> = (0..=255).cycle().take(4096).collect();
        let (article, _) =
            nzb_decode::yenc::encode_article(&original, "all.bin", 1, 1, 0, original.len() as u64);
        let text = article.as_slice();
        let start = text.windows(2).position(|w| w == b"\r\n").unwrap() + 2;
        let start = if text[start..].starts_with(b"=ypart ") {
            start + text[start..].windows(2).position(|w| w == b"\r\n").unwrap() + 2
        } else {
            start
        };
        let end = start
            + text[start..]
                .windows(5)
                .position(|w| w == b"=yend")
                .unwrap();

        assert_eq!(decode_via_abi(&text[start..end]).unwrap(), original);
    }

    #[test]
    fn treats_line_leading_yenc_keywords_as_escaped_data() {
        let expected = [
            121u8.wrapping_sub(106),
            101 - 42,
            110 - 42,
            100 - 42,
            120 - 42,
        ];
        assert_eq!(decode_via_abi(b"=yendx").unwrap(), expected);
        assert_eq!(
            decode_via_abi(b"klm\r\n=yendx").unwrap(),
            [b"ABC".as_slice(), &expected].concat()
        );
        assert_eq!(decode_via_abi(b"=y=ypart x").unwrap().len(), 8);
    }

    #[test]
    fn joins_escapes_split_across_a_line_break() {
        assert_eq!(decode_via_abi(b"k=\r\n@").unwrap(), b"A\xd6");
    }

    #[test]
    fn decodes_empty_input_to_nothing() {
        assert_eq!(decode_via_abi(b"").unwrap(), b"");
    }

    #[test]
    fn crc_matches_php_crc32b() {
        let data = b"ABC";
        assert_eq!(
            unsafe { rapidyenc_crc(data.as_ptr().cast(), data.len(), 0) },
            0xa383_0348
        );
    }

    #[test]
    fn crc_continues_from_an_initial_value() {
        let whole = unsafe { rapidyenc_crc(b"ABCDEF".as_ptr().cast(), 6, 0) };
        let first = unsafe { rapidyenc_crc(b"ABC".as_ptr().cast(), 3, 0) };
        let resumed = unsafe { rapidyenc_crc(b"DEF".as_ptr().cast(), 3, first) };
        assert_eq!(resumed, whole);
    }

    #[test]
    fn reports_the_nzb_decode_version() {
        assert_eq!(rapidyenc_version(), 0x0103);
    }
}
