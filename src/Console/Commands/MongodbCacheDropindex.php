<?php

namespace ForFit\Mongodb\Cache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use \MongoDB\Driver\ReadPreference;

/**
 * Drop the indexes created by MongodbCacheIndex
 */
class MongodbCacheDropIndex extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mongodb:cache:dropindex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop indexes from the mongodb `cache` collection';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cacheCollectionName = config('cache')['stores']['mongodb']['table'];

        DB::connection('mongodb')->getMongoDB()->command([
            'dropIndexes' => $cacheCollectionName,
            'index' => 'key_1'
        ], [
            'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY)
        ]);

        DB::connection('mongodb')->getMongoDB()->command([
            'dropIndexes' => $cacheCollectionName,
            'index' => 'expiration_ttl_1'
        ], [
            'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY)
        ]);
    }
}
