<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'searchname' => $this->faker->unique()->name(),
            'fromname' => $this->faker->unique()->safeEmail(),
            'postdate' => $this->faker->date(),
            'adddate' => $this->faker->date(),
            'guid' => Str::uuid()->toString(),
            'leftguid' => fn (array $attributes): string => $attributes['guid'][0],
            'categories_id' => '2080',
            'nzbstatus' => 1,
            'passwordstatus' => 0,
            'isrenamed' => 1,
        ];
    }
}
