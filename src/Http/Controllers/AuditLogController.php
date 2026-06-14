<?php

namespace Mxnwire\AuditLog\Http\Controllers;

use Mxnwire\AuditLog\Contracts\AuditTypeRegistryContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin viewer for the audit log.
 *
 * index() renders the Blade shell that mounts the <audit-logs> Vue component
 * and injects the filter option lists; data() is the JSON endpoint the component
 * paginates. Both are gated by the callable in config('audit-log.gate').
 */
class AuditLogController extends Controller
{
    protected array $data = [];

    private AuditTypeRegistryContract $registry;

    public function __construct(AuditTypeRegistryContract $registry)
    {
        $this->registry      = $registry;
        $this->data['menu']  = 'audit-logs';
    }

    public function index()
    {
        $this->data['filters'] = $this->filterOptions();

        return view('audit-log::audit-log.index', $this->data);
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

        $logs = $query->latest()->paginate(20);

        $this->trimCausers($logs->getCollection());

        return response()->json($logs);
    }

    /**
     * Reduce each entry's eager-loaded causer to the attributes configured in
     * `audit-log.causer_attributes`, so the JSON response carries only the keys
     * the viewer needs instead of the full actor model. A null config value
     * leaves the causer untouched. setVisible() whitelists the serialized keys
     * while the model's own `$hidden` still applies, so listing a hidden column
     * never exposes it.
     *
     * @param \Illuminate\Support\Collection<int, Activity> $logs
     */
    private function trimCausers($logs): void
    {
        $attributes = config('audit-log.causer_attributes');

        if (! is_array($attributes)) {
            return;
        }

        $logs->each(function (Activity $log) use ($attributes) {
            if ($log->causer !== null) {
                $log->causer->setVisible($attributes);
            }
        });
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
