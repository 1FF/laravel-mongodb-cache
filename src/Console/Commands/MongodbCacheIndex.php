<?php

namespace ForFit\Mongodb\Cache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\ReadPreference;

/**
 * Create indexes for the cache collection
 */
class MongodbCacheIndex extends Command
{

    /** The name and signature of the console command. */
    protected $signature = 'mongodb:cache:index';

    /** The console command description. */
    protected $description = 'Create indexes on the mongodb `cache` collection';

    /** Execute the console command. */
    public function handle(): void
    {
        $cacheCollectionName = config('cache')['stores']['mongodb']['table'];

        DB::connection('mongodb')->getDatabase()->command([
            'createIndexes' => $cacheCollectionName,
            'indexes' => [
                [
                    'key' => ['key' => 1],
                    'name' => 'key_1',
                    'unique' => true,
                    'background' => true
                ],
                [
                    'key' => ['expiration' => 1],
                    'name' => 'expiration_ttl_1',
                    'expireAfterSeconds' => 0,
                    'background' => true
                ]
            ]
        ], [
            'readPreference' => new ReadPreference(ReadPreference::PRIMARY)
        ]);
    }
}
