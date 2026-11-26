<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_homepage_displays_environment_message_and_appconfig_greeting(): void
    {
        config(['app.display_message' => 'Environment message for test']);
        Http::fake([
            'localhost:2772/*' => Http::response(['GREETING' => 'AppConfig greeting for test']),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Environment message for test')
            ->assertSee('AppConfig greeting for test');
    }
}
