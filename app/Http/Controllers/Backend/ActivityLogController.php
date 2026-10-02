<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Every add, edit, status update and delete across the system, newest first —
     * or, with a record picked, that record's own history.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|max:30',
            'type' => 'nullable|string|max:40',
            'id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $types = ActivityLog::types();
        $typeClass = isset($filters['type'], $types[$filters['type']]) ? $types[$filters['type']]['class'] : null;
        $recordClass = $typeClass && ! empty($filters['id']) ? $typeClass : null;

        $activities = ActivityLog::query()
            ->when($recordClass, fn ($q) => $q->forRecord((new $recordClass)->getMorphClass(), $filters['id']))
            ->when($typeClass && ! $recordClass, fn ($q) => $q->where('subject_type', (new $typeClass)->getMorphClass()))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($match) => $match->where('subject_label', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('user_name', 'like', "%{$term}%"));
            })
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $record = null;

        if ($recordClass) {
            // A deleted record is named by the last thing the log knew it as.
            $model = $recordClass::find($filters['id']);

            $record = [
                'type' => $types[$filters['type']]['label'],
                'label' => $model ? ActivityLog::labelFor($model) : (ActivityLog::query()
                    ->where('subject_type', (new $recordClass)->getMorphClass())
                    ->where('subject_id', $filters['id'])
                    ->latest('id')
                    ->value('subject_label') ?? '#'.$filters['id']),
            ];
        }

        return view('admin.backend.activity.index', [
            'activities' => $activities,
            'filters' => $filters,
            'record' => $record,
            'types' => collect($types)->map(fn (array $type) => $type['label'])->sort(),
            'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'username']),
            'actions' => [
                ActivityLog::ADDED => 'Added',
                ActivityLog::EDITED => 'Edited',
                ActivityLog::STATUS_UPDATED => 'Status updated',
                ActivityLog::DELETED => 'Deleted',
                ActivityLog::IMPORTED => 'Imported',
            ],
        ]);
    }
}
