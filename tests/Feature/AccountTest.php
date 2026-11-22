<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_nickname_survives_a_new_login(): void
    {
        $credentials = ['email' => 'student@example.test', 'password' => 'tennis-pass'];
        $this->post('/register', $credentials + ['nickname' => '小王', 'password_confirmation' => 'tennis-pass'])->assertSessionHasNoErrors();
        $this->post('/profile', ['nickname' => '小林'])->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', $credentials)->assertRedirect('/');
        $this->get('/profile')->assertSee('小林');
    }
}
