<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_spec_id', 'locale', 'value'])]
class ProductSpecTranslation extends Model
{
    public $timestamps = false;
}
