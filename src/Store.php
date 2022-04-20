<?php

namespace ForFit\Mongodb\Cache;

use Closure;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Cache\Store as StoreInterface;
use Jenssegers\Mongodb\Query\Builder;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\BulkWriteException;

class Store implements StoreInterface
{
    use InteractsWithTime;
    use RetrievesMultipleKeys;

    /**
     * The database connection instance.
     *
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * The name of the cache table.
     *
     * @var string
     */
    protected $table;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Create a new database store.
     *
     * @param ConnectionInterface $connection
     * @param string $table
     * @param string $prefix
     * @return void
     */
    public function __construct(ConnectionInterface $connection, string $table, string $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    public function get($key)
    {
        $cacheData = $this->table()->where('key', $this->getKeyWithPrefix($key))->first();

        return $cacheData ? unserialize($cacheData['value']) : null;
    }

    /**
     * @inheritDoc
     */
    public function put($key, $value, $seconds, $tags = [])
    {
        $expiration = ($this->currentTime() + (int)$seconds) * 1000;

        try {
            return (bool)$this->table()->where('key', $this->getKeyWithPrefix($key))->update(
                [
                    'value' => serialize($value),
                    'expiration' => new UTCDateTime($expiration),
                    'tags' => $tags,
                ],
                ['upsert' => true]
            );
        } catch (BulkWriteException $exception) {
            // high concurrency exception
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $value = 1)
    {
        return $this->incrementOrDecrement($key, $value, function ($current, $value) {
            return $current + $value;
        });
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $value = 1)
    {
        return $this->incrementOrDecrement($key, $value, function ($current, $value) {
            return $current - $value;
        });
    }

    /**
     * @inheritDoc
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 315360000);
    }

    /**
     * @inheritDoc
     */
    public function forget($key)
    {
        $this->table()->where('key', '=', $this->getPrefix() . $key)->delete();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        $this->table()->delete();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets the tags to be used
     *
     * @param array $tags
     * @return MongoTaggedCache
     */
    public function tags(array $tags)
    {
        return new MongoTaggedCache($this, $tags);
    }

    /**
     * Deletes all records with the given tag
     *
     * @param array $tags
     * @return void
     */
    public function flushByTags(array $tags)
    {
        foreach ($tags as $tag) {
            $this->table()->where('tags', $tag)->delete();
        }
    }

    /**
     * Retrieve an item's expiration time from the cache by key.
     *
     * @param string $key
     * @return null|float|int
     */
    public function getExpiration($key)
    {
        $cacheData = $this->table()->where('key', $this->getKeyWithPrefix($key))->first();

        if (empty($cacheData['expiration'])) {
            return null;
        }

        $expirationSeconds = $cacheData['expiration']->toDateTime()->getTimestamp();

        return round(($expirationSeconds - $this->currentTime()) / 60);
    }

    /**
     * Get a query builder for the cache table.
     *
     * @return Builder
     */
    protected function table()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Format the key to always search for
     *
     * @param string $key
     * @return string
     */
    protected function getKeyWithPrefix(string $key)
    {
        return $this->getPrefix() . $key;
    }

    /**
     * Increment or decrement an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @param Closure $callback
     * @return int|bool
     */
    protected function incrementOrDecrement($key, $value, Closure $callback)
    {
        $currentValue = $this->get($key);

        if ($currentValue === null) {
            return false;
        }

        $newValue = $callback($currentValue, $value);

        if ($this->put($key, $newValue, $this->getExpiration($key))) {
            return $newValue;
        }

        return false;
    }
}
