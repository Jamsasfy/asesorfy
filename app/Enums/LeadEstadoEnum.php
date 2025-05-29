<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadEstadoEnum: string implements HasLabel
{
    case SIN_GESTIONAR          = 'sin_gestionar';
    case INTENTO_CONTACTO       = 'intento_contacto';
    case CONTACTADO             = 'contactado';
    case ANALISIS_NECESIDADES   = 'analisis_necesidades';
    case ESPERANDO_INFORMACION  = 'esperando_informacion';
    case PROPUESTA_ENVIADA      = 'propuesta_enviada';
    case EN_NEGOCIACION         = 'en_negociacion';
    case CONVERTIDO             = 'convertido';
    case DESCARTADO             = 'descartado';

    public function getLabel(): string
    {
        return match ($this) {
            self::SIN_GESTIONAR         => 'Sin Gestionar',
            self::INTENTO_CONTACTO      => 'Intento Contacto',
            self::CONTACTADO            => 'Contactado',
            self::ANALISIS_NECESIDADES  => 'Análisis de Necesidades',
            self::ESPERANDO_INFORMACION => 'Esperando Información',
            self::PROPUESTA_ENVIADA     => 'Propuesta Enviada',
            self::EN_NEGOCIACION        => 'En Negociación',
            self::CONVERTIDO            => 'Convertido',
            self::DESCARTADO            => 'Descartado',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::CONVERTIDO, self::DESCARTADO => true,
            default                             => false,
        };
    }

    public function isConvertido(): bool
    {
        return $this === self::CONVERTIDO;
    }

    public function isEnProgreso(): bool
    {
        return match ($this) {
            self::INTENTO_CONTACTO,
            self::CONTACTADO,
            self::ANALISIS_NECESIDADES,
            self::ESPERANDO_INFORMACION,
            self::PROPUESTA_ENVIADA,
            self::EN_NEGOCIACION => true,
            default               => false,
        };
    }

    public function isInicial(): bool
    {
        return match ($this) {
            self::SIN_GESTIONAR => true,
            default                                     => false,
        };
    }
}
