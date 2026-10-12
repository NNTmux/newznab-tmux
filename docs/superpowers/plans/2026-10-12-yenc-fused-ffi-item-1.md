# Bounded Native Whole-Body yEnc Decoder ABI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a bounded Rust FFI ABI that can decode one complete lenient yEnc BODY response without changing PHP callers.

**Architecture:** The existing `docker/app/nzb-yenc-ffi` cdylib retains the
`rapidyenc_*` compatibility symbols and gains one namespaced function. The
function recognizes a complete yEnc frame from a borrowed byte slice, decodes
into caller-owned memory only after all bounds checks, and reports a status
that lets a later PHP caller preserve current behavior on uncertainty.

**Tech Stack:** Rust 2024, `nzb-decode`, C ABI, existing Docker Rust build.

**Spec:** [`docs/superpowers/specs/2026-10-12-yenc-fused-ffi-item-1-design.md`](../specs/2026-10-12-yenc-fused-ffi-item-1-design.md)

## Global Constraints

- Preserve all exported `rapidyenc_*` symbols and their ABI behavior.
- Accept only already dot-unstuffed, complete NNTP BODY bytes; do not take a socket or stream.
- Never write output for a non-`DECODED` status or beyond input length/caller capacity.
- Return `FALLBACK`, not guessed output, for malformed, ambiguous, unsupported, or panic paths.
- Do not add a PHP caller, dependency, configuration key, Docker change, or speed claim in this item.
- Run Rust validation in the approved build environment; record source and fixture provenance with results.

## Review Focus

- A line-leading valid `=yend` must remain a trailer boundary, while a false
  control such as `=yendx` remains payload data.
- An escape split over CR/LF must preserve the PHP lenient result.
- A false control prefix outside a line boundary must not become a frame.
- A second valid `=ybegin` before `=yend` must return `FALLBACK`, never select an arbitrary frame.
- A null pointer, undersized output buffer, or Rust panic must not expose
  partial output. Aliased caller storage is an unsupported caller precondition.

---

### Task 1: Define the public status and C ABI

**Files:**
- Modify: `docker/app/nzb-yenc-ffi/src/lib.rs:1-80`
- Test: `docker/app/nzb-yenc-ffi/src/lib.rs` unit-test module

**Interfaces:**
- Produces: `#[repr(C)] pub enum NntmuxYencBodyStatus` with `Decoded = 0`,
  `NotYenc = 1`, `Fallback = 2`, and `InvalidArgument = 3`.
- Produces: `unsafe extern "C" fn nntmux_yenc_decode_body(input: *const c_void, input_length: usize, output: *mut c_void, output_capacity: usize, output_length: *mut usize) -> NntmuxYencBodyStatus`.

- [ ] **Step 1: Write the failing ABI-status tests**

```rust
fn call_body(
    input: &[u8],
    output: &mut [u8],
    written: &mut usize,
) -> NntmuxYencBodyStatus {
    unsafe {
        nntmux_yenc_decode_body(
            input.as_ptr().cast(),
            input.len(),
            output.as_mut_ptr().cast(),
            output.len(),
            written,
        )
    }
}

fn call_status(input: &[u8]) -> NntmuxYencBodyStatus {
    let mut output = vec![0xa5; input.len()];
    let mut written = 0xfeed_beef;
    call_body(input, &mut output, &mut written)
}

fn call_status_into(input: &[u8], output: &mut [u8]) -> NntmuxYencBodyStatus {
    let mut written = 0xfeed_beef;
    call_body(input, output, &mut written)
}

fn call_body_bytes(input: &[u8]) -> (NntmuxYencBodyStatus, Vec<u8>) {
    let mut output = vec![0; input.len()];
    let mut written = 0;
    let status = call_body(input, &mut output, &mut written);
    if status == NntmuxYencBodyStatus::Decoded {
        output.truncate(written);
    } else {
        output.clear();
    }

    (status, output)
}

#[test]
fn rejects_null_output_length_without_writing() {
    let mut output = [0xa5; 8];
    let status = unsafe {
        nntmux_yenc_decode_body(
            b"plain".as_ptr().cast(),
            5,
            output.as_mut_ptr().cast(),
            output.len(),
            std::ptr::null_mut(),
        )
    };
    assert_eq!(status, NntmuxYencBodyStatus::InvalidArgument);
    assert_eq!(output, [0xa5; 8]);
}

#[test]
fn returns_not_yenc_and_preserves_output_for_plain_body() {
    let mut output = [0xa5; 8];
    let mut written = 0xfeed_beef;
    let status = call_body(b"plain", &mut output, &mut written);
    assert_eq!(status, NntmuxYencBodyStatus::NotYenc);
    assert_eq!(output, [0xa5; 8]);
    assert_eq!(written, 0xfeed_beef);
}

#[test]
fn permits_null_zero_length_buffers() {
    let mut written = 0xfeed_beef;
    let status = unsafe {
        nntmux_yenc_decode_body(
            std::ptr::null(),
            0,
            std::ptr::null_mut(),
            0,
            &mut written,
        )
    };
    assert_eq!(status, NntmuxYencBodyStatus::NotYenc);
    assert_eq!(written, 0xfeed_beef);
}
```

