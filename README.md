# Laravel Data Integrity

Scan your Eloquent models for data integrity violations and fix them automatically.

## Concept

An **audit case** is a class that groups one or more integrity checks for a given model. Each `check*` method defines a single audit that examines every record in chunks and reports violations. Audit cases are discovered automatically from `database/audits/`, and violations can be fixed in place when passed `--fix`.

## Installation

```bash
composer require proai/laravel-data-integrity
```

The package auto-registers itself via Laravel's package discovery. The `db:audit` command is available immediately after installation.

Since audits live in `database/audits/` by default, you need to register the namespace in your application's `composer.json` so the classes can be autoloaded:

```json
"autoload": {
    "psr-4": {
        "Database\\Audits\\": "database/audits/"
    }
}
```

Then run `composer dump-autoload`.

## Usage

```bash
# Run all audits
php artisan db:audit

# Run all audits and fix violations
php artisan db:audit --fix

# Run only audits in database/audits/Threads/
php artisan db:audit Threads

# Run only audits for a specific model (by class basename)
php artisan db:audit --model=Thread
```

## Creating an audit case

Create a class anywhere under `database/audits/` extending `ProAI\DataIntegrity\AuditCase`. It will be discovered automatically. Each public `check*` method defines one audit and returns an `Audit`.

### Inline audits

Define checks as individual methods using the fluent builder:

```php
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use App\Models\Order;

class OrderAudit extends AuditCase
{
    protected $model = Order::class;

    public function checkTotalMatchesLineItems(): Audit
    {
        return $this->audit('order total matches line items')
            ->validate(function ($order, $fail) {
                $expected = $order->lineItems()->sum('price');

                if ((int) $order->total !== (int) $expected) {
                    $fail(
                        "has total {$order->total}, expected {$expected}",
                        fn () => $order->update(['total' => $expected]),
                    );
                }
            });
    }
}
```

The `validate` closure receives a single model instance and a `$fail` closure. Call `$fail(string $reason)` to report a violation. The model identifier (e.g. `Order #42:`) is automatically prepended to the reason in the output.

To support `--fix`, pass an optional fix closure as the second argument to `$fail`:

```php
$fail(
    "has total {$order->total}, expected {$expected}",
    fn () => $order->update(['total' => $expected]),
);
```

The fluent builder supports `query()`, `chunkSize()`, `before()`, and `after()`:

```php
public function checkCompletedOrderHasPayment(): Audit
{
    return $this->audit('completed order has a payment')
        ->query(fn ($query) => $query->where('status', 'completed'))
        ->chunkSize(200)
        ->validate(function ($order, $fail) {
            if (! $order->payment()->exists()) {
                $fail('is completed but has no payment record');
            }
        });
}
```

### Description from method name

When no description is passed to `audit()`, it is derived from the method name automatically:

```php
// Description will be "email is valid"
public function checkEmailIsValid(): Audit
{
    return $this->audit()
        ->validate(fn ($user, $fail) => /* ... */);
}
```

### Lifecycle hooks: `before` and `after`

Use `before` and `after` to run logic before and after each chunk is validated. The `before` hook is useful for preloading related data or setting up shared state, while `after` can be used for cleanup or post-processing:

```php
protected array $activeCounts = [];

public function checkActiveOrdersCount(): Audit
{
    return $this->audit('user has correct active orders count')
        ->before(function ($chunk) {
            $this->activeCounts = DB::table('orders')
                ->whereIn('user_id', $chunk->modelKeys())
                ->where('status', 'active')
                ->groupBy('user_id')
                ->pluck(DB::raw('count(*)'), 'user_id')
                ->all();
        })
        ->validate(function ($user, $fail) {
            $expected = $this->activeCounts[$user->id] ?? 0;

            if ($user->active_orders_count !== $expected) {
                $fail(
                    "has active_orders_count {$user->active_orders_count}, expected {$expected}",
                    fn () => $user->update(['active_orders_count' => $expected]),
                );
            }
        });
}
```

### Reusable checks with `IntegrityCheck`

For checks that are shared across multiple audits, create a class implementing `IntegrityCheck`:

```php
use Closure;
use Illuminate\Database\Eloquent\Model;
use ProAI\DataIntegrity\IntegrityCheck;

class BelongsToExists implements IntegrityCheck
{
    public function __construct(
        public readonly string $relation,
    ) {}

    public function description(): string
    {
        return "{$this->relation} exists";
    }

    public function validate(Model $model, Closure $fail): void
    {
        if (! $model->{$this->relation}()->exists()) {
            $foreignKey = $model->{$this->relation}()->getForeignKeyName();

            $fail("references missing {$this->relation} ({$foreignKey}: {$model->$foreignKey})");
        }
    }
}
```

Then reference it in your audit case with `auditUsing()`:

```php
class CommentAudit extends AuditCase
{
    protected $model = Comment::class;

    public function checkPostExists(): Audit
    {
        return $this->auditUsing(BelongsToExists::class, ['post']);
    }

    public function checkAuthorExists(): Audit
    {
        return $this->auditUsing(BelongsToExists::class, ['author']);
    }
}
```

