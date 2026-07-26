<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\StaffSalaryPayment;
use App\Models\Transaction;
use App\Models\WarehouseStaff;
use App\Support\CurrentWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index()
    {
        $staff = WarehouseStaff::withCount('documents')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->orderBy('name')->get();

        return view('warehouse.staff.index', [
            'warehouse' => CurrentWarehouse::get(),
            'staff' => $staff,
            'monthlyPayroll' => round($staff->where('is_active', true)->sum(fn ($s) => (float) $s->monthly_salary), 2),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['warehouse_id'] = CurrentWarehouse::id();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['added_by'] = Auth::id();

        WarehouseStaff::create($data);

        return redirect()->back()->with('success', 'Staff member added.');
    }

    public function update(Request $request, $id)
    {
        $staff = $this->findStaff($id);

        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $staff->update($data);

        return redirect()->back()->with('success', 'Staff member updated.');
    }

    public function show($id)
    {
        $staff = $this->findStaff($id);
        $staff->load(['documents.uploadedBy', 'salaryPayments.paymentAccount']);

        return view('warehouse.staff.show', [
            'staff' => $staff,
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'salaryTotal' => round($staff->salaryPayments->sum(fn ($p) => (float) $p->amount), 2),
        ]);
    }

    public function destroy($id)
    {
        $this->findStaff($id)->delete();

        return redirect()->route('warehouse.staff.index')->with('success', 'Staff member removed.');
    }

    /**
     * Upload a titled document for a staff member.
     */
    public function storeDocument(Request $request, $id)
    {
        $staff = $this->findStaff($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,jpeg,jpg,png,zip|max:4096',
        ]);

        $file = $request->file('file');
        $filename = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/staff_documents');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);

        $staff->documents()->create([
            'title' => $data['title'],
            'file' => $filename,
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Document uploaded.');
    }

    public function destroyDocument($id, $documentId)
    {
        $staff = $this->findStaff($id);
        $staff->documents()->findOrFail($documentId)->delete();

        return redirect()->back()->with('success', 'Document removed.');
    }

    /**
     * Record a monthly salary payment. When paid from an account, it debits that account.
     */
    public function storeSalary(Request $request, $id)
    {
        $staff = $this->findStaff($id);

        $data = $request->validate([
            'salary_month' => 'required|string|max:7',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpeg,jpg,png,doc,docx|max:4096',
        ]);

        DB::transaction(function () use ($staff, $data, $request) {
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/salaries');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $payment = $staff->salaryPayments()->create([
                'warehouse_id' => $staff->warehouse_id,
                'salary_month' => $data['salary_month'],
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'note' => $data['note'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'staff_salary',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => 'Salary '.$data['salary_month'].' — '.$staff->name,
                    'reference' => $staff->name,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => StaffSalaryPayment::class,
                    'transactionable_id' => $payment->id,
                    'added_by' => Auth::id(),
                    'created_at' => $payment->payment_date,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Salary payment recorded.');
    }

    public function destroySalary($id, $paymentId)
    {
        $staff = $this->findStaff($id);
        $payment = $staff->salaryPayments()->findOrFail($paymentId);

        DB::transaction(function () use ($payment) {
            if ($payment->payment_account_id) {
                $account = PaymentAccount::find($payment->payment_account_id);
                if ($account) {
                    $account->increment('balance', (float) $payment->amount);
                }
                Transaction::where('transactionable_type', StaffSalaryPayment::class)
                    ->where('transactionable_id', $payment->id)
                    ->delete();
            }

            $payment->delete();
        });

        return redirect()->back()->with('success', 'Salary payment deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'join_date' => 'nullable|date',
            'monthly_salary' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);
    }

    /**
     * Fetch a staff member scoped to the active warehouse.
     */
    private function findStaff($id): WarehouseStaff
    {
        return WarehouseStaff::where('warehouse_id', CurrentWarehouse::id())->findOrFail($id);
    }
}
