@props([
    'record',
    'limit' => 10,
])
{{-- Who added this record, who last changed it, and its latest activity — including
     the payments, costs and documents filed under it. Shown with 'activity.view'. --}}
@can('activity.view')
@php
    $typeKey = \App\Models\ActivityLog::typeKey($record::class);
    $ownLabel = trim(($typeKey ? \App\Models\ActivityLog::types()[$typeKey]['label'] : '').' '.\App\Models\ActivityLog::labelFor($record));
    $entries = \App\Models\ActivityLog::forRecord($record)->latest('id')->limit($limit)->get();
    $own = \App\Models\ActivityLog::query()
        ->where('subject_type', $record->getMorphClass())
        ->where('subject_id', $record->getKey());
    $added = (clone $own)->where('action', \App\Models\ActivityLog::ADDED)->oldest('id')->first();
    $lastChange = (clone $own)->whereIn('action', [\App\Models\ActivityLog::EDITED, \App\Models\ActivityLog::STATUS_UPDATED])->latest('id')->first();

    // Records made before the log existed still carry who added them.
    $addedBy = $added?->userLabel()
        ?? (method_exists($record, 'addedBy') && $record->addedBy ? \App\Models\ActivityLog::labelFor($record->addedBy) : null);
    $addedAt = $added?->localTime() ?? $record->created_at?->copy()->timezone(config('app.display_timezone'));
@endphp
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0"><i class="ri-history-line me-1"></i> History</h5>
        @if($typeKey)
            <a href="{{ route('activity.index', ['type' => $typeKey, 'id' => $record->getKey()]) }}" class="btn btn-sm btn-outline-primary">View full history</a>
        @endif
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 small mb-3">
            @if($addedBy || $addedAt)
                <div><span class="badge bg-success-subtle text-success">Added</span> by <strong>{{ $addedBy ?? 'Unknown' }}</strong>@if($addedAt) · {{ $addedAt->format('d M Y, h:i A') }}@endif</div>
            @endif
            @if($lastChange)
                <div><span class="badge bg-{{ $lastChange->actionColor() }}-subtle text-{{ $lastChange->actionColor() }}">Last {{ strtolower($lastChange->actionLabel()) }}</span> by <strong>{{ $lastChange->userLabel() }}</strong> · {{ $lastChange->localTime()->format('d M Y, h:i A') }}</div>
            @endif
        </div>

        @forelse($entries as $entry)
            <div class="d-flex gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="flex-grow-1">
                    <span class="badge bg-{{ $entry->actionColor() }}-subtle text-{{ $entry->actionColor() }}">{{ $entry->actionLabel() }}</span>
                    by <strong>{{ $entry->userLabel() }}</strong>
                    @unless($entry->subject_type === $record->getMorphClass() && (int) $entry->subject_id === (int) $record->getKey())
                        {{-- Already on this record's page, so drop the "— Order OR0001" it ends with. --}}
                        <span class="text-muted">— {{ $entry->typeLabel() }} {{ Str::beforeLast($entry->subject_label, ' — '.$ownLabel) }}</span>
                    @endunless
                    @include('admin.backend.activity._changes', ['activity' => $entry])
                </div>
                <div class="text-muted small text-nowrap">{{ $entry->localTime()->format('d M Y, h:i A') }}</div>
            </div>
        @empty
            <div class="text-muted small">No changes recorded yet.</div>
        @endforelse
    </div>
</div>
@endcan
