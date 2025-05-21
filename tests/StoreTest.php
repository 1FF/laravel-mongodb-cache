<?php

namespace Tests;

use ForFit\Mongodb\Cache\MongoTaggedCache;
use ForFit\Mongodb\Cache\Store;
use Illuminate\Support\Carbon;
use Tests\Models\Cache;

class StoreTest extends TestCase
{
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new Store($this->connection(), $this->table());

        // Freeze time.
        Carbon::setTestNow(now());
    }

    /** @test */
    public function it_stores_an_item_in_the_cache_for_given_time()
    {
        // Act
        $sut = $this->store->put('test-key', 'test-value', 3);

        // Assert
        $this->assertTrue($sut);
        $this->assertDatabaseHas($this->table(), [
            'key' => 'test-key',
            'value' => serialize('test-value'),
            'expiration' => (now()->timestamp + 3) * 1000,
            'tags' => '[]'
        ]);
    }

    /** @test */
    public function it_updates_an_item_in_the_cache_for_given_time()
    {
        Cache::create([
            'key' => 'test-key',
            'value' => serialize('fake-value')
        ]);

        // Act
        $sut = $this->store->put('test-key', 'new-value', 3);

        // Assert
        $this->assertTrue($sut);
        $this->assertDatabaseHas($this->table(), [
            'key' => 'test-key',
            'value' => serialize('new-value'),
            'expiration' => (now()->timestamp + 3) * 1000,
            'tags' => '[]'
        ]);
    }

    /** @test */
    public function it_retrieves_value_from_the_cache_by_given_key()
    {
        // Arrange
        Cache::create([
            'key' => 'test-key',
            'value' => serialize('test-value')
        ]);

        // Act
        $sut = $this->store->get('test-key');

        // Assert
        $this->assertIsString($sut);
        $this->assertEquals('test-value', $sut);
    }

    /** @test */
    public function it_returns_null_if_key_does_not_exist()
    {
        // Act
        $sut = $this->store->get('test-key');

        // Assert
        $this->assertNull($sut);
    }

    /** @test */
    public function it_sets_the_tags_to_be_used()
    {
        // Act
        $sut = $this->store->tags(['tag1', 'tag2']);

        // Assert
        $this->assertInstanceOf(MongoTaggedCache::class, $sut);
        $this->assertPropertySame(['tag1', 'tag2'], 'tags', $sut);
    }

    /** @test */
    public function it_deletes_all_records_with_the_given_tag()
    {
        // Arrange
        Cache::create([
            'key' => 'test-key-1',
            'value' => serialize('test-value-1'),
            'tags' => ['tag1']
        ]);

        Cache::create([
            'key' => 'test-key-2',
            'value' => serialize('test-value-2'),
            'tags' => ['tag2']
        ]);

        // Act
        $this->store->flushByTags(['tag1']);

        // Assert
        $this->assertDatabaseMissing($this->table(), [
            'key' => 'test-key-1',
            'value' => serialize('test-value-1'),
        ]);
        $this->assertDatabaseHas($this->table(), [
            'key' => 'test-key-2',
            'value' => serialize('test-value-2'),
        ]);
    }

    /** @test */
    public function it_retrieves_an_items_expiration_time_by_given_key()
    {
        // Arrange
        Cache::create([
            'key' => 'test-key',
            'value' => serialize('test-value'),
            'expiration' => now()->addDays(2)
        ]);

        // Act
        $sut = $this->store->getExpiration('test-key');

        // Assert
        $this->assertEquals(172800, $sut); // 2 days in seconds.
    }

    /** @test */
    public function get_returns_null_if_item_is_expired()
    {
        // Arrange
        $key = 'expired-key';
        $value = 'expired-value';
        $ttlSeconds = 1;

        $this->store->put($key, $value, $ttlSeconds);

        // Act
        Carbon::setTestNow(now()->addSeconds($ttlSeconds + 1)); // Advance time past expiration
        $retrievedValue = $this->store->get($key);

        // Assert
        $this->assertNull($retrievedValue);
    }

    /** @test */
    public function increment_successfully_increments_value()
    {
        $this->store->put('counter', 1, 10);
        $newValue = $this->store->increment('counter', 1);
        $this->assertEquals(2, $newValue);
        $this->assertEquals(2, $this->store->get('counter'));
    }

    /** @test */
    public function decrement_successfully_decrements_value()
    {
        $this->store->put('counter', 5, 10);
        $newValue = $this->store->decrement('counter', 2);
        $this->assertEquals(3, $newValue);
        $this->assertEquals(3, $this->store->get('counter'));
    }

    /** @test */
    public function increment_returns_false_if_key_does_not_exist()
    {
        $this->assertFalse($this->store->increment('non-existent-key'));
    }

    /** @test */
    public function decrement_returns_false_if_key_does_not_exist()
    {
        $this->assertFalse($this->store->decrement('non-existent-key'));
    }

    /** @test */
    public function increment_returns_false_if_value_is_not_numeric()
    {
        $this->store->put('not-numeric', 'i-am-string', 10);
        $this->assertFalse($this->store->increment('not-numeric'));
    }

    /** @test */
    public function decrement_returns_false_if_value_is_not_numeric()
    {
        $this->store->put('not-numeric', ['i-am' => 'array'], 10);
        $this->assertFalse($this->store->decrement('not-numeric'));
    }

    /** @test */
    public function increment_returns_false_on_optimistic_lock_failure()
    {
        $key = 'optimistic-lock-increment';
        $initialValue = 1;
        $serializedInitialValue = serialize($initialValue);
        $prefix = $this->store->getPrefix(); // Get prefix from original store instance

        $connectionMock = $this->mock(\Illuminate\Database\ConnectionInterface::class);
        $builderMock = $this->mock(\Jenssegers\Mongodb\Query\Builder::class);

        // Use a fresh Store instance with the mocked connection for the test
        $storeWithMock = new Store($connectionMock, $this->table(), $prefix);

        $connectionMock->shouldReceive('table')->with($this->table())->times(2)->andReturn($builderMock); // one for item, one for getExpiration

        // Mock for the main item fetch in incrementOrDecrement
        $builderMock->shouldReceive('where')->with('key', $prefix . $key)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('first')->once()->ordered()->andReturn([
            'key' => $prefix . $key,
            'value' => $serializedInitialValue,
            'expiration' => new \MongoDB\BSON\UTCDateTime((Carbon::now()->addMinutes(10)->timestamp) * 1000),
            'tags' => [],
        ]);

        // Mock for the getExpiration call
        $builderMock->shouldReceive('where')->with('key', $prefix . $key)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('first')->once()->ordered()->andReturn([
            'key' => $prefix . $key, // Not strictly needed by getExpiration but good for consistency
            'value' => $serializedInitialValue, // Not strictly needed by getExpiration
            'expiration' => new \MongoDB\BSON\UTCDateTime((Carbon::now()->addMinutes(10)->timestamp) * 1000),
        ]);

        // Expect 'update' to be called with the *old* serialized value, but simulate it returning 0 rows affected
        $builderMock->shouldReceive('where')->with('value', $serializedInitialValue)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('update')->with(
            \Mockery::on(function ($data) use ($initialValue) {
                return unserialize($data['value']) === ($initialValue + 1); // Check new value is correct
            })
        )->once()->ordered()->andReturn(0); // Simulate 0 rows affected - CAS failure

        $result = $storeWithMock->increment($key, 1);
        $this->assertFalse($result);
    }

    /** @test */
    public function decrement_returns_false_on_optimistic_lock_failure()
    {
        $key = 'optimistic-lock-decrement';
        $initialValue = 5;
        $serializedInitialValue = serialize($initialValue);
        $prefix = $this->store->getPrefix();

        $connectionMock = $this->mock(\Illuminate\Database\ConnectionInterface::class);
        $builderMock = $this->mock(\Jenssegers\Mongodb\Query\Builder::class);

        $storeWithMock = new Store($connectionMock, $this->table(), $prefix);

        $connectionMock->shouldReceive('table')->with($this->table())->times(2)->andReturn($builderMock);

        // Mock for the main item fetch in incrementOrDecrement
        $builderMock->shouldReceive('where')->with('key', $prefix . $key)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('first')->once()->ordered()->andReturn([
            'key' => $prefix . $key,
            'value' => $serializedInitialValue,
            'expiration' => new \MongoDB\BSON\UTCDateTime((Carbon::now()->addMinutes(10)->timestamp) * 1000),
            'tags' => [],
        ]);

        // Mock for the getExpiration call
        $builderMock->shouldReceive('where')->with('key', $prefix . $key)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('first')->once()->ordered()->andReturn([
            'expiration' => new \MongoDB\BSON\UTCDateTime((Carbon::now()->addMinutes(10)->timestamp) * 1000),
        ]);

        // Expect 'update' for CAS
        $builderMock->shouldReceive('where')->with('value', $serializedInitialValue)->once()->ordered()->andReturnSelf();
        $builderMock->shouldReceive('update')->with(
            \Mockery::on(function ($data) use ($initialValue) {
                return unserialize($data['value']) === ($initialValue - 1);
            })
        )->once()->ordered()->andReturn(0); // Simulate 0 rows affected

        $result = $storeWithMock->decrement($key, 1);
        $this->assertFalse($result);
    }

    /** @test */
    public function flush_by_tags_deletes_items_matching_any_of_the_given_tags()
    {
        // Arrange
        Cache::create(['key' => 'item1', 'value' => serialize('val1'), 'tags' => ['tag1', 'tag2']]);
        Cache::create(['key' => 'item2', 'value' => serialize('val2'), 'tags' => ['tag2', 'tag3']]);
        Cache::create(['key' => 'item3', 'value' => serialize('val3'), 'tags' => ['tag3', 'tag4']]);
        Cache::create(['key' => 'item4', 'value' => serialize('val4'), 'tags' => ['tag5']]); // No shared tags with flush list

        // Act
        $this->store->flushByTags(['tag1', 'tag4', 'tagX']); // tagX does not exist

        // Assert
        $this->assertDatabaseMissing($this->table(), ['key' => 'item1']); // Matches tag1
        $this->assertDatabaseHas($this->table(), ['key' => 'item2']);    // Matches tag2 (not in flush list)
        $this->assertDatabaseMissing($this->table(), ['key' => 'item3']); // Matches tag4
        $this->assertDatabaseHas($this->table(), ['key' => 'item4']);    // Matches tag5 (not in flush list)
    }
}
