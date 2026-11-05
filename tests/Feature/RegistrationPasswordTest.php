<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_accepts_short_password_when_confirmation_matches(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mahasiswa Test',
            'nim' => '12345678',
            'email' => 'mahasiswa@example.test',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_registration_still_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mahasiswa Test',
            'nim' => '12345678',
            'email' => 'mahasiswa@example.test',
            'password' => 'abc',
            'password_confirmation' => 'xyz',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
