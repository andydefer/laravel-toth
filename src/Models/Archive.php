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
        'model_class',
        'data',
        'last_save_at',
    ];

    protected $casts = [
        'data' => 'array',
        'last_save_at' => 'datetime',
    ];
}
