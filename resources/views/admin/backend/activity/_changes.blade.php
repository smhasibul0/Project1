{{-- What one activity entry changed: its note, then each field's before → after. --}}
@if($activity->description)
    <div class="small">{{ $activity->description }}</div>
@endif
@foreach($activity->changeLines() as $line)
    <div class="small">
        <span class="text-muted">{{ $line['field'] }}:</span>
        @if($line['hidden'])
            <em>changed</em>
        @elseif($line['old'] === '—')
            <span class="fw-semibold">{{ $line['new'] }}</span>
        @elseif($line['new'] === '—')
            <del class="text-muted">{{ $line['old'] }}</del> <em class="text-muted">(cleared)</em>
        @else
            <del class="text-muted">{{ $line['old'] }}</del>
            <i class="ri-arrow-right-line text-muted"></i>
            <span class="fw-semibold">{{ $line['new'] }}</span>
        @endif
    </div>
@endforeach
