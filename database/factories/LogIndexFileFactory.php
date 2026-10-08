<?php

namespace Database\Factories;

use App\Models\LogIndexFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogIndexFile>
 */
class LogIndexFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => fake()->unique()->lexify('????????').'.log',
            'device' => 2049,
            'inode' => fake()->unique()->numberBetween(1_000, 9_999_999),
            'head_hash' => sha1(''),
            'head_length' => 0,
            'structured' => true,
            'indexed_offset' => 0,
            'indexed_line' => 0,
            'indexed_size' => 0,
            'indexed_at' => null,
        ];
    }

    /**
     * A file indexed up to `$bytes`.
     */
    public function indexedTo(int $bytes, int $lines = 0): static
    {
        return $this->state(fn (): array => [
            'indexed_offset' => $bytes,
            'indexed_line' => $lines,
            'indexed_size' => $bytes,
            'indexed_at' => now(),
        ]);
    }
}
