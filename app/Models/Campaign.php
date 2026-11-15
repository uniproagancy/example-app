<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Catalog;
use App\Support\HomeContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['slug', 'title', 'text', 'badge', 'discount_percent', 'image', 'theme', 'position', 'is_active', 'starts_at', 'ends_at'])]
class Campaign extends Model
{
    use HasFactory;
    use LogsActivity;

    public const BACKGROUNDS = [
        'navy' => 'bg-navy',
        'red' => 'bg-[linear-gradient(135deg,#8E0E16_0%,#E5303C_100%)]',
        'blue' => 'bg-[linear-gradient(135deg,#0A2F8F_0%,#0B63F6_100%)]',
        'indigo' => 'bg-[linear-gradient(135deg,#221560_0%,#5B45E0_100%)]',
    ];

    public const THEMES = ['navy', 'red', 'blue', 'indigo'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'text' => 'array',
            'badge' => 'array',
            'discount_percent' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }

    public static function discountedPrice(float $price, int $percent): float
    {
        return max(1, round($price * (100 - $percent) / 100));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position')->orderByPivot('position');
    }

    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public static function listing(): Collection
    {
        return once(function () {
            $products = Catalog::products()->keyBy('id');

            return static::query()
                ->live()
                ->with('products:id')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (Campaign $campaign) => $campaign->present($products))
                ->values();
        });
    }

    public static function findLive(string $slug): ?array
    {
        return static::listing()->firstWhere('slug', $slug);
    }

    public function present(Collection $products): array
    {
        $items = $this->products
            ->map(fn (Product $product) => $products->get($product->id))
            ->filter()
            ->values();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => HomeContent::localized($this->title),
            'text' => HomeContent::localized($this->text),
            'badge' => HomeContent::localized($this->badge) ?? ($this->discount_percent ? '−'.$this->discount_percent.'%' : null),
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'background' => self::BACKGROUNDS[$this->theme] ?? self::BACKGROUNDS['navy'],
            'url' => route('promotions.show', ['campaign' => $this->slug]),
            'endsAt' => $this->ends_at ? Carbon::parse($this->ends_at)->toIso8601String() : null,
            'products' => $items,
            'count' => $items->count(),
            'maxDiscount' => (int) $items
                ->filter(fn (array $product) => $product['old'])
                ->map(fn (array $product) => round(($product['old'] - $product['price']) / $product['old'] * 100))
                ->max(),
        ];
    }
}
