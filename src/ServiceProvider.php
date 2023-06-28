<?php

namespace ForFit\Mongodb\Cache;

use ForFit\Mongodb\Cache\Console\Commands\MongodbCacheIndex;
use ForFit\Mongodb\Cache\Console\Commands\MongodbCacheIndexTags;
use ForFit\Mongodb\Cache\Console\Commands\MongodbCacheDropIndex;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider as ParentServiceProvider;

class ServiceProvider extends ParentServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function boot()
    {
        // Extend the mongodb driver by connecting the cache repository directly
        Cache::extend('mongodb', function ($app) {
            $config = config('cache')['stores']['mongodb'];
            $prefix = config('cache')['prefix'];
            $connection = $app['db']->connection($config['connection'] ?? null);

            return Cache::repository(new Store($connection, $config['table'], $prefix));
        });

        // register the cache indexing commands if running in cli
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

            $this->commands([
                MongodbCacheIndex::class,
                MongodbCacheIndexTags::class,
                MongodbCacheDropIndex::class,
            ]);
        }
    }
}
