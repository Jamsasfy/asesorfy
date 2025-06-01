<x-filament-panels::page>
 {{-- INICIO DE TU SECCIÓN PERSONALIZADA DE ESTADÍSTICAS --}}
    <div class="mb-6 space-y-4">
        @if($nombreAsesor)
            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    Resumen para: {{ $nombreAsesor }}
                </h2>
            </div>

            {{-- Contenedor de la rejilla MODIFICADO --}}
<div class="grid grid-cols-4 gap-6">

                {{-- Tarjeta 1: Total Mis Clientes --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">
                        Mis Clientes Totales
                    </h3>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $totalMisClientes }}
                    </p>
                </div>

                {{-- Tarjeta 2: Mis Clientes Activos --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">
                        Mis Clientes Activos
                    </h3>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $misClientesActivos }}
                    </p>
                </div>

                {{-- Tarjeta 3: Clientes (Atención/Impago) --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700">
                    <h3 class="text-lg font-medium {{ $misClientesAtencionImpago > 0 ? 'text-danger-500 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">
                        Atención / Impago
                    </h3>
                    <p class="mt-1 text-3xl font-semibold {{ $misClientesAtencionImpago > 0 ? 'text-danger-600 dark:text-danger-500' : 'text-gray-900 dark:text-gray-100' }}">
                        {{ $misClientesAtencionImpago }}
                    </p>
                    @if($misClientesAtencionImpago > 0)
                        <p class="text-xs text-danger-500 dark:text-danger-400">Requieren acción</p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">Todo al día</p>
                    @endif
                </div>

                {{-- Tarjeta 4: Documentos Pendientes (Placeholder) --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700">
                    <h3 class="text-lg font-medium text-green-500 dark:text-gray-400">
                        Docs. por Verificar
                    </h3>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $documentosPendientesPlaceholder }} <span class="text-sm">(Próximamente)</span>
                    </p>
                </div>

            </div>
        @endif
    </div>
    {{-- FIN DE TU SECCIÓN PERSONALIZADA DE ESTADÍSTICAS --}}
    
    {{ $this->table }}
</x-filament-panels::page>