- [ ] **Step 2: Run the tests and verify the intended RED failure**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: compilation fails because the body status type and ABI symbol do not exist.

- [ ] **Step 3: Add the status enum and defensive ABI shell**

Define the `#[repr(C)]` enum with explicit discriminants. Validate
`output_length`, null/length pointer pairs, and capacity before constructing
slices. Initialize no output bytes on non-success. Return `NotYenc` for a
well-formed plain byte slice; leave framed behavior for Task 2.

- [ ] **Step 4: Run the focused tests and verify GREEN**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: PASS.

### Task 2: Recognize only an unambiguous complete yEnc frame

**Files:**
- Modify: `docker/app/nzb-yenc-ffi/src/lib.rs`
- Test: `docker/app/nzb-yenc-ffi/src/lib.rs` unit-test module

**Interfaces:**
- Consumes: `NntmuxYencBodyStatus` and the ABI shell from Task 1.
- Produces: private `find_complete_frame(body: &[u8]) -> Result<Frame<'_>, NntmuxYencBodyStatus>`, where `Frame` exposes its borrowed payload for module tests.

- [ ] **Step 1: Write the failing frame-recognition tests**

```rust
#[test]
fn returns_fallback_when_a_second_valid_begin_precedes_end() {
    let body = b"=ybegin line=128 size=3 name=a\r\nklm\r\n=ybegin line=128 size=3 name=b\r\nklm\r\n=yend size=3\r\n";
    assert_eq!(call_status(body), NntmuxYencBodyStatus::Fallback);
}

#[test]
fn ignores_false_control_prefix_inside_a_payload_line() {
    assert_eq!(
        call_status(b"x=ybegin line=128 size=3 name=a\r\n"),
        NntmuxYencBodyStatus::NotYenc,
    );
}

#[test]
fn leaves_false_yend_control_in_the_payload() {
    let body = b"=ybegin line=128 size=6 name=a\r\n=yendx\r\n=yend size=6\r\n";
    let frame = find_complete_frame(body).expect("complete frame");
    assert_eq!(frame.payload, b"=yendx\r\n");
}

#[test]
fn returns_fallback_for_a_truncated_frame() {
    assert_eq!(
        call_status(b"=ybegin line=128 size=3 name=a\r\nklm"),
        NntmuxYencBodyStatus::Fallback,
    );
}
```

- [ ] **Step 2: Run the tests and verify the intended RED failure**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: FAIL because the ABI shell cannot classify a framed body.

- [ ] **Step 3: Implement conservative frame detection**

Scan only body-start or `\n` control boundaries, using PHP-compatible,
case-insensitive control delimiters. Parse enough `=ybegin` fields to require
`line=`, `size=`, and `name=`. Return `Fallback` for every immediate `=ypart`
line rather than parsing multipart values in this item. Find a bounded valid
`=yend` control; a line-leading `=yendx` remains payload. If another valid
begin appears first, or any required line is incomplete, return `Fallback`.
Do not parse checksums or multipart ranges in this item.

- [ ] **Step 4: Run the frame tests and verify GREEN**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: PASS.

### Task 3: Decode a recognized frame without partial output

**Files:**
- Modify: `docker/app/nzb-yenc-ffi/src/lib.rs`
- Test: `docker/app/nzb-yenc-ffi/src/lib.rs` unit-test module

**Interfaces:**
- Consumes: `Frame<'_>` from Task 2.
- Produces: `Decoded` status with `output_length` set only after a complete,
  capacity-checked decode.

- [ ] **Step 1: Write the failing decode tests**

