<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFileTest extends TestCase
{
    public function test_uploaded_files_are_served_without_the_storage_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/8/photo.webp', 'image-bytes');

        $response = $this->get('/storage/products/8/photo.webp')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=604800, public')
            ->assertCookieMissing(config('session.cookie'));

        $this->assertSame('image-bytes', $response->getFile()->getContent());
    }

    public function test_missing_files_and_paths_outside_the_disk_are_not_found(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/8/photo.webp', 'image-bytes');

        $this->get('/storage/products/8/missing.webp')->assertNotFound();
        $this->get('/storage/products/8')->assertNotFound();
        $this->get('/storage/products/../../.env')->assertNotFound();
        $this->get('/storage/.gitignore')->assertNotFound();
    }
}
