<?php

namespace App\Console\Commands;

use App\Http\Controllers\ImportPushController;
use App\Models\Supplier;
use App\Services\Import\Drivers\Zoommer\BlockedByZoommer;
use App\Services\Import\ImageDownloader;
use App\Services\Import\ProductPayload;
use App\Services\Import\SupplierDriver;
use App\Support\Redact;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PushImport extends Command
{
    protected $signature = 'import:push
        {supplier=zoommer : supplier code on the server}
        {--only=* : import only these external ids}
        {--from= : first id to scan}
        {--to= : last id to scan}
        {--full : scan the whole from-to range, not only known ids and the next ones}
        {--limit=0 : stop after this many saved products}
        {--pause=300 : milliseconds to wait between products}';

    protected $description = 'Read a supplier from this computer and save the products and images on the server';

    public function handle(): int
    {
        $server = rtrim((string) config('import.push_url'), '/');

        $secure = str_starts_with($server, 'https://') || preg_match('#\Ahttp://(127\.0\.0\.1|localhost)(:\d+)?\z#', $server);

        if (strlen((string) config('import.push_token')) < 32 || ! $secure) {
            $this->error('Set IMPORT_PUSH_URL (https) and IMPORT_PUSH_TOKEN (at least 32 characters, the same as on the server) in .env.');

            return self::FAILURE;
        }

        $answer = $this->server()->get($server.'/import-push/'.rawurlencode((string) $this->argument('supplier')));

        if (! $answer->successful()) {
            $this->error('The server refused: HTTP '.$answer->status().'. Check IMPORT_PUSH_TOKEN on both sides and that the supplier is active.');

            return self::FAILURE;
        }

        $driverClass = (string) $answer->json('driver');

        if (! in_array($driverClass, ImportPushController::DRIVERS, true)) {
            $this->error('This supplier cannot be pushed from here.');

            return self::FAILURE;
        }

        $config = array_replace((array) $answer->json('config'), array_filter([
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'full_scan' => $this->option('full') ? true : null,
        ], fn ($value) => $value !== null));

        $supplier = (new Supplier)->forceFill([
            'code' => (string) $answer->json('code'),
            'driver' => $driverClass,
            'config' => $config,
        ]);

        /** @var SupplierDriver $driver */
        $driver = new $driverClass($supplier);
        $driver->knownIds = (array) $answer->json('known_ids', []);

        $ids = $this->option('only') ?: $driver->ids();
        $endpoint = $server.'/import-push/'.rawurlencode($supplier->code);
        $limit = (int) $this->option('limit');
        $counts = ['saved' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];

        foreach ($ids as $id) {
            try {
                $payload = $driver->fetch((string) $id);
            } catch (BlockedByZoommer $e) {
                $this->error($e->getMessage());

                break;
            } catch (\Throwable $e) {
                $counts['failed']++;
                $this->warn("{$id}: ".Redact::secrets($e->getMessage()));

                continue;
            }

            if (! $payload) {
                $counts['missing']++;

                continue;
            }

            $status = $this->push($endpoint, $payload);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $this->line("{$id}: {$status}");

            if ($limit > 0 && $counts['saved'] >= $limit) {
                break;
            }

            usleep(max(0, (int) $this->option('pause')) * 1000);
        }

        $this->newLine();
        $this->table(array_keys($counts), [array_values($counts)]);

        return self::SUCCESS;
    }

    protected function push(string $endpoint, ProductPayload $payload): string
    {
        $fields = ['payload' => json_encode(get_object_vars($payload), JSON_UNESCAPED_UNICODE)];
        $answer = $this->send($endpoint, $fields);

        if ($answer?->json('status') === 'needs_images') {
            $answer = $this->send($endpoint, $fields, $this->images($payload->images));
        }

        if (! $answer?->successful()) {
            $this->warn($payload->externalId.': server answered HTTP '.($answer?->status() ?? 'nothing').' '.mb_substr(strip_tags((string) $answer?->body()), 0, 200));

            return 'failed';
        }

        return (string) $answer->json('status', 'failed');
    }

    protected function send(string $endpoint, array $fields, array $images = []): ?Response
    {
        $request = $this->server()->timeout(120)->asMultipart();

        foreach ($images as $index => $bytes) {
            $request = $request->attach("images[{$index}]", $bytes, "image-{$index}");
        }

        try {
            return $request->post($endpoint, $fields);
        } catch (\Throwable $e) {
            $this->warn('Could not reach the server: '.Redact::secrets($e->getMessage()));

            return null;
        }
    }

    protected function images(array $urls): array
    {
        $images = [];

        foreach (array_slice($urls, 0, ImageDownloader::MAX_IMAGES, true) as $index => $url) {
            try {
                $response = Http::timeout(20)->connectTimeout(10)
                    ->withHeaders(['User-Agent' => (string) (config('services.zoommer.user_agent') ?: config('import.user_agent')), 'Accept' => 'image/avif,image/webp,image/*;q=0.8'])
                    ->get($url);
            } catch (\Throwable) {
                continue;
            }

            $bytes = $response->body();

            if ($response->successful() && $bytes !== '' && strlen($bytes) <= (int) config('import.max_image_bytes')) {
                $images[$index] = $bytes;
            }
        }

        return $images;
    }

    protected function server(): PendingRequest
    {
        return Http::withToken((string) config('import.push_token'))->acceptJson()->connectTimeout(15)->timeout(60);
    }
}
