<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
dd("hjh");
class VerifyCsrfToken extends Middleware
{ 
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        'custom-api/*',  //Exclude your Bagisto custom API routes from CSRF
    ];
}
