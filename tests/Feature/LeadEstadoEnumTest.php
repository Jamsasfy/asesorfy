<?php

use App\Enums\LeadEstadoEnum;

it('contiene los valores básicos', function () {
    expect(LeadEstadoEnum::cases())
        ->sequence(
            fn ($case) => $case->value !== '',    // ningún valor vacío
        )
        ->toContain(LeadEstadoEnum::SIN_GESTIONAR)
        ->toContain(LeadEstadoEnum::CONTACTADO);
});
