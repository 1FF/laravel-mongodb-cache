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
}
