<?php

namespace Tests;

use ForFit\Mongodb\Cache\ServiceProvider as MongoDbCacheServiceProvider;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Tests\Overrides\Builder;

abstract class TestCase extends Orchestra
{
    private $table = 'cache_test';
    private $connectionInterface;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);
        $this->connectionInterface = $this->initializeConnection();
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
        ];
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.stores.mongodb', [
            'driver' => 'mongodb',
            'table' => $this->table,
            'connection' => 'mongodb',
        ]);

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * @return \Illuminate\Database\Connection|\Mockery\LegacyMockInterface|\Mockery\MockInterface|null
     */
    protected function connection()
    {
        return $this->connectionInterface->getMock();
    }

    /**
     * @return string
     */
    protected function table(): string
    {
        return $this->table;
    }

    /**
     * Set up the database.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $app['db']->connection()->getSchemaBuilder()->create($this->table, function (Blueprint $table) {
            $table->increments('_id');
            $table->dateTimeTz('expiration')->nullable();
            $table->string('key');
            $table->string('value');
            $table->json('tags')->nullable();
        });
    }

    /**
     * @return \Mockery\Expectation|\Mockery\ExpectationInterface|\Mockery\HigherOrderMessage
     */
    protected function initializeConnection()
    {
        $builder = app(Builder::class, ['connection' => $this->getConnection()]);
        $builder->from = $this->table;

        return $this->spy(ConnectionInterface::class)
            ->shouldReceive('table')
            ->andReturn($builder);
    }

    /**
     * Assert the object has given property.
     *
     * @param $expected
     * @param $property
     * @param $object
     * @param string $message
     * @return void
     * @throws \ReflectionException
     */
    protected function assertPropertySame($expected, $property, $object, string $message = '')
    {
        $reflectedClass = new \ReflectionClass($object);
        $reflection = $reflectedClass->getProperty($property);
        $reflection->setAccessible(true);

        $this->assertSame($expected, $reflection->getValue($object), $message);
    }
}
