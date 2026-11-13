<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Operators;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProductResource extends Resource
{
    use HasAdminLabels;

    protected static string $labelKey = 'products';

    protected static string $groupKey = 'catalog';

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return Operators::active() && $action !== 'viewAny' ? Response::deny() : parent::getAuthorizationResponse($action, $record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['kaTranslation', 'brand', 'category.kaTranslation', 'coverImage']);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->kaTranslation?->name ?? (string) $record?->sku;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['sku', 'supplier_sku', 'kaTranslation.name', 'enTranslation.name'];
    }

    public static function getRelations(): array
    {
        return [
            OffersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
