<?php

namespace Tests;

use ForFit\Mongodb\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;

#[RequiresPhpExtension('mongodb')]
class TaggedCacheTest extends TestCase
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
    public function it_can_store_multiple_items_with_tags(): void
    {
        // Arrange
        $taggedCache = $this->store->tags(['api', 'v1']);

        // Act
        $taggedCache->putMany([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ], 60);

        // Assert - The values are retrievable from the tagged cache
        $this->assertEquals('value1', $taggedCache->get('key1'));
        $this->assertEquals('value2', $taggedCache->get('key2'));
        $this->assertEquals('value3', $taggedCache->get('key3'));

        // Assert - They're also retrievable from the base cache
        $this->assertEquals('value1', $this->store->get('key1'));
        $this->assertEquals('value2', $this->store->get('key2'));
        $this->assertEquals('value3', $this->store->get('key3'));
    }

    #[Test]
    public function it_can_flush_by_specific_tag(): void
    {
        // Arrange - Create items with different combinations of tags
        $this->store->tags(['api'])->put('api-only', 'api-value', 60);
        $this->store->tags(['v1'])->put('v1-only', 'v1-value', 60);
        $this->store->tags(['api', 'v1'])->put('api-v1', 'both-value', 60);
        $this->store->tags(['other'])->put('other-tag', 'other-value', 60);
        $this->store->put('no-tag', 'no-tag-value', 60);

        // Act - Flush only the 'api' tag
        $this->store->flushByTags(['api']);

        // Assert - 'api' tagged items should be gone
        $this->assertNull($this->store->get('api-only'));
        $this->assertNull($this->store->get('api-v1'));

        // Assert - Other items should remain
        $this->assertEquals('v1-value', $this->store->get('v1-only'));
        $this->assertEquals('other-value', $this->store->get('other-tag'));
        $this->assertEquals('no-tag-value', $this->store->get('no-tag'));
    }

    #[Test]
    public function it_can_flush_all_tagged_items(): void
    {
        // Arrange
        $taggedCache = $this->store->tags(['api', 'v1']);
        $taggedCache->put('tagged1', 'value1', 60);
        $taggedCache->put('tagged2', 'value2', 60);

        // Add untagged item
        $this->store->put('untagged', 'untagged-value', 60);

        // Act - Flush the tagged cache
        $taggedCache->flush();

        // Assert - Tagged items should be gone
        $this->assertNull($this->store->get('tagged1'));
        $this->assertNull($this->store->get('tagged2'));

        // Assert - Untagged item should remain
        $this->assertEquals('untagged-value', $this->store->get('untagged'));
    }

    #[Test]
    public function it_can_put_forever_with_tags(): void
    {
        // Arrange
        $taggedCache = $this->store->tags(['permanent']);

        // Act
        $taggedCache->forever('permanent-tagged', 'permanent-value');

        // Assert
        $this->assertEquals('permanent-value', $taggedCache->get('permanent-tagged'));
    }

    #[Test]
    public function it_respects_cache_ttl_with_tags(): void
    {
        // Arrange - store a value with a short TTL (1 second)
        $taggedCache = $this->store->tags(['expiring']);
        $taggedCache->put('expires-soon', 'expiring-value', 1);

        // Assert - Value exists right after storing
        $this->assertEquals('expiring-value', $taggedCache->get('expires-soon'));

        // Wait for expiration
        sleep(2);

        // MongoDB TTL index runs approximately every 60 seconds
        // So we can't reliably test automatic deletion
        // Let's verify via a direct collection check if possible
        $count = DB::connection('mongodb')
            ->table($this->table())
            ->where('key', 'expires-soon')
            ->count();

        // If it's 0, great - it was removed
        // If not, we can still check the expiration time is in the past
        if ($count > 0) {
            $cacheItem = DB::connection('mongodb')
                ->table($this->table())
                ->where('key', 'expires-soon')
                ->first();

            $expireAt = $cacheItem->expiration->toDateTime()->getTimestamp();
            $this->assertLessThanOrEqual(time(), $expireAt, 'Expiration time should be in the past');
        } else {
            $this->assertTrue(true, 'Item was automatically removed by MongoDB TTL index');
        }
    }

    #[Test]
    public function it_can_increment_and_decrement_with_tags(): void
    {
        // Arrange
        $taggedCache = $this->store->tags(['counters']);
        $taggedCache->put('counter', 5, 60);

        // Act & Assert - Increment
        $this->assertEquals(8, $taggedCache->increment('counter', 3));
        $this->assertEquals(8, $taggedCache->get('counter'));

        // Act & Assert - Decrement
        $this->assertEquals(6, $taggedCache->decrement('counter', 2));
        $this->assertEquals(6, $taggedCache->get('counter'));
    }
}
