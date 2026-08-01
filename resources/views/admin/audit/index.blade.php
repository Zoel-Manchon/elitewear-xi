@extends('layouts.admin')

@section('eyebrow', 'Seguridad')
@section('title', 'Registro de auditoría')

@section('content')
    <p class="text-body-secondary mb-4" style="max-width:62ch">
        Quién cambió qué, cuándo y desde qué dirección. Se conserva aunque la
        cuenta que hizo el cambio se elimine después.
    </p>

    <form method="GET" class="d-flex flex-wrap gap-2 mb-4">
        <select name="accion" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
            <option value="">Todas las acciones</option>
            @foreach (['created' => 'Creaciones', 'updated' => 'Modificaciones', 'deleted' => 'Eliminaciones'] as $v => $l)
                <option value="{{ $v }}" @selected(request('accion') === $v)>{{ $l }}</option>
            @endforeach
        </select>

        <input type="search" name="tipo" value="{{ request('tipo') }}"
               class="form-control form-control-sm w-auto" placeholder="Tipo (Product, Order…)">

        @if (request()->hasAny(['accion', 'tipo']))
            <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-outline-light">Limpiar</a>
        @endif
    </form>

    <div class="table-responsive" style="border:1px solid var(--line)">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="eyebrow">
                    <th>Cuándo</th><th>Quién</th><th>Qué</th><th>Cambios</th><th>IP</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="data small text-body-secondary" style="white-space:nowrap">
                        {{ $log->created_at?->format('d/m/Y H:i') }}
                    </td>
                    <td>
                        {{ $log->actor_label }}
                        @unless ($log->user)
                            <span class="badge bg-secondary" title="La cuenta ya no existe">baja</span>
                        @endunless
                    </td>
                    <td>
                        <span class="data text-body-secondary">{{ $log->actionLabel() }}</span>
                        {{ $log->auditable_label }}
                        <span class="d-block search-hint">{{ class_basename($log->auditable_type) }}</span>
                    </td>
                    <td style="max-width:380px">
                        @if ($log->changes)
                            @foreach ($log->changes as $field => $diff)
                                <div class="small">
                                    <span class="data text-body-secondary">{{ $field }}</span>
                                    @if (is_array($diff))
                                        <span style="opacity:.6">{{ Str::limit((string) ($diff['antes'] ?? '—'), 24) }}</span>
                                        →
                                        <strong>{{ Str::limit((string) ($diff['despues'] ?? '—'), 24) }}</strong>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <span class="text-body-secondary small">—</span>
                        @endif
                    </td>
                    <td class="data small text-body-secondary">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-body-secondary">Sin registros todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
