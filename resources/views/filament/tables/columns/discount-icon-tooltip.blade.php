@php
    $record = $getRecord();
    // Comprobamos si el descuento existe y si su fecha de fin aún no ha pasado.
    $descuentoVigente = $record->descuento_tipo && $record->descuento_valido_hasta && now()->lte($record->descuento_valido_hasta);
@endphp

{{-- Mostramos algo solo si hay un tipo de descuento --}}
@if ($record->descuento_tipo)
    <div class="flex justify-center">
        @if ($descuentoVigente)
            {{-- Icono AMARILLO si el descuento está EN CURSO --}}
            <x-heroicon-o-exclamation-circle class="h-5 w-5" style="color: #f59e0b;" />
        @else
            {{-- Icono VERDE si el descuento está FINALIZADO --}}
            <x-heroicon-o-check-circle class="h-5 w-5" style="color: #16a34a;" />
        @endif
    </div>
@endif