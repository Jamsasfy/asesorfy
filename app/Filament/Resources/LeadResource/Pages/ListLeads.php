<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Widgets\LeadStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;






class ListLeads extends ListRecords
{
   
    protected static string $resource = LeadResource::class;
    


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LeadStatsOverview::class,
            // Otros widgets si los hubiera...
        ];
    }



}
