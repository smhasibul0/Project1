<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app\Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app\Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

# Live site (Hostinger): give the update steps after every change

The app runs on Hostinger shared hosting at https://rtc-bd.org, and the owner updates it themselves over SSH. **Every reply that finishes a change pushed to `main` must end with the full steps to put that change live**, written for someone who is not a developer: how to connect, each command to run one at a time, what they should see, what to do if a step fails, and anything to do in the app afterwards. When a change needs nothing on the live site, say so plainly. If earlier changes may not be deployed yet, give the steps that cover them all.

## Connecting
- SSH details are in hPanel → Websites → Manage (rtc-bd.org) → Advanced → SSH Access: IP, port 65002, a username starting with `u`, and **Change** to set the password. They are kept out of this file because the repository is public.
- From Windows: open PowerShell, run `ssh -p 65002 <username>@<IP>`, type `yes` the first time, then the password (nothing shows while typing). "Permission denied" means a wrong password: reset it under SSH Access.

## The server
- App folder: `~/domains/rtc-bd.org/laravel`. `public_html` is a symlink to its `public/` folder.
- `proc_open`, `exec` and `symlink()` are disabled. Composer must run with `--no-scripts`, followed by `php artisan package:discover`. `php artisan storage:link` fails; the link was made with `ln -s ../storage/app/public public/storage`.
- There is no Node. When frontend assets change (`resources/css`, `resources/js`, Vite or Tailwind config, `package.json`), build with `npm run build` elsewhere, zip `public/build` as `build.zip`, have it uploaded through hPanel's File Manager into `public/` and extracted, and check it did not land in `public/build/build`.
- The database is MySQL. Before deploying migrations that change or move existing data, have the owner back it up: hPanel → Files → Backups → Database backups → Generate.

## Update commands (keep only the lines a change needs)
```
cd ~/domains/rtc-bd.org/laravel
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader --no-scripts   # when composer.lock changed
php artisan package:discover                                    # after composer install
php artisan migrate --force                                     # when there are new migrations
php artisan optimize
php artisan up
```
- PHP or Blade only: `cd ~/domains/rtc-bd.org/laravel`, `git pull origin main`, `php artisan optimize`.

## When a step fails
- `git pull` says "Your local changes would be overwritten": `git stash`, then pull again.
- Composer runs out of memory: `php -d memory_limit=-1 $(which composer) install --no-dev --optimize-autoloader --no-scripts`.
- Anything else: ask for a screenshot. `php artisan up` brings the site back while it is fixed; the database backup protects the data.

## Working on this repository
- Composer commands run `boost:update`, which rewrites the Boost block above and turns `.claude/skills/deploying-laravel-cloud` into a symlink. Undo it before committing: remove that symlink, then `git checkout -- CLAUDE.md .claude/skills`. This section sits outside the Boost block, so Boost leaves it alone.

# The ERP: how it is built (follow this structure for every new feature)

RTC (Redwan Trading Corporation) is a freight forwarder and importer: customers in Bangladesh buy goods abroad, and RTC collects them in its overseas warehouses, ships them, clears customs, and delivers. One Laravel app with three logins and a public tracking page.

## Business flow
- **Quotation → Order → Container → delivered.**
  - Quotations price goods from HS codes and duty (`DutyCalculator`), Customs valuation rates (highest, lowest and most common, read from valuation-report PDFs) and the day's dollar rate (`ExchangeRate`).
  - An order is made from a quotation (`order.from.quotation`). It carries items, cartons, CBM, the customer's shipping mark and a `track_token`.
  - Orders are shipped in containers (many-to-many) and carry their own costs (Order Costs, Cost Categories).
- **Money in:** order payments (`OrderPayment`). What remains is the order's due and makes up receivables.
- **LC Management (LC / CAD / TT):**
  - Paying an LC buys dollars that stay in the bank account (the dollar wallet). Nothing is sent abroad.
  - Those dollars pay freight and warehouse charges, or are sold for taka.
- **Expenses:** Monthly (fixed) and Regular (variable), both under Cost Types. Warehouses have their own expenses, staff, payroll and stock.
- **Finance:**
  - Payment accounts, plus money borrowed and lent (loans).
  - Reports: Balance Sheet (any date, with PDF), Cash Flow, Profit & Loss, Receivables.
  - Asset register with depreciation.
- Every change is recorded in the Activity Log.

## Three areas
- **Admin panel:**
  - Routes sit in `Route::middleware(['auth', 'admin'])` groups in `routes/web.php`.
  - Controllers go in `app/Http/Controllers/Backend`.
  - Views go in `resources/views/admin/backend/<module>/`, each starting with `@extends('admin.admin_master')` and `@section('admin')`.
  - The menu is `resources/views/admin/body/sidebar.blade.php`; wrap each entry in `@can`.
