<?php

namespace Mxnwire\AuditLog\Http\Controllers;

use Mxnwire\AuditLog\Contracts\ActivityTypeRegistryContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin viewer for the activity log.
 *
 * index() renders the Blade shell that mounts the <activity-logs> Vue component
 * and injects the filter option lists; data() is the JSON endpoint the component
 * paginates. Both are gated by the callable in config('audit-log.gate').
 */
class ActivityLogController extends Controller
{
    protected array $data = [];

    private ActivityTypeRegistryContract $registry;

    public function __construct(ActivityTypeRegistryContract $registry)
    {
        $this->registry      = $registry;
        $this->data['menu']  = 'activity-logs';
    }

    public function index()
    {
        $this->data['filters'] = $this->filterOptions();

        return view('audit-log::activity-log.index', $this->data);
    }

    public function data(Request $request)
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('q')) {
            $query->where('description', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->latest()->paginate(20));
    }

    private function filterOptions(): array
    {
        $logNames     = $this->registry->logNames();
        $events       = $this->registry->events();
        $subjectTypes = collect($this->registry->subjectTypes())
            ->map(fn ($type) => ['value' => $type, 'label' => class_basename($type)])
            ->values();

        $userModel = config('audit-log.user_model');
        $causers   = ($userModel && class_exists($userModel))
            ? $userModel::orderBy('name')->get(['id', 'name'])
            : collect();

        return compact('logNames', 'events', 'subjectTypes', 'causers');
    }
}
