@php
    $record = $getRecord();
    $usuario = $record->user?->name ?? 'Usuario';
    $iniciales = strtoupper(Str::of($usuario)->substr(0, 2));
    $contenido = e($record->contenido);
    $fecha = $record->created_at?->format('d/m/Y H:i');
    $fechaLarga = $record->created_at?->format('d/m/Y H:i:s');
@endphp

<div style="
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin: 0.25rem 0;
">

    {{-- Avatar --}}
    <div style="
        width: 36px;
        height: 36px;
        border-radius: 9999px;
        background-color: #3b82f6;
        color: white;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    ">
        {{ $iniciales }}
    </div>

    {{-- Burbuja del comentario --}}
    <div style="
        background-color: #e0f2fe;
        color: #1e3a8a;
        padding: 0.5rem 0.75rem;
        border-radius: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        flex-grow: 1;
        font-size: 0.9rem;
        line-height: 1.2;
        max-width: 100%;
        overflow-wrap: break-word;
    ">
        <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <span style="font-weight: 600;">
                🧑‍💼 {{ $usuario }}
            </span>
            <span style="font-size: 0.75rem; color: #475569;" title="{{ $fechaLarga }}">
                🕓 {{ $fecha }}
            </span>
        </div>
        <div style="white-space: pre-line;">
            {{ $contenido }}
        </div>
    </div>
</div>
