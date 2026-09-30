<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ProfilePasswordValidationTest extends TestCase
{
    #[DataProvider('invalidCurrentPasswordProvider')]
    public function test_a_new_password_requires_the_authenticated_users_current_password(?string $currentPassword): void
    {
        Auth::setUser(new GenericUser([
            'id' => 42,
            'password' => Hash::make('Existing-password1!'),
        ]));

        $validator = Validator::make([
            'current_password' => $currentPassword,
            'password' => 'Replacement-password2!',
            'password_confirmation' => 'Replacement-password2!',
        ], $this->profileValidationRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('current_password', $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function invalidCurrentPasswordProvider(): array
    {
        return [
            'missing password' => [null],
            'incorrect password' => ['Wrong-password1!'],
        ];
    }

    public function test_a_new_password_accepts_the_authenticated_users_current_password(): void
    {
        Auth::setUser(new GenericUser([
            'id' => 42,
            'password' => Hash::make('Existing-password1!'),
        ]));

        $validator = Validator::make([
            'current_password' => 'Existing-password1!',
            'password' => 'Replacement-password2!',
            'password_confirmation' => 'Replacement-password2!',
        ], $this->profileValidationRules());

        $this->assertTrue($validator->passes());
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function profileValidationRules(): array
    {
        $controller = (new ReflectionClass(ProfileController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ProfileController::class, 'profileValidationRules');

        return $method->invoke($controller, 42);
    }
}
