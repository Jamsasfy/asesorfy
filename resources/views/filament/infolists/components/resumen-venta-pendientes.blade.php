@php
    $record = $getRecord();
    $proyectosHtml = [];
    $suscripcionesHtml = [];

    if ($record->venta) {
        // --- Proyectos Pendientes ---
        $otrosProyectos = $record->venta->proyectos()
            ->where('id', '!=', $record->id)
            ->where('estado', '!=', \App\Enums\ProyectoEstadoEnum::Finalizado)
            ->with('user')
            ->get();

        foreach ($otrosProyectos as $proyecto) {
            $url = \App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto]);
            $icon = Blade::render("<x-heroicon-o-briefcase class='h-5 w-5 text-custom-500 flex-shrink-0' style='--c-500:var(--warning-500);' />");
            $proyectoLink = "<a href='{$url}' target='_blank' class='text-custom-600 hover:underline font-semibold min-w-0 truncate' style='--c-600:var(--warning-600);'>{$proyecto->nombre}</a>";

            // --- LÓGICA DEL BADGE (CON ESTILOS INLINE) ---
            $badgeHtml = '';
            if ($proyecto->user) {
                // Si tiene asesor, badge VERDE
                $style = "background-color:#dcfce7; color:#166534; padding:3px 8px; border-radius:9999px; font-size:0.75rem; font-weight:500; white-space:nowrap;";
                $badgeText = $proyecto->user->name;
            } else {
                // Si no tiene, badge AMARILLO
                $style = "background-color:#fef3c7; color:#92400e; padding:3px 8px; border-radius:9999px; font-size:0.75rem; font-weight:500; white-space:nowrap;";
                $badgeText = 'Sin asignar';
            }
            $badgeHtml = "<span style='{$style}'>{$badgeText}</span>";
            
            $proyectosHtml[] = "<div class='flex items-center space-x-2 py-1'>{$icon} {$proyectoLink} <span class='text-gray-400 mx-1'>-</span> {$badgeHtml}</div>";
        }

        // --- Suscripciones Pendientes (sin cambios) ---
        $suscripciones = $record->venta->suscripciones()
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->with('servicio')
            ->get();

        foreach ($suscripciones as $suscripcion) {
            $icon = Blade::render("<x-heroicon-o-exclamation-triangle class='h-5 w-5 text-custom-500 flex-shrink-0' style='--c-500:var(--danger-500);' />");
            $prefix = "<strong class='font-bold'>SERVICIO MENSUAL:</strong>";
            $serviceName = $suscripcion->servicio->nombre;
            $suffix = "<span class='text-xs text-gray-500 dark:text-gray-400'>(se activa cuando finalice los proyectos)</span>";
            $linea1 = "<div class='flex items-center space-x-2 text-sm text-custom-600' style='--c-600:var(--danger-600);'><div class='min-w-0 truncate'>{$prefix} {$serviceName}</div></div>";
            $linea2 = "<div class='pl-7'>{$suffix}</div>";
            $suscripcionesHtml[] = "<div class='flex items-start space-x-2 py-1'>{$icon}<div>{$linea1}{$linea2}</div></div>";
        }
    }
    
    $htmlParts = array_merge($proyectosHtml, $suscripcionesHtml);
@endphp

@if (empty($htmlParts))
        {{-- CAMBIO: Usamos style="..." para el color del texto y del icono --}}
        <div class="flex items-center text-sm font-semibold" style="color: #16a34a;">
            <x-heroicon-o-check-circle class="h-5 w-5 mr-2" style="color: #16a34a;" />
            No hay elementos pendientes en esta venta, o es un proyecto único.
        </div>
    @else
        <div class="space-y-3">
            {!! implode('', $htmlParts) !!}
        </div>
    @endif