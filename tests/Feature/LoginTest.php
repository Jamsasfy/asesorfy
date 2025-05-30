<?php

use function Pest\Laravel\get;

it('muestra la pantalla de login', function () {
    // Ruta estándar de Filament cuando el panel está en /admin
    get('/admin/login')->assertOk();
});