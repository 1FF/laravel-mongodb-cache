<?php

namespace Tests;

use ForFit\Mongodb\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;

#[RequiresPhpExtension('mongodb')]
class AdvancedCacheFeaturesTest extends TestCase
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
    public function it_can_increment_numeric_values(): void
    {
        // Arrange
        $this->store->put('counter', 5, 60);

        // Act
        $result = $this->store->increment('counter', 3);

        // Assert
        $this->assertEquals(8, $result);
        $this->assertEquals(8, $this->store->get('counter'));
    }

    #[Test]
    public function it_can_decrement_numeric_values(): void
    {
        // Arrange
        $this->store->put('counter', 10, 60);

        // Act
        $result = $this->store->decrement('counter', 3);

        // Assert
        $this->assertEquals(7, $result);
        $this->assertEquals(7, $this->store->get('counter'));
    }

    #[Test]
    public function it_returns_false_when_incrementing_non_existent_key(): void
    {
        // Act
        $result = $this->store->increment('non-existent', 1);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_can_store_items_forever(): void
    {
        // Act
        $result = $this->store->forever('permanent-key', 'permanent-value');

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('permanent-value', $this->store->get('permanent-key'));

        // Verify long expiration time (10 years minimum)
        $expiration = $this->store->getExpiration('permanent-key');
        $this->assertNotNull($expiration);
        $this->assertGreaterThanOrEqual(315360000, $expiration); // >= 10 years
    }

    #[Test]
    public function it_can_store_and_retrieve_arrays(): void
    {
        // Arrange
        $data = ['name' => 'Test', 'values' => [1, 2, 3]];

        // Act
        $this->store->put('array-data', $data, 60);

        // Assert
        $result = $this->store->get('array-data');
        $this->assertEquals($data, $result);
        $this->assertIsArray($result);
        $this->assertEquals([1, 2, 3], $result['values']);
    }

    #[Test]
    public function it_can_store_and_retrieve_objects(): void
    {
        // Arrange
        $data = new \stdClass();
        $data->name = 'Test Object';
        $data->value = 123;

        // Act
        $this->store->put('object-data', $data, 60);

        // Assert
        $result = $this->store->get('object-data');
        $this->assertEquals($data, $result);
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('Test Object', $result->name);
        $this->assertEquals(123, $result->value);
    }

    #[Test]
    public function it_can_forget_specific_keys(): void
    {
        // Arrange
        $this->store->put('forget-me', 'value', 60);
        $this->store->put('keep-me', 'value', 60);

        // Assert item exists before forgetting
        $this->assertEquals('value', $this->store->get('forget-me'));

        // Act
        $result = $this->store->forget('forget-me');

        // Assert
        $this->assertTrue($result);
        $this->assertNull($this->store->get('forget-me'));
        $this->assertEquals('value', $this->store->get('keep-me'));
    }

    #[Test]
    public function it_can_flush_entire_cache(): void
    {
        // Arrange
        $this->store->put('key1', 'value1', 60);
        $this->store->put('key2', 'value2', 60);
        $this->store->tags(['tag1'])->put('key3', 'value3', 60);

        // Act
        $result = $this->store->flush();

        // Assert
        $this->assertTrue($result);
        $this->assertNull($this->store->get('key1'));
        $this->assertNull($this->store->get('key2'));
        $this->assertNull($this->store->get('key3'));

        // Verify MongoDB collection is empty
        $count = DB::connection('mongodb')
            ->table($this->table())
            ->count();

        $this->assertEquals(0, $count);
    }
}
