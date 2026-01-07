<?php

declare(strict_types=1);

namespace App\Support\DTOs;

/**
 * Data Transfer Object for Steam Price information.
 */
final readonly class SteamPriceData
{
    public function __construct(
        public string $currency,
        public float $initial,
        public float $final,
        public int $discountPercent,
        public ?string $formattedPrice = null,
    ) {}

    /**
     * Create from Steam API price_overview response.
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? 'USD',
            initial: ($data['initial'] ?? 0) / 100,
            final: ($data['final'] ?? 0) / 100,
            discountPercent: $data['discount_percent'] ?? 0,
            formattedPrice: $data['final_formatted'] ?? null,
        );
    }

    /**
     * Create a free price instance.
     */
    public static function free(): self
    {
        return new self(
            currency: 'USD',
            initial: 0.0,
            final: 0.0,
            discountPercent: 0,
            formattedPrice: 'Free',
        );
    }

    /**
     * Check if currently on sale.
     */
    public function isOnSale(): bool
    {
        return $this->discountPercent > 0;
    }

    /**
     * Check if free.
     */
    public function isFree(): bool
    {
        return $this->final <= 0;
    }

    /**
     * Get savings amount.
     */
    public function getSavings(): float
    {
        return max(0, $this->initial - $this->final);
    }

    /**
     * Get formatted display price.
     */
    public function getDisplayPrice(): string
    {
        if ($this->formattedPrice !== null) {
            return $this->formattedPrice;
        }

        if ($this->isFree()) {
            return 'Free';
        }

        return sprintf('%s %.2f', $this->currency, $this->final);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'initial' => $this->initial,
            'final' => $this->final,
            'discount_percent' => $this->discountPercent,
            'formatted' => $this->formattedPrice,
        ];
    }
}
