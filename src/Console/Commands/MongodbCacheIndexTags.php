<?php

namespace ForFit\Mongodb\Cache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use \MongoDB\Driver\ReadPreference;

/**
 * Create indexes for the cache collection
 */
class MongodbCacheIndexTags extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mongodb:cache:index_tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create indexes on the tags column of mongodb `cache` collection';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cacheCollectionName = config('cache')['stores']['mongodb']['table'];

        if ($connection = app()->make('db',['build'=>true])->connection('mongodb')) {
            $connection->getMongoDB()->command([
                'createIndexes' => $cacheCollectionName,
                'indexes' => [
                    [
                        'key' => ['tags' => 1],
                        'name' => 'tags_1',
                        'background' => true
                    ],
                ]
            ], [
                'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY)
            ]);
        }
    }
}
