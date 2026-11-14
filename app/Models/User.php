<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Notifications\ResetPasswordNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;
    use LogsActivity;

    public const NOTIFICATION_CHANNELS = ['sms', 'mail', 'promo'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'is_admin' => 'boolean',
            'is_operator' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true || $this->is_operator === true;
    }

    public function isOperator(): bool
    {
        return $this->is_admin !== true && $this->is_operator === true;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class)->latest('id');
    }

    public function favoriteIds(): array
    {
        return $this->favorites()->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    public function addFavorites(array $productIds): void
    {
        $existing = $this->favorites()->pluck('product_id')->map(fn ($id) => (int) $id);
        $room = Favorite::MAX_PER_USER - $existing->count();

        $newIds = Product::query()
            ->where('is_active', true)
            ->whereIn('id', array_map('intval', $productIds))
            ->whereNotIn('id', $existing)
            ->pluck('id')
            ->take(max(0, $room));

        $newIds->each(fn (int $productId) => $this->favorites()->firstOrCreate(['product_id' => $productId]));
    }

    public function removeFavorite(int $productId): void
    {
        $this->favorites()->where('product_id', $productId)->delete();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function firstName(): string
    {
        return (string) str($this->name)->trim()->before(' ');
    }

    public function wantsNotification(string $channel): bool
    {
        $preferences = $this->notification_preferences ?? ['sms' => true, 'mail' => true, 'promo' => false];

        return (bool) ($preferences[$channel] ?? false);
    }
}
