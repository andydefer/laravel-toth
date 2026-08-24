<?php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Repository\Proxies\AttributeProxy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Archive model representing a stored version of an Eloquent model's data.
 *
 * Each archive entry captures the state of a specific model record at a point in time.
 * Archives are used for backup, restoration, and audit purposes.
 *
 * @property int $id
 * @property string $table_name Name of the source table
 * @property string $row_id Identifier of the source record (supports both int and UUID)
 * @property string $model_class Fully qualified class name of the source model
 * @property StrictAssociative $data The archived data as a key-value array
 * @property Carbon $last_save_at Timestamp of the last save/archive
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Archive extends Model
{
    protected $table = 'archives';

    protected $fillable = [
        'table_name',
        'row_id',
        'model_class',
        'data',
        'last_save_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'last_save_at' => 'datetime',
        ];
    }

    /**
     * Get the created_at attribute as a DateTimeVO.
     */
    protected function data(): Attribute
    {
        return AttributeProxy::required(StrictAssociative::class, column: 'data');
    }
}
