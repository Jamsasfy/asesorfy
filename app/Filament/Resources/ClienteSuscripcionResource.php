<?php

namespace App\Filament\Resources;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Filament\Resources\ClienteSuscripcionResource\Pages;
use App\Filament\Resources\ClienteSuscripcionResource\RelationManagers;
use App\Models\ClienteSuscripcion;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class ClienteSuscripcionResource extends Resource
{
    protected static ?string $model = ClienteSuscripcion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

     public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cliente_id')
                ->relationship('cliente', 'razon_social')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('servicio_id')
                ->relationship('servicio', 'nombre')
                ->preload()
                ->searchable()
                ->required(),

            Select::make('estado')
                ->options(collect(ClienteSuscripcionEstadoEnum::cases())
                    ->mapWithKeys(fn ($estado) => [$estado->value => $estado->name]))
                ->required(),

            DatePicker::make('fecha_inicio')->required(),
            DatePicker::make('fecha_fin'),

            TextInput::make('precio_acordado')->numeric()->prefix('€')->required(),
            TextInput::make('cantidad')->numeric()->default(1),
            TextInput::make('ciclo_facturacion'),

            DatePicker::make('proxima_fecha_facturacion'),

            TextInput::make('descuento_tipo'),
            TextInput::make('descuento_valor')->numeric(),
            TextInput::make('descuento_descripcion'),
            DatePicker::make('descuento_valido_hasta'),

            TextInput::make('stripe_subscription_id'),
            Textarea::make('observaciones'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('cliente.razon_social')->searchable(),
            Tables\Columns\TextColumn::make('servicio.nombre')->searchable(),
            Tables\Columns\TextColumn::make('estado')->badge(),
            Tables\Columns\TextColumn::make('fecha_inicio')->date(),
            Tables\Columns\TextColumn::make('fecha_fin')->date(),
            Tables\Columns\TextColumn::make('precio_acordado')->money('EUR'),
            Tables\Columns\TextColumn::make('ciclo_facturacion'),
        ])
        ->filters([])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienteSuscripcions::route('/'),
            'create' => Pages\CreateClienteSuscripcion::route('/create'),
            'edit' => Pages\EditClienteSuscripcion::route('/{record}/edit'),
        ];
    }
}
