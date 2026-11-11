<?php

namespace App\Filament\Resources\Attributes\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\Import\TaxonomyResolver;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.attributes.values');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label_ka')
                    ->label(__('admin.attributes.label_field').' · '.__('admin.locales.ka'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('label_en')
                    ->label(__('admin.attributes.label_field').' · '.__('admin.locales.en'))
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('admin.attributes.code'))
                    ->helperText(__('admin.common.slug_help'))
                    ->alphaDash()
                    ->maxLength(64)
                    ->unique(table: AttributeValue::class, ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('attribute_id', $this->getOwnerRecord()->getKey())),
                ColorPicker::make('color_hex')
                    ->label(__('admin.attributes.color_hex'))
                    ->visible(fn () => $this->getOwnerRecord()->type === Attribute::TYPE_COLOR),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['kaTranslation', 'enTranslation'])
                ->addSelect(['products_count' => DB::table('attribute_value_product')
                    ->selectRaw('count(*)')
                    ->whereColumn('attribute_value_product.attribute_value_id', 'attribute_values.id')]))
            ->columns([
                TextColumn::make('kaTranslation.label')
                    ->label(__('admin.attributes.label_field'))
                    ->description(fn (AttributeValue $record) => $record->enTranslation?->label)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('translations', fn (Builder $query) => $query->where('label', 'like', "%{$search}%"))),
                TextColumn::make('code')
                    ->label(__('admin.attributes.code'))
                    ->color('gray'),
                ColorColumn::make('color_hex')
                    ->label(__('admin.attributes.color_hex'))
                    ->visible(fn () => $this->getOwnerRecord()->type === Attribute::TYPE_COLOR),
                TextColumn::make('products_count')
                    ->label(__('admin.attributes.specs_count'))
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data) => $this->saveValue(new AttributeValue(['attribute_id' => $this->getOwnerRecord()->getKey()]), $data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (AttributeValue $record) => [
                        'label_ka' => $record->translations()->where('locale', 'ka')->value('label'),
                        'label_en' => $record->translations()->where('locale', 'en')->value('label'),
                        'code' => $record->code,
                        'color_hex' => $record->color_hex,
                    ])
                    ->using(fn (AttributeValue $record, array $data) => $this->saveValue($record, $data)),
                DeleteAction::make(),
            ]);
    }

    protected function saveValue(AttributeValue $value, array $data): AttributeValue
    {
        $value->fill([
            'code' => filled($data['code'] ?? null) ? $data['code'] : TaxonomyResolver::valueCode((string) $data['label_ka']),
            'color_hex' => $data['color_hex'] ?? null,
        ]);

        if (! $value->exists) {
            $value->sort_order = (int) AttributeValue::query()->where('attribute_id', $value->attribute_id)->max('sort_order') + 1;
        }

        $value->save();
        $value->saveTranslations([
            'ka' => ['label' => $data['label_ka']],
            'en' => ['label' => $data['label_en'] ?? null],
        ]);

        if (blank($data['label_en'] ?? null)) {
            $value->translations()->where('locale', 'en')->delete();
        }

        return $value;
    }
}
