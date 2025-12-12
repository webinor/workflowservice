<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
          if (!Type::hasType('enum')) {
        Type::addType('enum', \Doctrine\DBAL\Types\StringType::class);
    }

    // Optionnel mais recommandé pour éviter d'autres erreurs
    Schema::defaultStringLength(191);
    }
}
