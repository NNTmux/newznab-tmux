<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('title');
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('root_categories_id');
            $table->string('title');
            $table->string('description')->nullable();
        });

        DB::table('root_categories')->insert(['id' => Category::XXX_ROOT, 'title' => 'XXX']);
        // Inserted out of id order, with retired rows that releases:recategorize-xxx has not removed yet.
        DB::table('categories')->insert([
            ['id' => 6090, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'WEBDL'],
            ['id' => Category::XXX_PACK, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'Pack'],
            ['id' => 6041, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'HD Clips'],
            ['id' => 6070, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'Packs'],
            ['id' => Category::XXX_HD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'HD'],
            ['id' => Category::XXX_SD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'SD'],
            ['id' => Category::XXX_DVD, 'root_categories_id' => Category::XXX_ROOT, 'title' => 'DVD'],
        ]);
    }

    #[Test]
    public function menu_hides_retired_xxx_categories_and_orders_by_id(): void
    {
        $menu = Category::getForMenu();

        $this->assertSame(['DVD', 'SD', 'HD', 'Pack'], array_column($menu[0]['categories'], 'title'));
    }

    #[Test]
    public function menu_still_applies_user_exclusions(): void
    {
        $menu = Category::getForMenu([Category::XXX_DVD]);

        $this->assertSame(['SD', 'HD', 'Pack'], array_column($menu[0]['categories'], 'title'));
    }
}
