@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Company &amp; Invoice Settings</h4>
                <small class="text-muted">These details appear on customer invoices</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Company Settings</li>
                </ol>
            </div>
        </div>

        <form action="{{ route('settings.company.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Company Details</h6></div>
                        <div class="card-body row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $setting->company_name) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Accent Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="primary_color" value="{{ old('primary_color', $setting->primary_color) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2">{{ old('address', $setting->address) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone(s)</label>
                                <input type="text" class="form-control" name="phone" value="{{ old('phone', $setting->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" name="email" value="{{ old('email', $setting->email) }}">
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Invoice Options</h6></div>
                        <div class="card-body row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Currency Code</label>
                                <input type="text" class="form-control" name="currency" value="{{ old('currency', $setting->currency) }}" placeholder="BDT">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" class="form-control" name="currency_symbol" value="{{ old('currency_symbol', $setting->currency_symbol) }}" placeholder="৳">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Default Terms</label>
                                <input type="text" class="form-control" name="default_terms" value="{{ old('default_terms', $setting->default_terms) }}" placeholder="Net 10 Days">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer Note</label>
                                <input type="text" class="form-control" name="footer_note" value="{{ old('footer_note', $setting->footer_note) }}" placeholder="Thank you for your business!">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer Contact Line</label>
                                <textarea class="form-control" name="footer_contact" rows="2">{{ old('footer_contact', $setting->footer_contact) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Logo</h6></div>
                        <div class="card-body text-center">
                            @php $hasLogo = $setting->logo && file_exists(public_path('upload/company/'.$setting->logo)); @endphp
                            <div class="border rounded p-3 mb-2 d-flex align-items-center justify-content-center" style="min-height:130px; background:#fafbfe;">
                                <img id="logoPreview" src="{{ $hasLogo ? asset('upload/company/'.$setting->logo) : '' }}" alt="logo" class="img-fluid" style="max-height:110px; {{ $hasLogo ? '' : 'display:none;' }}">
                                <div id="logoPlaceholder" class="text-muted" style="{{ $hasLogo ? 'display:none;' : '' }}"><i class="ri-image-line d-block fs-3 mb-1"></i>No logo uploaded</div>
                            </div>
                            <small id="logoPreviewNote" class="text-primary d-block mb-2" style="display:none;"><i class="ri-eye-line me-1"></i>Preview — click Save Settings to apply</small>
                            <input type="file" class="form-control" id="logoInput" name="logo" accept="image/*">
                            <small class="text-muted d-block mt-2">JPG/PNG/WebP, up to 2 MB · used across the whole site</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('logoInput');
    const preview = document.getElementById('logoPreview');
    const placeholder = document.getElementById('logoPlaceholder');
    const note = document.getElementById('logoPreviewNote');
    if (!input) { return; }
    input.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) { return; }
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = '';
            if (placeholder) { placeholder.style.display = 'none'; }
            if (note) { note.style.display = ''; }
        };
        reader.readAsDataURL(file);
    });
});
</script>
@endsection
