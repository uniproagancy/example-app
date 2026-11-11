<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'topic', 'name', 'phone', 'order_number', 'message', 'locale', 'handled_at'])]
class ContactMessage extends Model
{
    public const TOPICS = ['consultation', 'order', 'warranty', 'installment'];

    public const TOPICS_WITH_ORDER = ['order', 'warranty'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
