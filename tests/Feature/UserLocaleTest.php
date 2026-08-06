<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_users_saved_locale(): void
    {
        $user = User::query()->create([
            'name' => 'Locale User',
            'email' => 'locale-user@example.test',
            'password' => Hash::make('password'),
            'locale' => 'pt-BR',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.locale', 'pt-BR');
    }

    public function test_me_defaults_locale_to_english_without_an_explicit_value(): void
    {
        $user = User::query()->create([
            'name' => 'Default Locale User',
            'email' => 'default-locale-user@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en');
    }

    public function test_user_can_update_locale(): void
    {
        $user = User::query()->create([
            'name' => 'Switching User',
            'email' => 'switching-user@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/locale', ['locale' => 'pt-BR'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'pt-BR');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'pt-BR',
        ]);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Invalid Locale User',
            'email' => 'invalid-locale-user@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/locale', ['locale' => 'fr'])
            ->assertStatus(422);
    }
}
