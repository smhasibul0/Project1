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
            <label class="form-label">Container Number</label>
            <input type="text" class="form-control" name="container_number" value="{{ old('container_number', $container?->container_number) }}">
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
