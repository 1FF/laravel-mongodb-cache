<?php

namespace Tests;

use ForFit\Mongodb\Cache\ServiceProvider as MongoDbCacheServiceProvider;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private string $table = 'cache_test';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear the cache collection before each test
        $this->flushCache();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            MongoDbCacheServiceProvider::class,
            MongoDBServiceProvider::class
        ];
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.stores.mongodb', [
            'driver' => 'mongodb',
            'table' => $this->table,
            'connection' => 'mongodb',
        ]);

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'host' => env('MONGODB_HOST', '127.0.0.1'),
            'port' => env('MONGODB_PORT', 27017),
            'database' => env('MONGODB_DATABASE', 'laravel_mongodb_cache_test'),
            'username' => env('MONGODB_USERNAME', ''),
            'password' => env('MONGODB_PASSWORD', ''),
            'options' => [
                'database' => env('MONGODB_AUTHENTICATION_DATABASE', 'admin'),
            ],
        ]);
    }

    /**
     * @return string
     */
    protected function table(): string
    {
        return $this->table;
    }

    /**
     * Flush the cache collection
     */
    protected function flushCache(): void
    {
        DB::connection('mongodb')
            ->table($this->table)
            ->delete();
    }
}
