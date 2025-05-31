<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;


class MisClientes extends ListRecords
{

    protected static string $resource = ClienteResource::class;
    


    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('asesor_id', auth()->id());
    }

    public function hasTableSearch(): bool  { return false; }
    public function hasTableFilters(): bool { return false; }
    public function canCreate(): bool       { return false; }


}