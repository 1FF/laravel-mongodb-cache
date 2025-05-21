<?php

namespace Tests;

use ForFit\Mongodb\Cache\MongoTaggedCache;
use ForFit\Mongodb\Cache\Store;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

class MongoTaggedCacheTest extends TestCase
{
    /** @var Store|MockInterface */
    protected $storeMock;
    protected array $defaultTags = ['tagA', 'tagB'];
    protected MongoTaggedCache $taggedCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeMock = $this->mock(Store::class);
        
        // Mock getPrefix for the store, as itemKey() in Repository will call it.
        $this->storeMock->shouldReceive('getPrefix')->andReturn('test_prefix::');

        $this->taggedCache = new MongoTaggedCache($this->storeMock, $this->defaultTags);
        Event::fake();
        Carbon::setTestNow(now());
    }

    protected function itemKey(string $key): string
    {
        return 'test_prefix::' . $key;
    }

    /** @test */
    public function put_many_stores_multiple_items_and_dispatches_events()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $ttlSeconds = 60;
        $expirationTimestamp = (Carbon::now()->timestamp + $ttlSeconds) * 1000;

        $expectedDocuments = [
            [
                'key' => $this->itemKey('key1'),
                'value' => serialize('value1'),
                'expiration' => new \MongoDB\BSON\UTCDateTime($expirationTimestamp),
                'tags' => $this->defaultTags,
            ],
            [
                'key' => $this->itemKey('key2'),
                'value' => serialize('value2'),
                'expiration' => new \MongoDB\BSON\UTCDateTime($expirationTimestamp),
                'tags' => $this->defaultTags,
            ],
        ];

        $builderMock = $this->mock(\Jenssegers\Mongodb\Query\Builder::class);
        $this->storeMock->shouldReceive('table')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('insert')->once()->with($expectedDocuments)->andReturn(true);

        $result = $this->taggedCache->putMany($values, $ttlSeconds);

        $this->assertTrue($result);
        Event::assertDispatched(KeyWritten::class, function (KeyWritten $event) use ($values, $ttlSeconds) {
            return $event->key === 'key1' && $event->value === $values['key1'] && $event->seconds === $ttlSeconds;
        });
        Event::assertDispatched(KeyWritten::class, function (KeyWritten $event) use ($values, $ttlSeconds) {
            return $event->key === 'key2' && $event->value === $values['key2'] && $event->seconds === $ttlSeconds;
        });
    }

    /** @test */
    public function put_many_forgets_items_if_ttl_is_zero_or_negative()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->storeMock->shouldReceive('forget')->once()->with($this->itemKey('key1'))->andReturn(true);
        $this->storeMock->shouldReceive('forget')->once()->with($this->itemKey('key2'))->andReturn(true);

        $result = $this->taggedCache->putMany($values, 0);
        $this->assertTrue($result);

        $resultNegative = $this->taggedCache->putMany($values, -10);
        $this->assertTrue($resultNegative);

        Event::assertNotDispatched(KeyWritten::class);
    }
    
    /** @test */
    public function put_many_returns_true_for_empty_values_array()
    {
        $this->storeMock->shouldNotReceive('table'); // No DB interaction
        
        $result = $this->taggedCache->putMany([], 60);
        $this->assertTrue($result);
        Event::assertNotDispatched(KeyWritten::class);
    }

    /** @test */
    public function put_many_returns_false_if_store_insert_fails()
    {
        $values = ['key1' => 'value1'];
        $ttlSeconds = 60;
        $expirationTimestamp = (Carbon::now()->timestamp + $ttlSeconds) * 1000;
        $expectedDocument = [
            'key' => $this->itemKey('key1'),
            'value' => serialize('value1'),
            'expiration' => new \MongoDB\BSON\UTCDateTime($expirationTimestamp),
            'tags' => $this->defaultTags,
        ];

        $builderMock = $this->mock(\Jenssegers\Mongodb\Query\Builder::class);
        $this->storeMock->shouldReceive('table')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('insert')->once()->with([$expectedDocument])->andReturn(false);

        $result = $this->taggedCache->putMany($values, $ttlSeconds);

        $this->assertFalse($result);
        Event::assertNotDispatched(KeyWritten::class);
    }
    
    /** @test */
    public function put_many_returns_false_if_store_insert_throws_exception()
    {
        $values = ['key1' => 'value1'];
        $ttlSeconds = 60;
        // Definition of $expectedDocument would be identical to put_many_returns_false_if_store_insert_fails

        $builderMock = $this->mock(\Jenssegers\Mongodb\Query\Builder::class);
        $this->storeMock->shouldReceive('table')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('insert')->once()->andThrow(new \Exception('DB insert failed'));

        $result = $this->taggedCache->putMany($values, $ttlSeconds);

        $this->assertFalse($result);
        Event::assertNotDispatched(KeyWritten::class);
    }
}
