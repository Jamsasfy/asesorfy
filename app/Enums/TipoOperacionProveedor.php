<?php

namespace App\Enums;

enum TipoOperacionProveedor: string
{
    case INTERIOR_DEDUCIBLE = 'interior_deducible';
    case INTRACOMUNITARIA_BIENES = 'intracomunitaria_bienes';
    case INTRACOMUNITARIA_SERVICIOS = 'intracomunitaria_servicios';
    case COMPENSACIONES_AGRARIAS = 'compensaciones_agrarias';
    case IMPORTACIONES = 'importaciones';
    case INVERSION_SUJETO_PASIVO = 'inversion_sujeto_pasivo';
    case IVA_NO_DEDUCIBLE = 'iva_no_deducible';

    public function label(): string
    {
        return match ($this) {
            self::INTERIOR_DEDUCIBLE => '1 Operaciones interiores IVA deducible',
            self::INTRACOMUNITARIA_BIENES => '2 Compras intracomunitarias de bienes',
            self::INTRACOMUNITARIA_SERVICIOS => '3 Compras intracomunitarias de servicios',
            self::COMPENSACIONES_AGRARIAS => '4 Compensaciones agrarias',
            self::IMPORTACIONES => '5 Importaciones',
            self::INVERSION_SUJETO_PASIVO => '6 Inversión del Sujeto Pasivo',
            self::IVA_NO_DEDUCIBLE => '7 IVA no deducible',
        };
    }
}