<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_path_is_not_exposed(): void
    {
        $response = $this->get('/');

        $response->assertStatus(404);
    }
}
