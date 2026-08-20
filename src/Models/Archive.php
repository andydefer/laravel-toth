<?php

// src/Models/Archive.php

namespace AndyDefer\LaravelToth\Models;

use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    protected $table = 'archives';

    protected $fillable = [
        'table_name',
        'row_id',
        'data',
        'last_save_at',
    ];

    protected $casts = [
        'data' => 'array',
        'row_id' => 'integer',
        'last_save_at' => 'datetime',
    ];
}
