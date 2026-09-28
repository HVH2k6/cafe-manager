<?php

use App\Actions\Orders\PlaceOrder;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Any HTTP call a test did not fake (payOS…) fails instead of reaching the real service.
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A user with the owner role, which is allowed everything.
 */
function owner(): User
{
    return User::factory()->for(Role::firstOrCreate(['name' => Role::OWNER]))->create();
}

/**
 * A user whose (new) role holds exactly the given permissions.
 *
 * @param  list<Permission>  $permissions
 */
function staffWith(array $permissions, bool $roleIsActive = true): User
{
    $role = Role::factory()->create(['is_active' => $roleIsActive]);
    $role->permissions()->attach(collect($permissions)->pluck('id'));

    return User::factory()->for($role)->create();
}

/**
 * A POS cart line for a product.
 *
 * @param  list<int>  $variantIds
 * @return array<string, mixed>
 */
function productLine(Product $product, int $quantity = 1, array $variantIds = [], ?string $note = null): array
{
    return ['type' => 'product', 'id' => $product->id, 'quantity' => $quantity, 'variant_ids' => $variantIds, 'note' => $note];
}

/**
 * Place an order the way the POS does.
 *
 * @param  list<array{0: Product, 1?: int}>  $products
 */
function placeOrder(array $products, ?DiningTable $table = null, OrderStatus $status = OrderStatus::Confirmed, ?User $by = null): Order
{
    return app(PlaceOrder::class)->handle(
        $by ?? User::factory()->create(),
        ['order_type' => $table ? 'dine_in' : 'takeaway', 'table_id' => $table?->id],
        array_map(fn (array $line): array => ['type' => 'product', 'id' => $line[0]->id, 'quantity' => $line[1] ?? 1], $products),
        $status,
    );
}

/**
 * A user whose role holds the given permission keys, e.g. staffWithPermissions('employees.view').
 */
function staffWithPermissions(string ...$keys): User
{
    return staffWith(array_map(function (string $key): Permission {
        [$module, $action] = explode('.', $key, 2);

        return Permission::firstOrCreate(['module' => $module, 'action' => $action], ['name' => $key, 'is_active' => true]);
    }, $keys));
}
