<?php

// tests/Fixtures/Base/BaseModel.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\Base;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $table = 'base_models';
}
