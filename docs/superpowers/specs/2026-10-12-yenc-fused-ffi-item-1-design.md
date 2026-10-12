# Bounded Native Whole-Body yEnc Decoder ABI

## Status

Implementation design for delivery item 1 from [`5_TODO.md`](../../../5_TODO.md).
This document does not authorize runtime selection, deployment, or a speedup
claim.

## Problem

`NNTPService::readBody()` receives a dot-unstuffed NNTP BODY response and
passes its complete string to `YencService::decodeIgnore()`. The current
native backend accepts only the payload slice after PHP has found and parsed a
frame. That leaves PHP responsible for whole-body frame searches, slicing,
line-ending handling decisions, and tolerant edge-case recovery.

An exploratory run on source snapshot
`7d62dc03f83088829945d2e0e63fb597dddc4984` used the existing benchmark corpus
in the cloud-indexer-equivalent CLI image (Linux/aarch64, PHP 8.5.11 ZTS, FFI
enabled). Its console output was not retained as a reviewed artifact, and the
current harness emits medians/ranges rather than raw samples or randomized
variant order. It is therefore non-qualifying historical observation only;
item 4 owns durable performance evidence. This item is justified by the
extra whole-body PHP work visible in the current call graph, not by a speedup
claim.

## Goal

Add a bounded Rust C ABI for **lenient whole-body yEnc decoding**. Given one
already dot-unstuffed NNTP BODY response and caller-owned output capacity equal
to the input length, it either returns a complete decoded yEnc frame or a
precise non-success status. It never mutates the input and never returns
partial output.

## Scope

- Add the ABI and Rust implementation under `docker/app/nzb-yenc-ffi/`.
- Add Rust unit tests for its byte-level contract and outcome statuses.
- Preserve the existing `rapidyenc_*` symbols unchanged.
- Preserve all PHP behavior by leaving PHP call sites unchanged in this item.

## Non-goals

- No socket ownership, NNTP reads, dot unstuffing, or streaming transport in Rust.
- No PHP FFI declaration or `YencService`/`NNTPService` caller change.
- No change to `decode()`, `decodeWithFrame()`, multipart validation, metadata parsing, CRC validation, or encoder behavior.
- No Docker, environment, deployment, or dependency change.
- No performance claim from this item alone.

## ABI contract

The library exports:

```c
typedef enum {
    NNTMUX_YENC_BODY_DECODED = 0,
    NNTMUX_YENC_BODY_NOT_YENC = 1,
    NNTMUX_YENC_BODY_FALLBACK = 2,
    NNTMUX_YENC_BODY_INVALID_ARGUMENT = 3
} nntmux_yenc_body_status;

nntmux_yenc_body_status nntmux_yenc_decode_body(
    const void *input,
    size_t input_length,
    void *output,
    size_t output_capacity,
    size_t *output_length
);
```

`DECODED` means `output[0..*output_length]` is the exact lenient result. The
implementation writes only in that case, and `output_length` is at most
`input_length`. `NOT_YENC` means there is no complete valid frame and the
caller must retain the original body. `FALLBACK` means the input contains a
frame or ambiguity whose current PHP behavior must decide; it is deliberately
not a decode failure. `INVALID_ARGUMENT` covers null or inconsistent pointers,
lengths, and capacity. It has no PHP-visible error mapping in this item.

`output_length` is required and is left unmodified for every non-`DECODED`
status. `input` may be null only when `input_length` is zero. `output` may be
null only when `output_capacity` is zero. The caller must provide
non-overlapping input, output, and `output_length` storage; violating that
precondition is unsupported and the function makes no alias-safety promise.
For a nonempty successful decode, `output` must be non-null and the decoded
length must fit both `input_length` and `output_capacity`.

The implementation recognizes controls only at the start of the body or after
`\n`, with the same case-insensitive control-name delimiter rule as
`ArticleFrame`. A line-leading valid `=yend` is a trailer boundary, exactly as
it is in PHP; a false prefix such as `=yendx` remains payload. Item 1 returns
`FALLBACK` for every immediate `=ypart` multipart frame rather than attempting
partial multipart interpretation. It rejects a candidate if a later valid
`=ybegin` occurs before its `=yend`. It ignores CR/LF while decoding the
payload. A dangling escape, incomplete control line, malformed multipart
shape, or unsupported ambiguity returns `FALLBACK`, not a guessed result.

## Correctness requirements

- Decoded bytes must match the PHP lenient decoder for every ABI-success fixture.
- A valid line-leading `=yend` remains a trailer boundary; false controls such
  as `=yendx` remain payload data.
- Escapes split by CR/LF decode as the existing PHP path does.
- A frame-like string that is not at a valid control boundary is not a frame.
- Rust panics and impossible output sizes must become `FALLBACK`, leaving no partial output exposed.
- The caller's output capacity is checked before every write; capacity below the decoded size returns `FALLBACK`.

## Evidence and acceptance

Item 1 is accepted only when the Rust unit suite passes and the existing
`rapidyenc_*` ABI tests remain green. It produces source-level correctness
evidence only. It receives its own source-only PR review and merge gate. Item
4 owns benchmark artifacts, and item 5 owns performance/integration and
deployment qualification.
