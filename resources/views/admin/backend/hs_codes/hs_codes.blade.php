@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">HS Codes</h4>
                <small class="text-muted">Customs tariff — description &amp; duty rates behind every quoted item</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">HS Codes</li>
                </ol>
            </div>
        </div>

        {{-- ===================== Excel import ===================== --}}
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Import from Excel</h5></div>
            <div class="card-body">
                <form action="{{ route('hs.code.import') }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Tariff or rates workbook (.xlsx / .xls)</label>
                        <input type="file" class="form-control" name="sheet" accept=".xlsx,.xls" required>
                        @error('sheet')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-upload-2-line me-1"></i> Upload &amp; Update Database
                        </button>
                    </div>
                </form>

                <div class="alert alert-light border mt-3 mb-0 small">
                    The columns are found by their headings, so either shape works:
                    <ul class="mb-0 mt-1">
                        <li><strong>The published tariff book</strong> — <em>Heading · H.S. Code · Description · Statistical Unit · Statutory Rate of Customs Duty on Import</em>.
                            Each line's full description is rebuilt from the heading above it, and duty written as a fraction (0.05) is stored as a percentage (5%).</li>
                        <li><strong>A rates sheet</strong> — <em>HS Code</em> plus any of <em>CD · SD · VAT · AIT · RD · AT</em>.
                            With no description column it only tops up codes already stored; unknown codes are reported and skipped.</li>
                    </ul>
                    Existing codes are updated in place, so re-importing a newer book is safe.
                </div>
            </div>
        </div>

        {{-- ===================== Tariff list ===================== --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Tariff Lines <span class="text-muted fs-6 fw-normal">({{ number_format($total) }} stored)</span></h5>
                <div class="d-flex gap-2">
                    <form action="{{ route('hs.codes') }}" method="GET" class="d-flex gap-2">
                        <input type="search" class="form-control form-control-sm" name="q" value="{{ $search }}"
                               placeholder="HS code or description" style="min-width:260px">
                        <button class="btn btn-sm btn-outline-primary">Search</button>
                        @if($search !== '')
                            <a href="{{ route('hs.codes') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        @endif
                    </form>
                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="ri-add-line me-1"></i> Add HS Code
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>HS Code</th>
                                <th>Description</th>
                                <th class="text-center">Unit</th>
                                <th class="text-end">CD</th>
                                <th class="text-end">SD</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">AIT</th>
                                <th class="text-end">RD</th>
                                <th class="text-end">AT</th>
                                <th class="text-end">Total Tax Incidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($hsCodes as $hsCode)
                            <tr class="{{ $hsCode->is_active ? '' : 'text-muted' }}">
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item edit-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-row="{{ json_encode($hsCode) }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            <li>
                                                <form action="{{ route('hs.code.delete', $hsCode->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn">
                                                        <i class="ri-delete-bin-line me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td class="fw-semibold text-nowrap">{{ $hsCode->code }}</td>
                                <td style="min-width:340px">{{ $hsCode->description }}</td>
                                <td class="text-center">{{ $hsCode->statistical_unit ?: '—' }}</td>
                                @foreach(\App\Models\HsCode::RATE_FIELDS as $field)
                                    <td class="text-end">{{ rtrim(rtrim(number_format($hsCode->{$field}, 2), '0'), '.') ?: '0' }}</td>
                                @endforeach
                                <td class="text-end fw-semibold">{{ number_format($hsCode->totalTaxIncidence(), 2) }}%</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    @if($search !== '')
                                        Nothing matches “{{ $search }}”.
                                    @else
                                        No HS codes yet — upload the tariff workbook above to fill the database.
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($hsCodes->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                    Showing {{ $hsCodes->firstItem() }}–{{ $hsCodes->lastItem() }} of {{ number_format($hsCodes->total()) }}
                </small>
                {{ $hsCodes->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ===================== Add / edit ===================== --}}
@foreach(['add' => 'addModal', 'edit' => 'editModal'] as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('hs.code.store') : url('hs-codes') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} HS Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">HS Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" data-field="code" required placeholder="3911.90.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Statistical Unit</label>
                        <input type="text" class="form-control" name="statistical_unit" data-field="statistical_unit" placeholder="kg, u, l">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" data-field="is_active" checked>
                            <label class="form-check-label">Active (offered in quotations)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" data-field="description" rows="2" required></textarea>
                    </div>
                    @foreach(['cd_rate' => 'CD', 'sd_rate' => 'SD', 'vat_rate' => 'VAT', 'ait_rate' => 'AIT', 'rd_rate' => 'RD', 'at_rate' => 'AT'] as $field => $label)
                    <div class="col-md-2">
                        <label class="form-label">{{ $label }} %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="{{ $field }}" data-field="{{ $field }}" value="0">
                    </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $mode === 'add' ? 'Save' : 'Update' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
        const row = JSON.parse(e.relatedTarget.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('hs-codes') }}/' + row.id;
        form.querySelectorAll('[data-field]').forEach(function (field) {
            if (field.type === 'checkbox') {
                field.checked = !!row[field.dataset.field];
            } else {
                field.value = row[field.dataset.field] ?? '';
            }
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this HS code?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
