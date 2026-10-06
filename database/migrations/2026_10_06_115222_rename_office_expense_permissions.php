<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Office Expenses became Expenses (monthly and regular) with its Cost Types;
     * the permissions are renamed to match. Their keys, and who holds them, stay.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}> key => [new group, new name, old group, old name]
     */
    private const RENAMES = [
        'office.expenses.view' => ['Expenses', 'View expenses', 'Office Running Costs', 'View office costs'],
        'office.expenses.create' => ['Expenses', 'Add expenses', 'Office Running Costs', 'Add office costs'],
        'office.expenses.edit' => ['Expenses', 'Edit expenses', 'Office Running Costs', 'Edit office costs'],
        'office.expenses.generate' => ['Expenses', 'Generate monthly expenses', 'Office Running Costs', 'Generate recurring costs'],
        'office.expenses.delete' => ['Expenses', 'Delete expenses', 'Office Running Costs', 'Delete office costs'],
        'office.expenses.payments.create' => ['Expenses', 'Settle expenses', 'Office Running Costs', 'Settle office costs'],
        'office.expenses.payments.delete' => ['Expenses', 'Reverse expense payments', 'Office Running Costs', 'Reverse office cost payments'],
        'office.cost-types.view' => ['Cost Types', 'View cost types', 'Office Cost Types', 'View office cost types'],
        'office.cost-types.create' => ['Cost Types', 'Add cost types', 'Office Cost Types', 'Add office cost types'],
        'office.cost-types.edit' => ['Cost Types', 'Edit cost types', 'Office Cost Types', 'Edit office cost types'],
        'office.cost-types.delete' => ['Cost Types', 'Delete cost types', 'Office Cost Types', 'Delete office cost types'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $key => [$group, $name]) {
            DB::table('permissions')->where('key', $key)->update(['group' => $group, 'name' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $key => [, , $group, $name]) {
            DB::table('permissions')->where('key', $key)->update(['group' => $group, 'name' => $name, 'updated_at' => now()]);
        }
    }
};
