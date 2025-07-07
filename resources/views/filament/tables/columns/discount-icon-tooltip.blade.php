@php
    $record = $getRecord();
@endphp

@if ($record->descuento_tipo)
    <div class="flex justify-center">
        {{-- CAMBIO: Añadimos style="color: ..." para forzar el color --}}
        <x-heroicon-o-exclamation-circle class="h-5 w-5" style="color: #f59e0b;" />
    </div>
@endif