<?php

namespace ForFit\Mongodb\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Cache\Events\KeyWritten;

class MongoTaggedCache extends Repository
{
    protected $tags;

    /**
     * @param \Illuminate\Contracts\Cache\Store $store
     * @param array $tags
     */
    public function __construct(Store $store, array $tags = [])
    {
        parent::__construct($store);

        $this->tags = $tags;
    }

    /**
     * Store an item in the cache with tags.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTimeInterface|\DateInterval|float|int  $ttl
     * @return void
     */
    public function put($key, $value, $ttl = null)
    {
        if (is_array($key)) {
            return $this->putMany($key, $value);
        }

        $seconds = $this->getSeconds(is_null($ttl) ? 315360000 : $ttl);

        if ($seconds > 0) {
            $result = $this->store->put($this->itemKey($key), $value, $seconds, $this->tags);

            if ($result) {
                $this->event(new KeyWritten($key, $value, $seconds));
            }

            return $result;
        } else {
            return $this->forget($key);
        }
    }

    /**
     * Saves array of key value pairs to the cache
     *
     * @param array $values
     * @param  \DateTimeInterface|\DateInterval|float|int  $ttl
     * @return void
     */
    public function putMany(array $values, $ttl = null)
    {
        $seconds = $this->getSeconds(is_null($ttl) ? 315360000 : $ttl);

        if ($seconds <= 0) {
            $allForgotten = true;
            foreach (array_keys($values) as $key) {
                if (!$this->forget($key)) {
                    $allForgotten = false; // Though forget usually returns true
                }
            }
            return $allForgotten;
        }

        if (empty($values)) {
            return true;
        }

        $documents = [];
        $expirationTimestamp = ($this->currentTime() + $seconds) * 1000;

        foreach ($values as $key => $value) {
            $documents[] = [
                'key' => $this->itemKey($key),
                'value' => serialize($value),
                'expiration' => new \MongoDB\BSON\UTCDateTime($expirationTimestamp),
                'tags' => $this->tags,
            ];
        }

        try {
            // The `insert` method in Jenssegers/mongodb Query/Builder returns bool
            $result = $this->store->table()->insert($documents);
        } catch (\Exception $e) {
            // Log the exception or handle it as per application's error handling policy
            // For now, assume failure means returning false
            return false;
        }

        if ($result) {
            foreach ($values as $key => $value) {
                $this->event(new KeyWritten($key, $value, $seconds));
            }
        }

        return $result;
    }

    /**
     * Flushes the cache for the given tags
     *
     * @return void
     */
    public function flush()
    {
        return $this->store->flushByTags($this->tags);
    }
}
