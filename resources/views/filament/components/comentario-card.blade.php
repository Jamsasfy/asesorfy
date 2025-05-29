@php
    $record = $getRecord();
@endphp

<div class="flex items-start gap-3 py-3">
    {{-- Avatar --}}
    <x-filament-panels::avatar.user :user="$record->user" size="sm" />

    {{-- Burbuja estilo WhatsApp --}}
    <div class="max-w-2xl bg-green-300 dark:bg-green-900 rounded-2xl px-4 py-3 shadow-sm w-full">
        <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-semibold text-green-900 dark:text-green-200">
                {{ $record->user->name }}
            </span>
            <span class="text-xs text-green-800 dark:text-green-400">
                {{ $record->created_at->format('d/m/y H:i') }}
            </span>
        </div>
        <div class="text-sm text-gray-800 dark:text-gray-100">
            {{ $record->contenido }}
        </div>
    </div>
</div>
