<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\Security\IpBlockService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AdminBlockedIpStoreRequest extends FormRequest
{
    /** Longest manual block that still has an end: ten years. */
    public const int MAX_HOURS = 87600;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('Admin');
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    public function rules(): array
    {
        return [
            'ip' => ['required', 'string', 'max:45', 'ip', $this->blockableAddress(...)],
            'hours' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_HOURS],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Hours the block lasts, or null for a permanent block.
     */
    public function hours(): ?int
    {
        $hours = $this->validated('hours');

        return $hours === null ? null : (int) $hours;
    }

    /**
     * Refuses the admin's own address and the addresses the block list never accepts.
     */
    private function blockableAddress(string $attribute, mixed $value, Closure $fail): void
    {
        $blocks = app(IpBlockService::class);
        $ip = is_string($value) ? $blocks->normalize($value) : null;

        if ($ip === null) {
            return;
        }

        if ($ip === $blocks->normalize((string) $this->ip())) {
            $fail('You cannot block the address you are connected from.');

            return;
        }

        if ($blocks->isProtected($ip)) {
            $fail('That address is private, a trusted proxy or on the allowlist, so it cannot be blocked.');
        }
    }
}