- **Customer portal:** `/portal`, middleware `customer`, `PortalController`, `resources/views/portal`.
- **Warehouse portal:** `/warehouse`, middleware `warehouse`, `app/Http/Controllers/Warehouse`. Each warehouse login sees only its own warehouse (`App\Support\CurrentWarehouse`).
- **Public:** `/track/{token}` for order tracking, and `/scan` for carton scanning (permission `orders.scan`).

## Permissions
- `App\Support\PermissionCatalog` is the only list of permissions. Every new action gets a key there.
- Protect the route with `->middleware('can:<key>')` and the button or menu item with `@can('<key>')`.
- The admin role passes every check (`Gate::before`). Other roles get only what their role grants.
- A new key also needs a migration that inserts it into `permissions` and grants it to roles that already held the related permission (see `create_dollar_wallets` or `rename_office_expense_permissions`). `PermissionCatalogTest` fails if the catalog and the database differ.

## Money: how it is recorded
- Amounts are in taka (৳). Display them as `'৳ '.number_format($value, 2)`.
- **Taka:**
  - `PaymentAccount.balance` is the account's taka.
  - Every movement changes that balance and adds a `Transaction` with `debit` or `credit`, `running_balance`, `source`, and `transactionable_type` / `transactionable_id` pointing to the record that caused it.
  - Set the Transaction's `created_at` to the payment's own date; the balance sheet for past days depends on it.
  - Deleting or editing a payment reverses its Transaction and gives the balance back.
  - Do all of this inside `DB::transaction`.
- **Dollars:**
  - Each account also holds `usd_balance` and its taka cost `usd_cost`. These change only through `App\Support\DollarLedger` (receive, spend, sell, transfer, reverseFor), which writes `dollar_transactions`.
  - Spent dollars are costed at the average rate they were bought at, so spending them makes no gain or loss; only selling does.
  - Every payment form offers Taka or Dollar through `<x-currency-choice>`, and `<x-account-options>` lists the accounts. Records that can be paid in dollars use the `HasDollarEntries` trait.
- **Dates and times:** stored in UTC, shown in `config('app.display_timezone')` (Asia/Dhaka). Compare date columns with `whereDate`, never a plain string comparison.

## Building a feature: conventions to reuse
- **Validation** is written in the controller with `$request->validate([...])`. There are no Form Request classes.
- **Messages:** redirect back with `->with('success', '...')` or `->with('error', '...')`, which show as toastr pop-ups.
- **Lists:**
  - Use `<x-data-table>`, which provides search, export, print and column visibility.
  - Row actions go in an "Actions" dropdown.
  - Add `<x-history-link>` and `<x-activity-history>` to show a record's history.
- **Activity Log:** models use the `RecordsActivity` trait (`$activityParent`, `$activityIgnore`, `$activityStatusFields`). Use it on every new model.
- **Status and type lists** live on the model as static methods (`Order::goodsStatuses()`, `Lc::types()`, `Quotation::statuses()`) or constants (`Loan::STATUSES`).
- **PDFs:**
  - Use `App\Support\PdfDocument::download($view, $data, $filename)`. It uses dompdf; build the page with tables, not flexbox.
  - Each ৳ is drawn with `resources/fonts/FreeSans-Taka.ttf`. Avoid putting text right before ৳ in a PDF; dompdf draws the sign over it.
- **Reports worked out from records** (for example `App\Support\BalanceSheet::asOf($date)`) are calculated from the ledgers rather than stored as snapshots, so every past date works.
- **Frontend:**
  - The admin panel uses Bootstrap 5, jQuery, DataTables, SweetAlert and toastr, all loaded by `admin_master`. Write page scripts in the view and run them after jQuery has loaded (`DOMContentLoaded`).
  - The portals have their own Bootstrap layouts: `portal/layouts/master` and `warehouse/layouts/master`.
  - Tailwind and Vite (the files in `public/build`) are used only by the Breeze pages built on `x-app-layout` or `x-guest-layout`: profile, confirm password and verify email.
- **Database:**
  - Live is MySQL; tests use SQLite.
  - Index and foreign key names must be 64 characters or fewer; give long morph indexes a short name (`MigrationNamesTest` checks).
  - Write migrations so they can safely be run again after failing partway (`hasColumn` / `hasTable` guards).
- **Tests:** a Pest feature test for every change (`tests/Feature/<Feature>Test.php`), using `adminUser()` or `userWithPermissions([...])` from `tests/Pest.php` and model factories.
- **Finishing a change:**
  - Run Pint and the full test suite.
  - Check the page in a browser.
  - Push to `main`, then give the live-site update steps above.
