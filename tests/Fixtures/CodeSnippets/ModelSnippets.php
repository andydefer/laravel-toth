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
}
