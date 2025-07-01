@php
    $record = $getRecord();
    $proyectosHtml = [];
    $suscripcionesHtml = [];

    if ($record->venta) {
        // --- Proyectos Pendientes ---
        $otrosProyectos = $record->venta->proyectos()
            ->where('id', '!=', $record->id)
            ->where('estado', '!=', \App\Enums\ProyectoEstadoEnum::Finalizado)
            ->get();

        foreach ($otrosProyectos as $proyecto) {
            $url = \App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto]);
            // CAMBIO: Icono y texto ahora usan un color amarillo/naranja estándar de Tailwind
            $icon = Blade::render("<x-heroicon-o-briefcase class='h-5 w-5 text-amber-500 flex-shrink-0' />");
            $proyectosHtml[] = "<div><a href='{$url}' target='_blank' class='text-amber-600 hover:underline font-semibold flex items-center space-x-2 min-w-0 truncate'>{$icon}<span>{$proyecto->nombre}</span></a></div>";
        }

        // --- Suscripciones Pendientes (sin cambios) ---
        $suscripciones = $record->venta->suscripciones()
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->with('servicio')
            ->get();

        foreach ($suscripciones as $suscripcion) {
            $icon = Blade::render("<x-heroicon-o-exclamation-triangle class='h-5 w-5 text-danger-600 flex-shrink-0' />");
            $prefix = "<strong class='font-bold'>SERVICIO MENSUAL:</strong>";
            $serviceName = $suscripcion->servicio->nombre;
            $suffix = "<span class='text-xs text-gray-500 dark:text-gray-400'>(se activa cuando finalice los proyectos)</span>";
            $linea1 = "<div class='flex items-center space-x-2 text-sm text-danger-600'><div class='min-w-0 truncate'>{$prefix} {$serviceName}</div></div>";
            $linea2 = "<div class='pl-7'>{$suffix}</div>";
            $suscripcionesHtml[] = "<div class='flex items-start space-x-2 py-1'>{$icon}<div>{$linea1}{$linea2}</div></div>";
        }
    }
    
    $htmlParts = array_merge($proyectosHtml, $suscripcionesHtml);
@endphp

{{-- CAMBIO: Volvemos a poner el div con el borde y el padding --}}
<div class="rounded-lg border border-gray-200 dark:border-white/10 p-4">
    @if (empty($htmlParts))
        <div class="flex items-center text-success-600 font-semibold">
            <x-heroicon-o-check-circle class="h-5 w-5 mr-2" />
            No hay más elementos pendientes en esta venta.
        </div>
    @else
        <div class="space-y-3">
            {!! implode('', $htmlParts) !!}
        </div>
    @endif
</div>