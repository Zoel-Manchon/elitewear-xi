<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'accion' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'tipo' => ['nullable', 'string', 'max:120'],
        ]);

        return view('admin.audit.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->when($filters['accion'] ?? null, fn ($q, $a) => $q->where('action', $a))
                ->when($filters['tipo'] ?? null, fn ($q, $t) => $q->where('auditable_type', 'like', "%{$t}%"))
                ->latest('created_at')
                ->paginate(40)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
