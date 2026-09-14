<?php

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Every ability a route asks for, taken straight from the `can:` middleware.
 *
 * @return Collection<int, string>
 */
function routeAbilities(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => $route->gatherMiddleware())
        ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
        ->map(fn (string $middleware) => Str::after($middleware, 'can:'))
        ->unique()
        ->values();
}

/**
 * Every file that can reference an ability: routes, controllers, views, providers.
 *
 * @return array<int, string>
 */
function sourceFiles(): array
{
    $files = [];

    foreach ([base_path('routes'), app_path(), resource_path('views')] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

test('every permission a route requires is defined in the catalog', function () {
    $abilities = routeAbilities();

    expect($abilities)->not->toBeEmpty();
    expect($abilities->diff(PermissionCatalog::keys())->all())->toBe([]);
});

test('every permission in the catalog is actually enforced somewhere', function () {
    $haystack = '';
    foreach (sourceFiles() as $file) {
        $haystack .= file_get_contents($file);
    }

    $unused = array_values(array_filter(
        PermissionCatalog::keys(),
        fn (string $key) => ! str_contains($haystack, "'".$key."'") && ! str_contains($haystack, 'can:'.$key),
    ));

    expect($unused)->toBe([]);
});

test('permission keys are unique across modules', function () {
    $keys = PermissionCatalog::keys();

    expect($keys)->toBe(array_values(array_unique($keys)));
});

test('the catalog is written to the permissions table by the migration', function () {
    expect(Permission::pluck('key')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::keys())->sort()->values()->all());

    // Every row is filed under the module it belongs to.
    foreach (PermissionCatalog::all() as $group => $permissions) {
        foreach ($permissions as $key => [$name]) {
            $permission = Permission::where('key', $key)->firstOrFail();
            expect($permission->group)->toBe($group);
            expect($permission->name)->toBe($name);
        }
    }
});

test('the roles screen lists every module and permission', function () {
    $response = $this->actingAs(adminUser())->get(route('roles.index'));

    $response->assertOk();

    // Compared by hand rather than with assertSee so a failure names what is missing
    // instead of dumping the whole page.
    $html = $response->getContent();

    expect(array_values(array_filter(
        array_keys(PermissionCatalog::all()),
        fn (string $group) => ! str_contains($html, e($group)),
    )))->toBe([]);

    expect(array_values(array_filter(
        PermissionCatalog::keys(),
        fn (string $key) => ! str_contains($html, $key),
    )))->toBe([]);
});

test('a role can be given one action of a module without the rest', function () {
    // May look at customers, but not add, change or remove one.
    $viewer = userWithPermissions(['customers.view'], 'customer-viewer');

    $this->actingAs($viewer)->get(route('customers.index'))->assertOk();
    $this->actingAs($viewer)->post(route('contact.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('contact.delete', 1))->assertForbidden();
    $this->actingAs($viewer)->get(route('customer.groups'))->assertForbidden();
});

test('the legacy map covers every retired coarse permission', function () {
    // The old catalog's keys, as they stood before the detailed one replaced them.
    $retired = [
        'users.manage', 'roles.manage', 'customers.manage', 'hs.manage', 'quotations.manage',
        'orders.manage', 'orders.update-status', 'orders.scan', 'costs.manage', 'lc.manage',
        'containers.manage', 'accounts.manage', 'payments.manage', 'reports.view',
        'office.expenses.manage', 'loans.manage', 'assets.manage', 'warehouses.manage',
        'expenses.manage', 'settings.manage',
    ];

    $map = PermissionCatalog::legacyMap();

    expect(array_diff($retired, array_keys($map)))->toBe([]);

    // And everything it maps to is a real permission.
    foreach ($map as $coarse => $granular) {
        expect(array_diff($granular, PermissionCatalog::keys()))
            ->toBe([], "legacy map for {$coarse} points at unknown keys");
    }
});
