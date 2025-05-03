<?php

namespace NomeDoVendor\NomeDoPacote;

use Illuminate\Support\ServiceProvider;

class JasperRdrServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Se tiver rotas:
//        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Se tiver views:
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'nomedopacote');

        // Se tiver migrações:
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function register()
    {
        // Bindings de serviços aqui se necessário
    }
}
