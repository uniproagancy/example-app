<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_spec_id', 'locale', 'value'])]
class ProductSpecTranslation extends Model
{
    public $timestamps = false;

    public function productSpec(): BelongsTo
    {
        return $this->belongsTo(ProductSpec::class);
    }
}
