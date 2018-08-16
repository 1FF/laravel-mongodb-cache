<?php

namespace ForFit\Mongodb\Cache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::connection('mongodb')->table($cacheCollectionName, function (Blueprint $collection) {
            $collection->dropIndex('key');
            $collection->dropIndex('expiration_ttl');
        });
    }
}
