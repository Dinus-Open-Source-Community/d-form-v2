<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Blade @vite must not depend on built assets: CI runs tests without npm build output.
        $this->withoutVite();

        // Feature tests use $this->post() / delete() without simulating a browser session token.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
