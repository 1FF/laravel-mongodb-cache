<?php

namespace Tests;

use ForFit\Mongodb\Cache\MongoTaggedCache;
use ForFit\Mongodb\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class StoreTest extends TestCase
{
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup the store with a real connection
        $this->store = new Store(
            DB::connection('mongodb'), 
            $this->table()
        );

        // Freeze time for consistent testing
        Carbon::setTestNow(now());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(); // Clear test now
    }

    #[Test]
    public function it_stores_an_item_in_the_cache_for_given_time(): void
    {
        // Act
        $result = $this->store->put('test-key', 'test-value', 3);

        // Assert
        $this->assertTrue($result);
        
        // Verify the item was stored
        $cacheItem = DB::connection('mongodb')
            ->table($this->table())
            ->where('key', 'test-key')
            ->first();
            
        $this->assertNotNull($cacheItem);
        $this->assertEquals(serialize('test-value'), $cacheItem->value);
    }

    #[Test]
    public function it_updates_an_item_in_the_cache_for_given_time(): void
    {
        // Setup - add initial value
        $this->store->put('test-key', 'initial-value', 3);
        
        // Act - update the value
        $result = $this->store->put('test-key', 'new-value', 3);

        // Assert
        $this->assertTrue($result);
        
        // Verify the item was updated
        $cacheItem = DB::connection('mongodb')
            ->table($this->table())
            ->where('key', 'test-key')
            ->first();
            
        $this->assertNotNull($cacheItem);
        $this->assertEquals(serialize('new-value'), $cacheItem->value);
    }

    #[Test]
    public function it_retrieves_value_from_the_cache_by_given_key(): void
    {
        // Setup - store a value
        $this->store->put('test-key', 'test-value', 3);

        // Act
        $result = $this->store->get('test-key');

        // Assert
        $this->assertEquals('test-value', $result);
    }

    #[Test]
    public function it_returns_null_if_key_does_not_exist(): void
    {
        // Act
        $result = $this->store->get('non-existent-key');

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_sets_the_tags_to_be_used(): void
    {
        // Act
        $result = $this->store->tags(['tag1', 'tag2']);

        // Assert
        $this->assertInstanceOf(MongoTaggedCache::class, $result);
        
        // Use reflection to test the tags property
        $reflection = new \ReflectionObject($result);
        $property = $reflection->getProperty('tags');
        $this->assertEquals(['tag1', 'tag2'], $property->getValue($result));
    }

    #[Test]
    public function it_deletes_all_records_with_the_given_tag(): void
    {
        // Setup
        $this->store->tags(['tag1'])->put('key1', 'value1', 60);
        $this->store->tags(['tag2'])->put('key2', 'value2', 60);
        $this->store->tags(['tag1', 'tag2'])->put('key3', 'value3', 60);

        // Act
        $this->store->flushByTags(['tag1']);
        
        // Assert
        $this->assertNull($this->store->get('key1'));
        $this->assertEquals('value2', $this->store->get('key2'));
        $this->assertNull($this->store->get('key3'));
    }

    #[Test]
    public function it_retrieves_an_items_expiration_time_by_given_key(): void
    {
        // Setup with expiration time - 2 days
        $this->store->put('test-key', 'test-value', 172800);

        // Act
        $result = $this->store->getExpiration('test-key');

        // Assert - approximately 2 days in seconds (172800)
        // Allow for small differences in timing during test execution
        $this->assertGreaterThan(172700, $result);
        $this->assertLessThan(172900, $result);
    }
}
