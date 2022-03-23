<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Cache extends Model
{
    public $timestamps = false;

    protected $table = 'cache_test';

    protected $fillable = ['key', 'value', 'expiration', 'tags'];

    protected $casts = ['tags' => 'array'];
}
