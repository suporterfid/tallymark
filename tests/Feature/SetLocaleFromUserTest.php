<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetLocaleFromUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_render_in_the_users_saved_locale(): void
    {
        $user = User::query()->create([
            'name' => 'Locale User',
            'email' => 'locale-user@example.test',
            'password' => Hash::make('password'),
            'locale' => 'pt-BR',
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/locale', ['locale' => 'fr']);

        $response->assertStatus(422);
        $this->assertStringContainsString('selecionado', $response->json('errors.locale.0'));
    }

    public function test_validation_errors_default_to_english_without_a_saved_locale(): void
    {
        $user = User::query()->create([
            'name' => 'Default Locale User',
            'email' => 'default-locale-user@example.test',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/locale', ['locale' => 'fr']);

        $response->assertStatus(422);
        $this->assertStringContainsString('selected', $response->json('errors.locale.0'));
    }
}
