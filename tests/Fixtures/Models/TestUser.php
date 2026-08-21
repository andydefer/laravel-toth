<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelToth\Tests\Fixtures\Enums\TestUserStatus;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'grade',
        'metadata',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestUserStatus::class,
            'role' => TestUserRole::class,
            'grade' => TestUserGrade::class,
            'metadata' => ClusterCast::class,
            'preferences' => ClusterCast::class,
        ];
    }
}
