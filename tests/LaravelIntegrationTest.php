<?php

namespace Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;

#[RequiresPhpExtension('mongodb')]
class LaravelIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set mongodb as the default cache driver and configure it
        $this->app['config']->set('cache.default', 'mongodb');
        $this->app['config']->set('cache.prefix', 'laravel_cache:');

        // Ensure MongoDB cache store is defined
        $this->app['config']->set('cache.stores.mongodb', [
            'driver' => 'mongodb',
            'table' => $this->table(),
            'connection' => 'mongodb',
        ]);

        // Resolve the cache manager to register the driver
        $this->app->make('cache');

        // Clear any existing cache data
        Cache::flush();

        // Freeze time for consistent testing
        Carbon::setTestNow(now());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(); // Clear test now
    }

    #[Test]
    public function it_registers_mongodb_driver_with_laravel(): void
    {
        // Simply test if the MongoDB cache driver works
        $uniqueKey = 'mongodb-driver-test-' . uniqid('', true);
        $uniqueValue = 'test-value-' . uniqid('', true);

        // Store a value using the MongoDB driver
        Cache::driver('mongodb')->put($uniqueKey, $uniqueValue, 60);

        // Retrieve it and check if it matches
        $this->assertEquals($uniqueValue, Cache::driver('mongodb')->get($uniqueKey));
    }

    #[Test]
    public function it_works_with_laravel_cache_facade(): void
    {
        // Act
        Cache::put('facade-test', 'facade-value', 60);

        // Assert
        $this->assertEquals('facade-value', Cache::get('facade-test'));
    }

    #[Test]
    public function it_supports_cache_helper_functions(): void
    {
        // Act - Using the global cache() helper
        cache(['helper-test' => 'helper-value'], 60);

        // Assert
        $this->assertEquals('helper-value', cache('helper-test'));
    }

    #[Test]
    public function it_supports_has_method(): void
    {
        // Arrange
        Cache::put('exists-key', 'exists-value', 60);

        // Act & Assert
        $this->assertTrue(Cache::has('exists-key'));
        $this->assertFalse(Cache::has('doesnt-exist-key'));
    }

    #[Test]
    public function it_supports_add_method(): void
    {
        // Since we now call flush() in setUp, we need to ensure no conflicts
        $uniqueKey = 'add-test-' . uniqid('', true);

        // Act & Assert - Adding to non-existent key succeeds
        $this->assertTrue(Cache::add($uniqueKey, 'add-value', 60));
        $this->assertEquals('add-value', Cache::get($uniqueKey));

        // Act & Assert - Adding to existing key fails
        $this->assertFalse(Cache::add($uniqueKey, 'new-value', 60));
        $this->assertEquals('add-value', Cache::get($uniqueKey)); // Value remains unchanged
    }

    #[Test]
    public function it_supports_remember_method(): void
    {
        // Act - This should store the value
        $value = rand(1000, 9999);
        $result1 = Cache::remember('remember-test', 60, function () use ($value) {
            return 'remembered-' . $value;
        });

        // Act - This should retrieve from cache without executing callback
        $result2 = Cache::remember('remember-test', 60, function () {
            return 'different-' . rand(1000, 9999);
        });

        // Assert values match and callback wasn't executed second time
        $this->assertEquals($result1, $result2);
    }

    #[Test]
    public function it_supports_forever_method(): void
    {
        // Act
        Cache::forever('forever-key', 'forever-value');

        // Assert
        $this->assertEquals('forever-value', Cache::get('forever-key'));
    }

    #[Test]
    public function it_supports_pull_method(): void
    {
        // Arrange
        Cache::put('pull-key', 'pull-value', 60);

        // Act - Pull retrieves and removes in one operation
        $result = Cache::pull('pull-key');

        // Assert
        $this->assertEquals('pull-value', $result);
        $this->assertFalse(Cache::has('pull-key'));
    }

    #[Test]
    public function it_supports_counting_and_incrementing(): void
    {
        // Arrange
        Cache::put('count-key', 3, 60);

        // Act
        $result1 = Cache::increment('count-key');
        $result2 = Cache::increment('count-key', 2);
        $result3 = Cache::decrement('count-key');

        // Assert
        $this->assertEquals(4, $result1);
        $this->assertEquals(6, $result2);
        $this->assertEquals(5, $result3);
        $this->assertEquals(5, Cache::get('count-key'));
    }
}
