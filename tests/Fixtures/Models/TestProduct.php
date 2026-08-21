<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestProduct extends Model
{
    use SoftDeletes;

    protected $table = 'test_products';

    protected $fillable = [
        'name',
        'price',
        'stock',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
        'metadata' => ClusterCast::class,
    ];
}
