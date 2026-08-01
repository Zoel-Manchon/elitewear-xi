<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Deja rastro de quién cambió qué, cuándo y desde dónde.
 *
 * Sin esto, un precio que aparece cambiado de madrugada no tiene explicación
 * posible: no se puede saber si fue un administrador legítimo, una cuenta
 * comprometida o un fallo del código. El registro es lo que convierte
 * "algo pasó" en "esto pasó, lo hizo esta cuenta, desde esta IP".
 */
trait Auditable
{
    /** Campos que NUNCA se copian al registro. */
    protected array $auditExclude = [
        'password', 'remember_token', 'token', 'payload',
        'updated_at', 'created_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->recordAudit('created', $model->auditableAttributes()));

        static::updated(function (Model $model) {
            $changes = $model->auditableChanges();

            if ($changes !== []) {
                $model->recordAudit('updated', $changes);
            }
        });

        static::deleted(fn (Model $model) => $model->recordAudit('deleted', []));
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    /** Etiqueta legible del objeto: un id suelto no sirve para investigar nada. */
    public function auditLabel(): string
    {
        return $this->name ?? $this->number ?? $this->code ?? static::class.'#'.$this->getKey();
    }

    protected function auditableAttributes(): array
    {
        return collect($this->getAttributes())
            ->except($this->auditExclude)
            ->all();
    }

    protected function auditableChanges(): array
    {
        $changes = [];

        foreach ($this->getChanges() as $key => $new) {
            if (in_array($key, $this->auditExclude, true)) {
                continue;
            }

            $changes[$key] = [
                'antes' => $this->getOriginal($key),
                'despues' => $new,
            ];
        }

        return $changes;
    }

    protected function recordAudit(string $action, array $changes): void
    {
        $request = request();
        // auth()->user() y no request()->user(): el segundo depende del
        // resolutor de la petición HTTP, que no existe cuando el modelo se
        // toca desde un comando, una cola o un test con actingAs().
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id,
            // Copia del nombre: si la cuenta se borra, el registro sigue
            // diciendo quién fue.
            'actor_label' => $user->name ?? 'sistema',
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'auditable_label' => $this->auditLabel(),
            'changes' => $changes ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
