<section class="surface-panel rounded-xl border border-base-300 p-4 sm:p-5" aria-labelledby="release-fingerprint-heading">
    <h2 id="release-fingerprint-heading" class="text-lg font-semibold">Archive and Usenet evidence</h2>
    @if ($fingerprintMetadata?->metadata)
        <p class="mt-1 text-sm text-base-content/70">
            Collection status: <strong>{{ ucfirst($fingerprintMetadata->status) }}</strong>
            @if ($fingerprintMetadata->collected_at)
                <span> · collected {{ $fingerprintMetadata->collected_at->toIso8601String() }}</span>
            @endif
        </p>
        <details class="mt-4">
            <summary class="cursor-pointer font-medium">Show complete parsed metadata, raw sampled headers, and provider comparisons</summary>
            <pre class="mt-3 max-h-[60rem] overflow-auto rounded-lg bg-base-200 p-4 text-xs leading-relaxed">{{ json_encode($fingerprintMetadata->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
        </details>
    @else
        <p class="mt-2 text-sm text-base-content/70">Metadata collection has not completed yet.</p>
        @if ($fingerprintMetadata?->last_error)
            <p class="mt-1 text-sm text-warning">Collection status: {{ $fingerprintMetadata->status }} ({{ $fingerprintMetadata->last_error }})</p>
        @endif
    @endif
</section>