```rust
#[test]
fn decodes_escaped_payload_and_split_escape() {
    let body = b"=ybegin line=128 size=4 name=x\r\nklm=\r\n@\r\n=yend size=4\r\n";
    let (status, output) = call_body_bytes(body);
    assert_eq!(status, NntmuxYencBodyStatus::Decoded);
    assert_eq!(output, b"ABC\xd6");
}

#[test]
fn undersized_output_returns_fallback_without_partial_bytes() {
    let body = b"=ybegin line=128 size=3 name=x\r\nklm\r\n=yend size=3\r\n";
    let mut output = [0xa5; 2];
    assert_eq!(call_status_into(body, &mut output), NntmuxYencBodyStatus::Fallback);
    assert_eq!(output, [0xa5; 2]);
}
```

- [ ] **Step 2: Run the tests and verify the intended RED failure**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: FAIL because complete-frame decoding is not implemented.

- [ ] **Step 3: Implement one-pass payload normalization and decode**

For an accepted frame, remove only CR/LF bytes while preserving split-escape
semantics, use the existing `nzb_decode` dependency to decode, retain the
result in an internal `Vec<u8>`, verify it fits both input length and caller
capacity, then copy it once and set `output_length`. Wrap the body in
`catch_unwind`; a panic returns `Fallback` before any copy.

- [ ] **Step 4: Run the decode tests and verify GREEN**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: PASS.

### Task 4: Lock compatibility with the existing library ABI

**Files:**
- Modify: `docker/app/nzb-yenc-ffi/src/lib.rs`
- Test: `docker/app/nzb-yenc-ffi/src/lib.rs` unit-test module

**Interfaces:**
- Consumes: the new body ABI and pre-existing `rapidyenc_*` exports.
- Produces: a release-mode test suite that proves both ABI families remain callable.

- [ ] **Step 1: Write a failing coexistence test**

Add a test that calls `rapidyenc_decode_ex` for its existing known answer and
then calls `nntmux_yenc_decode_body` for a valid framed body, asserting the
literal byte results from both.

- [ ] **Step 2: Run it and verify the intended RED failure**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked body_abi_coexists_with_rapidyenc_compatibility_exports`

Expected: FAIL until the new symbol and helper are available together.

- [ ] **Step 3: Keep public symbols isolated and document the ABI invariant**

Add Rustdoc above the new function documenting input ownership, non-aliasing
preconditions, status semantics, unchanged `output_length` on non-success,
and the no-partial-output guarantee. Do not rename, remove, or alter the
compatibility exports.

- [ ] **Step 4: Run all Rust library tests**

Run: `cd docker/app/nzb-yenc-ffi && cargo test --release --locked`

Expected: PASS with the old and new ABI tests.

- [ ] **Step 5: Verify dynamic symbols from the release cdylib**

Run: `cd docker/app/nzb-yenc-ffi && cargo build --release --locked && nm -D target/release/libnzbyenc.so | grep -E ' (rapidyenc_decode_ex|rapidyenc_decode_init|rapidyenc_version|rapidyenc_crc|rapidyenc_crc_init|nntmux_yenc_decode_body)$'`

Expected: all six symbols are present in the release artifact. Run this in the
Linux build runtime because `nm -D` is an ELF check.

### Task 5: Validate the item-1 source in the supported build path

**Files:**
- Modify: none unless validation exposes a code defect.

**Interfaces:**
- Consumes: completed ABI and unit suite.
- Produces: recorded source-level validation only; no runtime integration.

- [ ] **Step 1: Build the Rust cdylib in release mode**

Run: `cd docker/app/nzb-yenc-ffi && cargo build --release --locked`

Expected: exit 0 and `target/release/libnzbyenc.so` exists.

- [ ] **Step 2: Run focused PHP regression tests without native selection**

Run: `php artisan test --compact tests/Unit/YencServiceTest.php tests/Unit/YencDecoderTest.php tests/Unit/NNTPBodyDecodingTest.php`

Expected: PASS; this item must not change PHP behavior.

- [ ] **Step 3: Run repository-required project validation**

Run: `./vendor/bin/pint --dirty && ./vendor/bin/phpstan analyse --memory-limit=2G && find app -name '*.php' -print0 | xargs -0 -n1 php -l && php artisan test --compact`

Expected: all commands exit 0. If host PHP is unavailable, run the equivalent
approved indexer CLI runtime and report that environmental substitution.

- [ ] **Step 4: Record the evidence boundary**

Record the exact commit, Rust toolchain, Cargo lockfile hash, command output,
and all test counts in the PR. State explicitly that item 1 has no PHP caller
and no performance qualification; item 4 owns performance artifacts.
