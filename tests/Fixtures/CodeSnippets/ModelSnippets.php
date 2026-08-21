<?php

// tests/Fixtures/CodeSnippets/ModelSnippets.php

declare(strict_types=1);

namespace AndyDefer\LaravelToth\Tests\Fixtures\CodeSnippets;

final class ModelSnippets
{
    public const SIMPLE_MODEL = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
}
PHP;

    public const MODEL_WITH_ALIAS = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    protected $table = 'users';
}
PHP;

    public const MODEL_WITH_TRAIT = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';
}
PHP;

    public const ABSTRACT_MODEL = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractModel extends Model
{
    protected $table = 'abstract_models';
}

class ConcreteModel extends Model
{
    protected $table = 'concrete_models';
}
PHP;

    public const MULTIPLE_MODELS = <<<'PHP'
<?php

namespace AndyDefer\LaravelToth\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';
}

class TestProduct extends Model
{
    protected $table = 'test_products';
}
PHP;

    public const NON_MODEL_CLASSES = <<<'PHP'
<?php

namespace App\Services;

class UserService
{
    public function getUser(): string
    {
        return 'user';
    }
}

class AnotherService
{
    public function doSomething(): void
    {
        // ...
    }
}
PHP;

    public const INTERFACE_AND_TRAIT = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

interface ModelInterface
{
    public function getData(): array;
}

trait ModelTrait
{
    public function getData(): array
    {
        return [];
    }
}

class ConcreteModel extends Model
{
    protected $table = 'concrete_models';
}
PHP;

    public const NESTED_NAMESPACE = <<<'PHP'
<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $table = 'admin_users';
}

class RegularUser extends Model
{
    protected $table = 'regular_users';
}
PHP;

    public const MODEL_WITH_FILLABLE = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'content',
        'user_id',
    ];
}
PHP;

    public const MODEL_WITH_CASTS = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $casts = [
        'total' => 'float',
        'is_paid' => 'boolean',
        'metadata' => 'array',
    ];
}
PHP;

    public const MODEL_EXTENDING_LARAVEL_USER = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
PHP;

    public const CUSTOM_BASE_CLASS_EXTENDING_MODEL = <<<'PHP'
<?php

namespace App\Base;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    // Base model class that extends Eloquent Model
}
PHP;

    public const MODEL_WITH_CUSTOM_BASE_CLASS = <<<'PHP'
<?php

namespace AndyDefer\LaravelToth\Tests\Fixtures\Models;

use AndyDefer\LaravelToth\Tests\Fixtures\Base\BaseModel;

class Product extends BaseModel
{
    protected $table = 'products';
}
PHP;

    public const MODEL_EXTENDING_NATIVE_USER = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as ModelAuthenticatable;

final class User extends ModelAuthenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
PHP;

    public const USER_MODEL_WITH_ALL_FEATURES = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
PHP;

    public const CLASS_WITH_MODEL_IN_NAME_BUT_NOT_MODEL = <<<'PHP'
<?php

namespace App\Services;

class ViewModel
{
    public function getData(): array
    {
        return [];
    }
}
PHP;
}
