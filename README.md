# Laravel Mongodb Cache driver

A MongoDB cache driver for Laravel

| **Laravel<br/>Version** | **Package<br/>Version** | **Install using<br/>this command**                 |
|-------------------------|-------------------------|----------------------------------------------------|
| 11.x                    | 7.x.x                   | composer require 1ff/laravel-mongodb-cache:^7.0    |
| 10.x                    | 6.x.x                   | composer require 1ff/laravel-mongodb-cache:^6.0    |
| 9.x                     | 5.x.x                   | composer require 1ff/laravel-mongodb-cache:^5.0    |
| 8.x                     | 4.x.x                   | composer require 1ff/laravel-mongodb-cache:^4.1    |
| 7.x                     | 3.x.x                   | composer require 1ff/laravel-mongodb-cache:^3.1    |
| 5.8.x, 6.x              | 2.12.x                  | composer require 1ff/laravel-mongodb-cache:~2.12.0 |
| 5.7.x                   | 2.11.x                  | composer require 1ff/laravel-mongodb-cache:~2.11.0 |

Installation
------------

Install using composer:

    composer require 1ff/laravel-mongodb-cache

If you are using Laravel older than 5.5 add the service provider in `config/app.php`:

    'ForFit\Mongodb\Cache\ServiceProvider::class',
    
Add the mongodb cache store in `config/cache.php`

    'stores' => [
        ...

        'mongodb' => [
            'driver' => 'mongodb',
            'table' => 'cache', // name it as you wish
            'connection' => 'mongodb',
        ],
    ],

Add the mongodb database connection in `config/database.php`

    'connections' => [
        ...

        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_DSN'),
            'database' => env('MONGODB_DATABASE'),
        ],
    ],

Update your .env file and change the `CACHE_DRIVER` to mongodb

    CACHE_DRIVER=mongodb
    MONGODB_DSN=mongodb://localhost:27017/laravel
    MONGODB_DATABASE=laravel

Advantages
----------

* This driver uses the [MongoDB TTL indexes](https://docs.mongodb.com/manual/core/index-ttl/) meaning when a cache key expires it will be automatically deleted.
* This way, the collection's size will remain around the size you expect and won't get falsely filled with unused data.
* The package automatically adds a migration which creates the index by running a mongodb command.
* This package also registers two new commands:

        php artisan mongodb:cache:index

    and

        php artisan mongodb:cache:dropindex

Testing
-------

This package includes tests that interact with a real MongoDB database to verify the functionality of the cache driver. The tests require a MongoDB instance to run successfully.

To run the tests:

1. Make sure you have MongoDB installed and running on your local machine
2. The test configuration is set in `phpunit.xml`:

```xml
<php>
    <env name="MONGODB_HOST" value="127.0.0.1"/>
    <env name="MONGODB_PORT" value="27017"/>
    <env name="MONGODB_DATABASE" value="laravel_mongodb_cache_test"/>
    <env name="MONGODB_USERNAME" value=""/>
    <env name="MONGODB_PASSWORD" value=""/>
</php>
```

3. Run the tests with:

```
composer test
```

or

```
vendor/bin/phpunit
```

### Test Structure

The test suite is organized into multiple files to test various aspects of the MongoDB cache driver:

- `StoreTest.php`: Tests basic Store class functionality (get, put, forget, flush)
- `AdvancedCacheFeaturesTest.php`: Tests advanced Store features like increment/decrement, forever storage, and handling arrays/objects
- `TaggedCacheTest.php`: Tests tagged cache functionality
- `LaravelIntegrationTest.php`: Tests integration with Laravel's Cache facade

Some functionality (like increment/decrement, forever storage) is intentionally tested in multiple contexts:
1. At the low-level Store implementation
2. Through Laravel's Cache facade
3. With tagged cache operations

This multi-layered approach ensures that all feature functionality works correctly at all levels of integration.

GitHub Actions
-------------

The package includes GitHub Actions workflows that automatically run tests against a MongoDB service. The MongoDB service is started as part of the CI workflow, ensuring tests are executed in an environment with a real MongoDB database.

Warning
-------

This cache driver is not compatible with other cache drivers because it encodes the data differently.
If you are using another mongodb cache driver at the moment make sure you set a new collection for this one.

Enjoy!
------
