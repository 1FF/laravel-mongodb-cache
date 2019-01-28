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
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes = null)
    {
        if (is_array($key)) {
            return $this->putMany($key, $value);
        }

        if (! is_null($minutes = $this->getMinutes($minutes))) {
            $this->store->put($this->itemKey($key), $value, $minutes, $this->tags);

            $this->event(new KeyWritten($key, $value, $minutes));
        }
    }

    /**
     * Saves array of key value pairs to the cache
     *
     * @param array $values
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @return void
     */
    public function putMany(array $values, $minutes)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $minutes);
        }
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
