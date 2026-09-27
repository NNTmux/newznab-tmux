<?php

declare(strict_types=1);

namespace App\Services\Mcp;

class SensitiveValueRedactor
{
    public function redact(string $value): string
    {
        $value = preg_replace(
            '/-----BEGIN [^-\r\n]*PRIVATE KEY-----.*?-----END [^-\r\n]*PRIVATE KEY-----/s',
            '[REDACTED PRIVATE KEY]',
            $value,
        ) ?? $value;

        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('#\b([a-z][a-z0-9+.-]*://)[^\s/:@]+:[^\s/@]+@#i', '$1[REDACTED]@', $value) ?? $value;
        $value = preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key|authorization)(\s*["\']?\s*(?:=>|=|:)\s*["\']?)([^\s,"\'}]+)/i',
            '$1$2[REDACTED]',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/\b([A-Z][A-Z0-9_]*(?:KEY|TOKEN|SECRET|PASSWORD|PASSWD))=([^\s]+)/',
            '$1=[REDACTED]',
            $value,
        ) ?? $value;

        return $value;
    }

    public function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redact($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = $this->isSensitiveKey((string) $key)
                ? '[REDACTED]'
                : $this->redactValue($item);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|passwd|token|secret|api[_-]?key|authorization|credential)/i', $key) === 1;
    }
}
