{{-- Type-ahead picker for office cost types: searches name, category and
     nature, and groups the results the way the cost types page does. The
     shared style and behaviour render once however many pickers a page holds. --}}
@props([
    'costTypes',
    'selected' => null,
    'name' => 'office_cost_type_id',
    'placeholder' => 'Search cost type, category…',
    'required' => false,
])
@php
    $uid = 'cts_'.Str::random(6);
    $selectedType = $selected ? $costTypes->firstWhere('id', $selected) : null;
    $groups = [
        'Fixed — monthly' => $costTypes->where('nature', 'fixed'),
        'Variable — as incurred' => $costTypes->where('nature', 'variable'),
    ];
    $hintFor = fn ($type) => ucfirst($type->nature).' · '.($type->categoryPath() ?: 'Uncategorised');
@endphp

@once
<style>
    .cts-select { position: relative; }
    .cts-select .cts-menu { position: absolute; top: 100%; left: 0; right: 0; z-index: 1056; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(16,24,40,.12); margin-top: 3px; max-height: 260px; overflow-y: auto; }
    .cts-select .cts-group { padding: .35rem .8rem; background: #f8fafc; color: #64748b; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    .cts-select .cts-item { padding: .45rem .8rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .cts-select .cts-item:last-child { border-bottom: none; }
    .cts-select .cts-item:hover, .cts-select .cts-item.active { background: #f4f0fe; }
    .cts-select .cts-item .nm { font-weight: 600; font-size: .85rem; color: #1e293b; }
    .cts-select .cts-item .mt { font-size: .74rem; color: #94a3b8; }
    .cts-select .cts-empty { padding: .7rem .8rem; color: #94a3b8; font-size: .82rem; }
    .cts-select .cts-caret { position: absolute; right: .6rem; top: 1.15rem; transform: translateY(-50%); color: #94a3b8; cursor: pointer; }
    .cts-select .cts-search { padding-right: 1.8rem; }
    .cts-select .cts-hint { font-size: .74rem; color: #94a3b8; margin-top: .2rem; min-height: 1rem; }
</style>
@endonce

<div class="cts-select" id="{{ $uid }}">
    <input type="hidden" name="{{ $name }}" value="{{ $selected }}" class="cts-value">
    <input type="text" class="form-control cts-search" autocomplete="off" placeholder="{{ $placeholder }}"
           value="{{ $selectedType?->name }}" @required($required)>
    <i class="ri-arrow-down-s-line cts-caret"></i>
    <div class="cts-menu" style="display:none;">
        <div class="cts-empty" style="display:none;">No cost type matches.</div>
        @foreach($groups as $groupLabel => $group)
            @if($group->isNotEmpty())
            <div class="cts-group" data-nature="{{ $group->first()->nature }}">{{ $groupLabel }}</div>
                @foreach($group as $type)
                <div class="cts-item"
                     data-id="{{ $type->id }}"
                     data-nature="{{ $type->nature }}"
                     data-label="{{ $type->name }}"
                     data-hint="{{ $hintFor($type) }}"
                     data-search="{{ strtolower(trim($type->name.' '.$type->categoryPath().' '.$type->nature)) }}">
                    <div class="nm">{{ $type->name }}@unless($type->is_active) <span class="text-muted fw-normal">(inactive)</span>@endunless</div>
                    <div class="mt">{{ $type->categoryPath() ?: 'Uncategorised' }}</div>
                </div>
                @endforeach
            @endif
        @endforeach
    </div>
    <div class="cts-hint">{{ $selectedType ? $hintFor($selectedType) : '' }}</div>
</div>

@once
<script>
(function () {
    const searchBox = wrap => wrap.querySelector('.cts-search');
    const menuOf = wrap => wrap.querySelector('.cts-menu');
    const isOpen = wrap => menuOf(wrap).style.display !== 'none';
    const close = wrap => { menuOf(wrap).style.display = 'none'; };

    /**
     * Show only the types matching the query, dropping any group heading left
     * with nothing under it.
     */
    function filter(wrap) {
        const query = searchBox(wrap).value.toLowerCase().trim();
        let shown = 0;

        wrap.querySelectorAll('.cts-item').forEach(function (item) {
            const match = !query || item.dataset.search.includes(query);
            item.style.display = match ? '' : 'none';
            item.classList.remove('active');
            if (match) { shown++; }
        });

        wrap.querySelectorAll('.cts-group').forEach(function (group) {
            const siblings = wrap.querySelectorAll('.cts-item[data-nature="' + group.dataset.nature + '"]');
            group.style.display = Array.from(siblings).some(item => item.style.display !== 'none') ? '' : 'none';
        });

        wrap.querySelector('.cts-empty').style.display = shown ? 'none' : '';
    }

    function open(wrap) {
        menuOf(wrap).style.display = '';
        filter(wrap);
    }

    /**
     * Commit a choice: the hidden field carries the id, the text field shows
     * the name, and the hint under it spells out nature and category.
     */
    function pick(wrap, item) {
        wrap.querySelector('.cts-value').value = item.dataset.id;
        searchBox(wrap).value = item.dataset.label;
        searchBox(wrap).setCustomValidity('');
        wrap.querySelector('.cts-hint').textContent = item.dataset.hint;
        close(wrap);
    }

    /**
     * Put the text field back to whatever is actually selected, so a
     * half-typed query never sits there looking like a choice.
     */
    function revert(wrap) {
        const id = wrap.querySelector('.cts-value').value;
        const item = id ? wrap.querySelector('.cts-item[data-id="' + id + '"]') : null;
        searchBox(wrap).value = item ? item.dataset.label : '';
        wrap.querySelector('.cts-hint').textContent = item ? item.dataset.hint : '';
    }

    /**
     * Walk the highlight through the rows still visible after filtering.
     */
    function move(wrap, step) {
        const items = Array.from(wrap.querySelectorAll('.cts-item')).filter(item => item.style.display !== 'none');
        if (!items.length) {
            return;
        }

        const current = items.findIndex(item => item.classList.contains('active'));
        const target = items[Math.max(0, Math.min(items.length - 1, current < 0 ? 0 : current + step))];
        items.forEach(item => item.classList.remove('active'));
        target.classList.add('active');
        target.scrollIntoView({ block: 'nearest' });
    }

    document.addEventListener('focusin', function (e) {
        if (e.target.classList.contains('cts-search')) {
            open(e.target.closest('.cts-select'));
        }
    });

    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('cts-search')) {
            return;
        }

        // Typing invalidates the current choice until a row is picked.
        const wrap = e.target.closest('.cts-select');
        wrap.querySelector('.cts-value').value = '';
        wrap.querySelector('.cts-hint').textContent = '';
        e.target.setCustomValidity('');
        open(wrap);
    });

    document.addEventListener('keydown', function (e) {
        if (!e.target.classList.contains('cts-search')) {
            return;
        }

        const wrap = e.target.closest('.cts-select');

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!isOpen(wrap)) {
                open(wrap);
            }
            move(wrap, e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Enter') {
            const active = wrap.querySelector('.cts-item.active');
            if (isOpen(wrap) && active) {
                // Enter takes the highlighted row rather than submitting the form.
                e.preventDefault();
                pick(wrap, active);
            }
        } else if (e.key === 'Escape') {
            close(wrap);
            revert(wrap);
        }
    });

    document.addEventListener('click', function (e) {
        const item = e.target.closest('.cts-item');
        if (item) {
            pick(item.closest('.cts-select'), item);
            return;
        }

        const caret = e.target.closest('.cts-caret');
        if (caret) {
            const wrap = caret.closest('.cts-select');
            if (isOpen(wrap)) {
                close(wrap);
            } else {
                searchBox(wrap).focus();
            }
            return;
        }

        document.querySelectorAll('.cts-select').forEach(function (wrap) {
            if (!wrap.contains(e.target) && isOpen(wrap)) {
                close(wrap);
                revert(wrap);
            }
        });
    });

    document.addEventListener('submit', function (e) {
        const pending = Array.from(e.target.querySelectorAll('.cts-select')).find(function (wrap) {
            return searchBox(wrap).required && !wrap.querySelector('.cts-value').value;
        });

        if (pending) {
            e.preventDefault();
            const box = searchBox(pending);
            box.setCustomValidity('Pick a cost type from the list.');
            box.reportValidity();
            box.focus();
        }
    });
})();
</script>
@endonce
