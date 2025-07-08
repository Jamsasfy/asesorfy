<?php

namespace App\Enums;

use Filament\Support\Colors\Color; // Importar si quieres usar colores para Filament
use Filament\Support\Contracts\HasColor; // Para colores
use Filament\Support\Contracts\HasLabel; // Para etiquetas

enum FacturaEstadoEnum: string implements HasLabel, HasColor
{
    case PENDIENTE_PAGO = 'pendiente_pago';
    case IMPAGADA       = 'impagada';
    case PAGADA         = 'pagada';
    case ANULADA        = 'anulada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDIENTE_PAGO => 'Pendiente de Pago',
            self::IMPAGADA       => 'Impagada / Vencida',
            self::PAGADA         => 'Pagada',
            self::ANULADA        => 'Anulada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDIENTE_PAGO => Color::Amber, // Amarillo/Naranja
            self::IMPAGADA       => Color::Red,   // Rojo
            self::PAGADA         => Color::Green, // Verde
            self::ANULADA        => Color::Gray,  // Gris
        };
    }
}