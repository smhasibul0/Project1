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
 * The flat export Customs publishes alongside the book: one line per code, every
 * rate filled in, and the code kept as a number — so chapters 01-09 lose their
 * leading zero. Specific duties sit in the rate columns as plain taka figures.
 */
function fakeCustomsExportSheet(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Hscode', 'TARRIFF_DESCRIPTION', 'CD', 'SD', 'VAT', 'AIT', 'RD', 'AT', 'TTI'],
        [1012100, 'Pure-bred breeding animals of horses..', 5, 0, 0, 5, 0, 0, 10],
        [17011200, 'Other cane sugar', 3000, 0, 15, 5, 15, 7.5, null],
        [72041000, 'Waste and scrap of cast iron', 0, 0, 1800, 600, 0, 7.5, null],
        [87032421, 'Other Motor cars and oth.vehicles,incl. stn. wagon,CBU cap. >3000cc, <=4000cc', 25, 500, 15, 5, 5, 7.5, 860.5],
    ], null, 'A1', true);

    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'tariff-export.xlsx', null, null, true);
}

/**
 * A Customs "ValuationReport_Analysis" PDF, written out by hand: one text line per
 * entry, so the lines can be given exactly as the report's own text comes out —
 * columns run together, wrapped descriptions, a bill that leaves its date out.
 *
 * @param  array<int, string>|null  $lines
 */
function fakeValuationReport(?array $lines = null, string $name = '79011210.pdf'): UploadedFile
{
    $lines ??= [
        'HSCODE', '79011210', 'Date', 'Between 01/01/2026 - 10/01/2026', 'ValuationReport_Analysis',
        'OfficeType Proc BE_NO BE_Date HSCODE Commercial Description Item Number CURCODE Dec. PriceDec Unit Price',
        '601 4 66225 27/09/202679011210ZINC INGOT 1 USD 50.001.00 60.001.20 50.00 0IN ACME TRADING',
        'DHAKA ROAD',
        '301 4 125132221/09/202679011210ZINC INGOTS 1 USD 19,543.001.0074,951.313.8419,543.00 0AU SAMPLE METALS',
        '55190 79011210ELECTROPLATING', 'CHEMICAL ZINC', 'INGOT',
        '2 USD 3,426.005.713,426.005.71 600.00 0IN NORTH EXPORTS',
    ];

    $content = "BT /F1 8 Tf 10 TL 20 560 Td\n";
    foreach ($lines as $line) {
        $content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
    }
    $content .= 'ET';

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

    $path = tempnam(sys_get_temp_dir(), 'valuation').'.pdf';
    file_put_contents($path, $pdf);

    return new UploadedFile($path, $name, 'application/pdf', null, true);
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
