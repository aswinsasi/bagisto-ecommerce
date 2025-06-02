<?php

namespace AswinSasi\BagistoApi\Providers;

use Illuminate\Support\ServiceProvider;

class BagistoApiServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load routes from this package
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
    }

    public function register()
    {
        //
    }
}
