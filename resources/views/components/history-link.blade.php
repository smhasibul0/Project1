@props([
    'record',
    'as' => 'item',
])
{{-- Opens one record's history in the activity log: a dropdown item in an
     actions menu, or a small icon button beside inline row actions. --}}
@can('activity.view')
@if($typeKey = \App\Models\ActivityLog::typeKey($record::class))
    @php($historyUrl = route('activity.index', ['type' => $typeKey, 'id' => $record->getKey()]))
    @if($as === 'button')
        <a href="{{ $historyUrl }}" class="btn btn-sm btn-outline-secondary" title="History"><i class="ri-history-line"></i></a>
    @else
        <li><a class="dropdown-item" href="{{ $historyUrl }}"><i class="ri-history-line me-1"></i> History</a></li>
    @endif
@endif
@endcan
