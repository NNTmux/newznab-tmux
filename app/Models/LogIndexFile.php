<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LogIndexFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Indexing progress of one log file in the Manticore log index.
 *
 * A file is identified by device + inode (so size-rotated files keep their progress when renamed);
 * the hash of its first `head_length` bytes guards against inode reuse.
 *
 * @property int $id
 * @property string $path
 * @property int $device
 * @property int $inode
 * @property string $head_hash
 * @property int $head_length
 * @property bool $structured
 * @property int $indexed_offset
 * @property int $indexed_line
 * @property int $indexed_size
 * @property CarbonImmutable|null $indexed_at
 */
class LogIndexFile extends Model
{
    /** @use HasFactory<LogIndexFileFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @var array<string, int|bool>
     */
    protected $attributes = [
        'structured' => true,
        'indexed_offset' => 0,
        'indexed_line' => 0,
        'indexed_size' => 0,
    ];

    protected function casts(): array
    {
        return [
            'device' => 'integer',
            'inode' => 'integer',
            'head_length' => 'integer',
            'structured' => 'boolean',
            'indexed_offset' => 'integer',
            'indexed_line' => 'integer',
            'indexed_size' => 'integer',
            'indexed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Forget all indexing progress so the file is indexed again from the start.
     */
    public function resetProgress(): void
    {
        $this->forceFill([
            'indexed_offset' => 0,
            'indexed_line' => 0,
            'indexed_size' => 0,
            'indexed_at' => null,
        ])->save();
    }
}
