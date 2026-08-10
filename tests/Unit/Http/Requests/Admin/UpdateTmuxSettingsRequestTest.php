<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Admin;

use App\Http\Requests\Admin\UpdateTmuxSettingsRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateTmuxSettingsRequestTest extends TestCase
{
    #[DataProvider('validThreadValuesProvider')]
    public function test_thread_value_is_valid_at_each_boundary(string $field, string $value): void
    {
        $validator = $this->validator($this->validPayload([$field => $value]));

        $this->assertFalse($validator->fails(), implode(PHP_EOL, $validator->errors()->all()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validThreadValuesProvider(): iterable
    {
        foreach (self::maximums() as $field => $maximum) {
            yield $field.' minimum' => [$field, '1'];
            yield $field.' maximum' => [$field, (string) $maximum];
        }
    }

    #[DataProvider('invalidThreadValuesProvider')]
    public function test_thread_value_is_rejected_when_required_integer_or_bounds_rules_fail(string $field, mixed $value): void
    {
        $validator = $this->validator($this->validPayload([$field => $value]));

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($field));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidThreadValuesProvider(): iterable
    {
        foreach (self::maximums() as $field => $maximum) {
            yield $field.' missing' => [$field, null];
            yield $field.' zero' => [$field, '0'];
            yield $field.' negative' => [$field, '-1'];
            yield $field.' non-integer' => [$field, 'abc'];
            yield $field.' decimal' => [$field, '1.5'];
            yield $field.' over maximum' => [$field, (string) ($maximum + 1)];
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace(array_fill_keys(array_keys(self::maximums()), '1'), $overrides);
    }

    /**
     * @return array<string, int>
     */
    private static function maximums(): array
    {
        return [
            'binarythreads' => 99,
            'backfillthreads' => 99,
            'releasethreads' => 99,
            'postthreads' => 99,
            'nfothreads' => 16,
            'postthreadsnon' => 99,
            'postthreadsamazon' => 99,
            'fixnamethreads' => 16,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): Validator
    {
        $translator = new Translator(new ArrayLoader, 'en');
        $factory = new Factory($translator);

        return $factory->make($payload, (new UpdateTmuxSettingsRequest)->rules());
    }
}
