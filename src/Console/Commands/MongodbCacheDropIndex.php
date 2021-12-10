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
    protected $signature = 'mongodb:cache:dropindex {index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drops the passed index from the mongodb `cache` collection';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cacheCollectionName = config('cache')['stores']['mongodb']['table'];
        if ($connection = app()->make('db', ['build' => true])->connection('mongodb')) {
            $connection->getMongoDB()->command([
                'dropIndexes' => $cacheCollectionName,
                'index' => $this->argument('index'),
            ], [
                'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY)
            ]);
        }
    }
}