Check classes can accept constructor arguments, making them reusable across different models and relationships. The example above shows how `BelongsToExists` can verify any `belongsTo` relationship — just pass the relation name.

For counter cache validation, a generic check class avoids duplicating the same logic:

```php
class CachedCountIsCorrect implements IntegrityCheck
{
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
    ) {}

    public function validate(Model $model, Closure $fail): void
    {
        $expected = $model->{$this->relation}()->count();

        if ($model->{$this->column} !== $expected) {
            $fail(
                "has {$this->column} {$model->{$this->column}}, expected {$expected}",
                fn () => $model->update([$this->column => $expected]),
            );
        }
    }
}
```

```php
// In ThreadAudit
public function checkPostsCount(): Audit
{
    return $this->auditUsing(CachedCountIsCorrect::class, ['posts', 'posts_count']);
}

public function checkSubscribersCount(): Audit
{
    return $this->auditUsing(CachedCountIsCorrect::class, ['subscribers', 'subscribers_count']);
}
```

Check classes can also define optional `before($chunk)`, `after($chunk)`, `query()`, and `description()` methods. Use `before` to batch-load data per chunk instead of querying per model:

```php
class CachedCountIsCorrect implements IntegrityCheck
{
    protected Collection $expectedCounts;

    public function __construct(
        public readonly string $relation,
        public readonly string $column,
    ) {}

    public function before(EloquentCollection $chunk): void
    {
        $example = $chunk->first()->{$this->relation}();
        $foreignKey = $example->getForeignKeyName();
        $table = $example->getRelated()->getTable();

        $this->expectedCounts = DB::table($table)
            ->whereIn($foreignKey, $chunk->modelKeys())
            ->groupBy($foreignKey)
            ->pluck(DB::raw('count(*)'), $foreignKey);
    }

    public function validate(Model $model, Closure $fail): void
    {
        $expected = $this->expectedCounts->get($model->getKey(), 0);

        if ($model->{$this->column} !== $expected) {
            $fail(
                "has {$this->column} {$model->{$this->column}}, expected {$expected}",
                fn () => $model->update([$this->column => $expected]),
            );
        }
    }
}
```

### Registering check aliases

You can register short aliases for check classes and reference them by name:

```php
use ProAI\DataIntegrity\AuditManager;

AuditManager::register('posts-count', PostsCountIsCorrect::class);
```

```php
public function checkPostsCount(): Audit
{
    return $this->auditUsing('posts-count');
}
```

## Configuration

All global settings are managed through the `AuditManager` class. Configure them in a service provider:

```php
use ProAI\DataIntegrity\AuditManager;

// Override the default chunk size (default: 1000)
AuditManager::defaultChunkSize(500);

// Override the audit discovery path (default: database/audits)
AuditManager::discoverIn(database_path('my-audits'));
```

Individual audits can still override the chunk size per check via the `chunkSize()` method.

## API

### `AuditManager`

| Member                                                    | Description                                          |
| --------------------------------------------------------- | ---------------------------------------------------- |
| `static defaultChunkSize(int $chunkSize): void`           | Set the default chunk size (default: `1000`)         |
| `static discoverIn(string $path): void`                   | Override the audit discovery path                    |
| `static register(string $name, string $checkClass): void` | Register a named check alias                         |
| `static flush(): void`                                    | Reset all settings to defaults                       |

### `AuditCase` (abstract base class)

| Member                                               | Description                                         |
| ---------------------------------------------------- | --------------------------------------------------- |
| `protected $model`                                   | Eloquent model class to scan                        |
| `public function check*(): Audit`             | Define a single audit (discovered via reflection)   |
| `$this->audit(?string $description): Audit`   | Start an inline audit with the fluent builder       |
| `$this->auditUsing(string $class, array $args = [])` | Delegate to an `IntegrityCheck` class               |

### `Audit` (fluent builder)

| Method                           | Description                                                                       |
| -------------------------------- | --------------------------------------------------------------------------------- |
| `query(Closure $callback)`       | `function ($query)` — add constraints / eager loads                               |
| `chunkSize(int $size)`           | Records per chunk (defaults to the global default set via `AuditManager`)         |
| `before(Closure $callback)`      | `function ($chunk)` — runs before each chunk                                      |
| `after(Closure $callback)`       | `function ($chunk)` — runs after each chunk                                       |
| `validate(Closure $callback)`    | `function ($model, $fail)` — validate a single model                              |

### `IntegrityCheck` (interface)

```php
public function validate(Model $model, Closure $fail): void;
```

Optionally add `before(EloquentCollection $chunk): void`, `after(EloquentCollection $chunk): void`, `query(): Closure`, and/or `description(): string` methods.

### `$fail` closure

```php
$fail(string $reason, ?Closure $fix = null): void
```

| Argument  | Type            | Description                                                        |
| --------- | --------------- | ------------------------------------------------------------------ |
| `$reason` | `string`        | Violation message (model ID is auto-prepended in output)           |
| `$fix`    | `Closure\|null` | Optional fix closure, called when `--fix` is passed. No arguments. |

## License

MIT
