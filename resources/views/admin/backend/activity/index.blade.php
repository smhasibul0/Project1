@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                @if($record)
                    <h4 class="fs-18 fw-semibold m-0">History — {{ $record['type'] }} {{ $record['label'] }}</h4>
                    <small class="text-muted">Everything done to this record and the payments, costs and documents filed under it</small>
                @else
                    <h4 class="fs-18 fw-semibold m-0">Activity Log</h4>
                    <small class="text-muted">Who added, edited, updated or deleted what — and when</small>
                @endif
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    @if($record)
                        <li class="breadcrumb-item"><a href="{{ route('activity.index') }}">Activity Log</a></li>
                        <li class="breadcrumb-item active">History</li>
                    @else
                        <li class="breadcrumb-item active">Activity Log</li>
                    @endif
                </ol>
            </div>
        </div>

        {{-- ===================== Filters ===================== --}}
        <div class="card">
            <div class="card-body">
                <form action="{{ route('activity.index') }}" method="GET" class="row g-2 align-items-end">
                    @if($record)
                        <input type="hidden" name="type" value="{{ $filters['type'] }}">
                        <input type="hidden" name="id" value="{{ $filters['id'] }}">
                    @endif
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label small mb-1">Search</label>
                        <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order no, name, amount, note…">
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label small mb-1">Done by</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">Anyone</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ trim($user->first_name.' '.$user->last_name) ?: $user->username }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label small mb-1">Action</label>
                        <select name="action" class="form-select form-select-sm">
                            <option value="">Any action</option>
                            @foreach($actions as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless($record)
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label small mb-1">Record type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">Everything</option>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endunless
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label small mb-1">From</label>
                        <input type="date" class="form-control form-control-sm" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label small mb-1">To</label>
                        <input type="date" class="form-control form-control-sm" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="col-auto d-flex gap-1">
                        <button class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ $record ? route('activity.index', ['type' => $filters['type'], 'id' => $filters['id']]) : route('activity.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    </div>
                </form>
                @if($record)
                    <a href="{{ route('activity.index') }}" class="small d-inline-block mt-2"><i class="ri-arrow-left-line"></i> All activity</a>
                @endif
            </div>
        </div>

        {{-- ===================== Entries ===================== --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ number_format($activities->total()) }} {{ Str::plural('entry', $activities->total()) }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
                        <thead class="table-light">
                            <tr>
                                <th style="width:150px">When</th>
                                <th style="width:230px">What happened</th>
                                <th>Record</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activities as $activity)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $activity->localTime()->format('d M Y') }}
                                    <div class="text-muted small">{{ $activity->localTime()->format('h:i A') }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $activity->actionColor() }}-subtle text-{{ $activity->actionColor() }}">{{ $activity->actionLabel() }}</span>
                                    by <strong>{{ $activity->userLabel() }}</strong>
                                </td>
                                <td>
                                    <div class="text-muted small">{{ $activity->typeLabel() }}</div>
                                    @if($url = $activity->url())
                                        <a href="{{ $url }}" class="fw-semibold">{{ $activity->subject_label }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $activity->subject_label }}</span>
                                    @endif
                                    @if(! $record && $activity->subject_id && ($typeKey = \App\Models\ActivityLog::typeKey($activity->subject_type)))
                                        <a href="{{ route('activity.index', ['type' => $typeKey, 'id' => $activity->subject_id]) }}" class="small ms-1" title="This record's history"><i class="ri-history-line"></i></a>
                                    @endif
                                </td>
                                <td style="min-width:260px">
                                    @include('admin.backend.activity._changes', ['activity' => $activity])
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Nothing recorded{{ $record ? ' for this record' : '' }} yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($activities->hasPages())
                <div class="card-footer">{{ $activities->links() }}</div>
            @endif
        </div>

    </div>
</div>

@endsection
