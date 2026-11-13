<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Import\ImageDownloader;
use App\Support\WebpImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebpImageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function png(int $width, int $height): string
    {
        return (string) UploadedFile::fake()->image('picture.png', $width, $height)->get();
    }

    protected function assertWebp(string $bytes): void
    {
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
    }

    public function test_pictures_are_converted_and_large_ones_are_scaled_down(): void
    {
        config(['images.max_side' => 300]);

        $webp = WebpImage::fromBytes($this->png(900, 450));

        $this->assertNotNull($webp);
        $this->assertWebp($webp);
        $this->assertSame([300, 150], array_slice(getimagesizefromstring($webp), 0, 2));

        $this->assertSame([120, 80], array_slice(getimagesizefromstring(WebpImage::fromBytes($this->png(120, 80))), 0, 2));
    }

    public function test_webp_animated_gif_and_broken_input_are_left_alone(): void
    {
        $webp = WebpImage::fromBytes($this->png(40, 40));
        $frame = "\x00\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C";
        $animated = 'GIF89a'.pack('vv', 1, 1)."\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF".$frame.'....'.$frame.'....;';

        $this->assertNull(WebpImage::fromBytes($webp));
        $this->assertNull(WebpImage::fromBytes($animated));
        $this->assertNull(WebpImage::fromBytes('<html>not a picture</html>'));
        $this->assertNull(WebpImage::fromBytes(''));
    }

    public function test_supplier_pictures_are_stored_as_webp(): void
    {
        Storage::fake('public');
        Http::fake(['93.184.216.34/*' => Http::response($this->png(200, 200), 200, ['Content-Type' => 'image/png'])]);
        $product = Product::query()->firstOrFail();
        $product->images()->delete();

        app(ImageDownloader::class)->sync($product, ['https://93.184.216.34/a.png']);

        $image = $product->images()->sole();
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertWebp(Storage::disk('public')->get($image->path));
    }

    public function test_command_reports_first_and_converts_existing_pictures_only_when_asked(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/old.png', $this->png(60, 60));
        [$first, $second] = Product::query()->limit(2)->get()->all();
        ProductImage::query()->delete();
        $first->images()->create(['path' => 'products/1/old.png', 'position' => 1]);
        $second->images()->create(['path' => 'products/1/old.png', 'position' => 1]);
        $second->images()->create(['path' => 'products/1/missing.jpg', 'position' => 2]);

        $this->artisan('images:webp')->expectsOutputToContain('2 product picture files are not WebP yet')->assertSuccessful();
        Storage::disk('public')->assertExists('products/1/old.png');

        $this->artisan('images:webp', ['--apply' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('products/1/old.png');
        Storage::disk('public')->assertExists('products/1/old.webp');
        $this->assertSame(2, ProductImage::query()->where('path', 'products/1/old.webp')->count());
        $this->assertSame(1, ProductImage::query()->where('path', 'products/1/missing.jpg')->count());
    }
}
