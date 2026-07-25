@php
    $container = $container ?? null;
@endphp

<div class="card">
    <div class="card-header"><h6 class="mb-0">Container / Shipment Details</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Shipment No</label>
            <input type="text" class="form-control" name="shipment_no" value="{{ old('shipment_no', $container?->shipment_no) }}" placeholder="e.g. 25/2026 (CTG)">
        </div>
        <div class="col-md-3">
            <label class="form-label">Shipment Type</label>
            <select class="form-control" name="shipment_type" id="shipmentTypeSelect">
                @foreach($shipmentTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('shipment_type', $container?->shipment_type ?? 'fcl') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 fcl-only">
            <label class="form-label">Container Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="container_number" value="{{ old('container_number', $container?->container_number) }}">
            @error('container_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3 fcl-only">
            <label class="form-label">Container Size</label>
            <select class="form-control" name="container_size">
                <option value="">--</option>
                @foreach($containerSizes as $size)
                    <option value="{{ $size }}" @selected(old('container_size', $container?->container_size) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 lcl-only">
            <label class="form-label">LCL Rate (per CBM)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="lcl_rate" value="{{ old('lcl_rate', $container?->lcl_rate) }}" placeholder="e.g. 55.00">
            <small class="text-muted">Freight = rate &times; loaded CBM of assigned orders.</small>
        </div>

        <div class="col-md-3">
            <label class="form-label">Transport Mode</label>
            <select class="form-control" name="transport_mode">
                <option value="">--</option>
                @foreach($transportationModes as $m)
                    <option value="{{ $m->name }}" @selected(old('transport_mode', $container?->transport_mode) === $m->name)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Shipping / Air Line</label>
            <input type="text" class="form-control" name="shipping_line" value="{{ old('shipping_line', $container?->shipping_line) }}" placeholder="e.g. Maersk (A.P. Moller)">
        </div>
        <div class="col-md-3">
            <label class="form-label">Booking Ref (HBL / MBL)</label>
            <input type="text" class="form-control" name="booking_ref" value="{{ old('booking_ref', $container?->booking_ref) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Cost Allocation Basis</label>
            <select class="form-control" name="allocation_basis">
                @foreach($allocationBases as $key => $label)
                    <option value="{{ $key }}" @selected(old('allocation_basis', $container?->allocation_basis ?? 'cbm') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Port of Loading</label>
            <input type="text" class="form-control" name="port_of_loading" value="{{ old('port_of_loading', $container?->port_of_loading) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Port of Discharge</label>
            <input type="text" class="form-control" name="port_of_discharge" value="{{ old('port_of_discharge', $container?->port_of_discharge) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">ETD (departure)</label>
            <input type="date" class="form-control" name="etd" value="{{ old('etd', optional($container?->etd)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">ETA (arrival)</label>
            <input type="date" class="form-control" name="eta" value="{{ old('eta', optional($container?->eta)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $container?->status ?? 'booked') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-9">
            <label class="form-label">Staffing Notes</label>
            <input type="text" class="form-control" name="staffing_notes" value="{{ old('staffing_notes', $container?->staffing_notes) }}" placeholder='e.g. "Fragile", "Bottom Load"'>
        </div>
        <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2">{{ old('remarks', $container?->remarks) }}</textarea>
        </div>
    </div>
</div>

<div class="d-flex gap-2 my-4">
    <button type="submit" class="btn btn-primary px-4">{{ $container ? 'Update Container' : 'Save Container' }}</button>
    <a href="{{ route('container.index') }}" class="btn btn-secondary">Cancel</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('shipmentTypeSelect');

    function toggleShipmentFields() {
        const isLcl = typeSelect.value === 'lcl';
        document.querySelectorAll('.fcl-only').forEach(el => el.classList.toggle('d-none', isLcl));
        document.querySelectorAll('.lcl-only').forEach(el => el.classList.toggle('d-none', !isLcl));
    }

    typeSelect.addEventListener('change', toggleShipmentFields);
    toggleShipmentFields();
});
</script>
