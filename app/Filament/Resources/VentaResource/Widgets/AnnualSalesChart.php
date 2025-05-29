<?php

namespace App\Filament\Resources\VentaResource\Widgets;

use App\Models\Venta;
use Filament\Widgets\ChartWidget;

class AnnualSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Ventas totales de este año';
     // Que el widget ocupe todas las columnas posibles
     protected static ?string $maxHeight = '300px';
     protected int | string | array $columnSpan = 'full';




    protected function getData(): array
    {
        $year = now()->year;

    $results = \App\Models\VentaItem::query()
        ->selectRaw('MONTH(v.fecha_venta) as month, s.tipo, SUM(vi.subtotal) as total')
        ->from('venta_items as vi')
        ->join('ventas as v', 'vi.venta_id', '=', 'v.id')
        ->join('servicios as s', 'vi.servicio_id', '=', 's.id')
        ->whereYear('v.fecha_venta', $year)
        ->groupByRaw('month, s.tipo')
        ->orderBy('month')
        ->get()
        ->groupBy('tipo');

    // Etiquetas en español fijo
    $labels = [
        'Enero', 'Febrero', 'Marzo', 'Abril',
        'Mayo', 'Junio', 'Julio', 'Agosto',
        'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    $buildDataset = fn(string $tipo, string $label, string $color) => [
        'label'           => $label,
        'data'            => array_map(fn($i) => 
            (float) ($results[$tipo]->pluck('total', 'month')[$i+1] ?? 0)
        , range(0, 11)),
        'backgroundColor' => $color,
    ];

    return [
        'labels'   => $labels,
        'datasets' => [
            $buildDataset('unico',      'Único',      'rgba(75, 192, 192, 0.6)'),
            $buildDataset('recurrente', 'Recurrente', 'rgba(255, 159, 64, 0.6)'),
        ],
    ];
    }


    






    protected function getType(): string
    {
        return 'bar';
    }
}
