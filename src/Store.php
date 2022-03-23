<?php

namespace ForFit\Mongodb\Cache;

use Closure;
use Illuminate\Cache\DatabaseStore;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\BulkWriteException;

class Store extends DatabaseStore
{
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
     * Retrieve an item from the cache by key.
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        $cacheData = $this->table()->where('key', $this->getKeyWithPrefix($key))->first();

        return $cacheData ? $this->decodeFromSaved($cacheData['value']) : null;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $ttl
     * @param  array|null $tags
     * @return bool
     */
    public function put($key, $value, $ttl, $tags = [])
    {
        $expiration = ($this->getTime() + (int) $ttl) * 1000;

        try {
            return (bool) $this->table()->where('key', $this->getKeyWithPrefix($key))->update(
                [
                    'value' => $this->encodeForSave($value),
                    'expiration' => new UTCDateTime($expiration),
                    'tags' => $tags
                ],
                ['upsert' => true]
            );
        } catch (BulkWriteException $exception) {
            // high concurrency exception
            return false;
        }
    }

    /**
     * Retrieve an item's expiration time from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getExpiration($key)
    {
        $cacheData = $this->table()->where('key', $this->getKeyWithPrefix($key))->first();

        if (!$cacheData) {
            return null;
        }

        $expirationSeconds = $cacheData['expiration']->toDateTime()->getTimestamp();

        return round(($expirationSeconds - time()) / 60);
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
     * Increment or decrement an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @param  Closure  $callback
     * @return int|bool
     */
    protected function incrementOrDecrement($key, $value, Closure $callback)
    {
        if (isset($this->connection->transaction)) {
            return parent::incrementOrDecrement($key, $value, $callback);
        }

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
     * Encode data for save
     *
     * @param mixed $data
     * @return string
     */
    protected function encodeForSave($data)
    {
        return serialize($data);
    }

    /**
     * Decode data from save
     *
     * @param string $data
     * @return mixed
     */
    protected function decodeFromSaved($data)
    {
        return unserialize($data);
    }
}
