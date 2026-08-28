<?php

namespace Tests\Feature\Feature\Api\V1\Auth;

use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
