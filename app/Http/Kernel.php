<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

/**
 * Laravel 12 Note:
 * 
 * In Laravel 12, middleware configuration has been moved to bootstrap/app.php.
 * This Kernel class is kept for backward compatibility but is no longer used
 * for middleware registration. All middleware should be configured in bootstrap/app.php.
 * 
 * You can safely remove the properties below as they are not used anymore.
 * However, keeping this file ensures compatibility with packages that may still
 * reference the Kernel class.
 */
class Kernel extends HttpKernel
{
    // In Laravel 12, middleware is configured in bootstrap/app.php
    // This class is kept for backward compatibility only
}
