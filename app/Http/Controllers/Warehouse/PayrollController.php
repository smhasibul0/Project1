<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\WarehouseStaff;
use App\Support\CurrentWarehouse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Month-by-month payroll board: every staff member's salary, what's been
     * paid for the picked month, what's still due, and quick pay actions.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $staff = WarehouseStaff::with('salaryPayments.paymentAccount')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('warehouse.payroll.index', [
            'warehouse' => CurrentWarehouse::get(),
            'month' => $month,
            'staff' => $staff,
            'totalPayroll' => round($staff->where('is_active', true)->sum(fn (WarehouseStaff $s) => (float) $s->monthly_salary), 2),
            'totalPaid' => round($staff->sum(fn (WarehouseStaff $s) => $s->paidForMonth($month)), 2),
            'totalDue' => round($staff->where('is_active', true)->sum(fn (WarehouseStaff $s) => $s->dueForMonth($month)), 2),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
