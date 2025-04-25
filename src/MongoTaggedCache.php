<?php

namespace ForFit\Mongodb\Cache;

use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

class MongoTaggedCache extends Repository
{
    protected array $tags;

    /**
     * @param Store $store
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
     * @return bool|null
     */
    public function put($key, $value, $ttl = null)
    {
        if (is_array($key)) {
            $this->putMany($key, $value);
        }

        $seconds = $this->getSeconds(is_null($ttl) ? 315360000 : $ttl);

        if ($seconds > 0) {
            $result = $this->store->put($this->itemKey($key), $value, $seconds, $this->tags);

            if ($result) {
                $this->event(new KeyWritten($key, $value, $seconds));
            }

            return $result;
        }

        return $this->forget($key);
    }

    /**
     * Saves array of key value pairs to the cache
     *
     * @param  \DateTimeInterface|\DateInterval|float|int  $ttl
     */
    public function putMany(array $values, $ttl = null): void
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
    }

    /**
     * Flushes the cache for the given tags
     */
    public function flush(): void
    {
        $this->store->flushByTags($this->tags);
    }
}
