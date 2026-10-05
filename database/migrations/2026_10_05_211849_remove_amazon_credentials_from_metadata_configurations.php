<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array COLUMNS = ['amazon_public_key', 'amazon_private_key', 'amazon_associate_tag'];

    public function up(): void
    {
        $columns = array_values(array_intersect(self::COLUMNS, Schema::getColumnListing('metadata_configurations')));

        if ($columns !== []) {
            Schema::table('metadata_configurations', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_diff(self::COLUMNS, Schema::getColumnListing('metadata_configurations')));

        if ($columns !== []) {
            Schema::table('metadata_configurations', function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
            });
        }
    }
};
