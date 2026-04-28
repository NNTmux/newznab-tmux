<?php

declare(strict_types=1);

namespace App\Support\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Data Transfer Object for Steam Price information.
 */
#[TypeScript]
final class SteamPriceData extends Data
{
    public function __construct(
        public string $currency,
        public float $initial,
        public float $final,
        #[MapOutputName('discount_percent')]
        public int $discountPercent,
        #[MapOutputName('formatted')]
        public ?string $formattedPrice = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
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

    public function isOnSale(): bool
    {
        return $this->discountPercent > 0;
    }

    public function isFree(): bool
    {
        return $this->final <= 0;
    }

    public function getSavings(): float
    {
        return max(0, $this->initial - $this->final);
    }

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
}
