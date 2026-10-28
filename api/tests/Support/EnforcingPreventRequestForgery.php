<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Laravel 13 skips CSRF verification whenever unit tests are running
 * (PreventRequestForgery::handle() → runningUnitTests()). This test-only subclass turns that
 * shortcut off, so CsrfEnforcementTest exercises the real token check. It is only ever swapped in
 * through config('sanctum.middleware.validate_csrf_token') inside that test.
 */
class EnforcingPreventRequestForgery extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
