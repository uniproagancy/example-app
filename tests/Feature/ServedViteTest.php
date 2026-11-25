<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ServedViteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $webRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshApplicationWithLocale('ka');
        $this->webRoot = storage_path('framework/testing/web-root-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->webRoot);

        parent::tearDown();
    }

    public function test_pages_ask_for_the_build_files_that_the_web_root_actually_serves(): void
    {
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
        $script = $manifest['resources/js/app.js']['file'];
        $style = $manifest['resources/css/app.css']['file'];

        $manifest['resources/js/app.js']['file'] = 'assets/app-served.js';
        $manifest['resources/css/app.css']['file'] = 'assets/app-served.css';
        File::ensureDirectoryExists($this->webRoot.'/build');
        File::put($this->webRoot.'/build/manifest.json', json_encode($manifest));

        $page = $this->withServerVariables(['DOCUMENT_ROOT' => $this->webRoot])->get('/ka')->assertOk()->getContent();

        $this->assertStringContainsString('build/assets/app-served.js', $page);
        $this->assertStringContainsString('build/assets/app-served.css', $page);
        $this->assertStringNotContainsString($script, $page);
        $this->assertStringNotContainsString($style, $page);
    }

    public function test_without_a_separate_web_root_the_repository_build_is_used(): void
    {
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);

        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->get('/ka')
            ->assertOk()
            ->assertSee('build/'.$manifest['resources/js/app.js']['file'], false);
    }
}
