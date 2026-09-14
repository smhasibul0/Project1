<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
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
 * A user with the Admin role (bypasses all permission gates). Handy for feature
 * tests that exercise permission-gated admin routes.
 *
 * @param  array<string, mixed>  $attributes
 */
function adminUser(array $attributes = []): User
{
    $role = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Admin', 'is_system' => true],
    );

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}

/**
 * Build a workbook shaped like the published Bangladesh customs tariff: a heading
 * row naming the block, dash-indented lines beneath it, duty written as a fraction,
 * a page header repeated part-way down, and one merged cell holding two lines.
 */
function fakeTariffSheet(): UploadedFile
{
    $header = ['Heading', '', '', '', 'H.S. Code', '', 'Description', '', 'Statistical Unit', 'Statutory Rate of Customs Duty on Import'];

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Section I');
    $sheet->fromArray($header, null, 'A3');
    $sheet->fromArray([
        ['39.11', '', '', '', '', '', "Petroleum resins, coumarone-indene resins,\npolyterpenes and other products, in primary forms.", '', '', ''],
        ['', '', '', '', '3911.10.00', '', '- Petroleum resins', '', 'kg', 0.15],
        ['', '', '', '', '3911.90.00', '', '- Other', '', 'kg', 0.05],
        // A page break repeats the column headings mid-book.
        $header,
        ['85.44', '', '', '', '', '', 'Insulated wire, cable and other insulated electric conductors.', '', '', ''],
        ['', '', '', '', '', '', '- Winding wire :', '', '', ''],
        // The code sits in the first column on this page, as the conversion often puts it.
        ['8544.11.00', '', '', '', '', '', '-- Of copper', '', 'kg', 0.25],
        // One cell, two tariff lines stacked.
        ['', '', '', '', "8544.19.00\n8544.20.00", '', "-- Other\n- Coaxial cable", '', "kg\nkg", "10%\n10%"],
    ], null, 'A4');

    $path = tempnam(sys_get_temp_dir(), 'bct').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'tariff.xlsx', null, null, true);
}

/**
 * A rates-only sheet: HS codes with the taxes the tariff book doesn't carry.
 *
 * @param  array<int, array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float}>  $rows  [code, sd, vat, ait, rd, at]
 */
function fakeRatesSheet(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['HS Code', 'SD', 'VAT', 'AIT', 'RD', 'AT'], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'rates').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'rates.xlsx', null, null, true);
}

/**
 * Build a real packing-list workbook mimicking the supplier layout (bilingual
 * header block, one row per carton, TOTAL row) and wrap it as an upload.
 *
 * @param  array<int, array{0: string, 1: float, 2: float, 3: float, 4: float}>|null  $cartons  [description, qty, gw, nw, cbm] per carton
 */
function fakePackingList(?array $cartons = null): UploadedFile
{
    $cartons ??= [
        ['Ex7 Hand pump', 30, 20.8, 20.0, 0.127],
        ['Ex7 Hand pump', 30, 20.8, 20.0, 0.127],
        ['GP20 Hand pump', 30, 22.7, 22.0, 0.127],
    ];

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A5', '客户名字（Customer Name):孟加拉 Test Customer');
    $sheet->setCellValue('A6', '供应商名称(Supplier)：Test Supplier Co.');
    $sheet->fromArray(
        ['', 'MARK', 'C/T NO', 'DESCRIPTION OF ITEM', '', "Q'TY/PKG", '', "TOTAL Q'TY", 'G.W', 'TOTAL  G.W', 'N.W', 'TOTAL  N.W', 'CBM', 'TOTAL CBM', 'REMARK'],
        null,
        'A9',
    );

    $row = 11; // row 10 is the translated header in real sheets; left blank here
    $totals = ['qty' => 0.0, 'gw' => 0.0, 'nw' => 0.0, 'cbm' => 0.0];
    foreach ($cartons as $i => [$description, $qty, $gw, $nw, $cbm]) {
        $sheet->fromArray([$i + 1, $description, '', $qty, '个', $qty, $gw, $gw, $nw, $nw, $cbm, $cbm], null, "C{$row}");
        $totals['qty'] += $qty;
        $totals['gw'] += $gw;
        $totals['nw'] += $nw;
        $totals['cbm'] += $cbm;
        $row++;
    }

    $sheet->setCellValue("A{$row}", 'TOTAL(合计）');
    $sheet->setCellValue("H{$row}", $totals['qty']);
    $sheet->setCellValue("J{$row}", round($totals['gw'], 3));
    $sheet->setCellValue("L{$row}", round($totals['nw'], 3));
    $sheet->setCellValue("N{$row}", round($totals['cbm'], 4));

    $path = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'packing-list.xlsx', null, null, true);
}

/**
 * A user on a role holding exactly the given permission keys. The keys must exist in
 * App\Support\PermissionCatalog — the migration writes the whole catalog, so they do.
 *
 * @param  array<int, string>  $keys
 * @param  array<string, mixed>  $attributes
 */
function userWithPermissions(array $keys, string $slug = 'test-role', array $attributes = []): User
{
    $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('-', ' ', $slug))]);
    $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}
