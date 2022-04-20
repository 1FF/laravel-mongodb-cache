# Laravel Mongodb Cache driver

A MongoDB cache driver for Laravel

| **Laravel<br/>Version** | **Package<br/>Version** | **Install using<br/>this command**                 |
|-------------------------|-------------------------|----------------------------------------------------|
| 5.7.x                   | 2.11.x                  | composer require 1ff/laravel-mongodb-cache:~2.11.0 |
| 5.8.x, 6.x              | 2.12.x                  | composer require 1ff/laravel-mongodb-cache:~2.12.0 |
| 7.x                     | 3.x.x                   | composer require 1ff/laravel-mongodb-cache:^3.1    |
| 8.x                     | 4.x.x                   | composer require 1ff/laravel-mongodb-cache:^4.1    |
| 9.x                     | 5.x.x                   | Comming soon                                       |

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
    
Update your .env file and change the `CACHE_DRIVER` to mongodb

    CACHE_DRIVER=mongodb

Advantages
----------

* This driver uses the [MongoDB TTL indexes](https://docs.mongodb.com/manual/core/index-ttl/) meaning when a cache key expires it will be automatically deleted.
* This way, the collection's size will remain around the size you expect and won't get falsely filled with unused data.
* The package automatically adds a migration which creates the index by running a mongodb command.
* This package also registers two new commands:

        php artisan mongodb:cache:index

    and

        php artisan mongodb:cache:dropindex

Warning
-------

This cache driver is not compatible with other cache drivers because it encodes the data differently.
If you are using another mongodb cache driver at the moment make sure you set a new collection for this one.

Enjoy!
------
