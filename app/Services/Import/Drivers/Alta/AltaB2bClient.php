<?php

namespace App\Services\Import\Drivers\Alta;

use App\Support\Figure;
use App\Support\Redact;
use Illuminate\Support\Facades\Log;
use SoapClient;

class AltaB2bClient
{
    protected ?SoapClient $soap = null;

    public function __construct(protected array $config = []) {}

    public function all(): array
    {
        return $this->ask('');
    }

    public function one(string $item): ?array
    {
        return $this->ask($item)[0] ?? null;
    }

    protected function ask(string $item): array
    {
        $user = (string) ($this->config['user'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($user === '' || $password === '') {
            Log::channel('import')->warning('alta b2b has no credentials', [
                'hint' => 'set ALTA_B2B_USER and ALTA_B2B_PASSWORD',
            ]);

            throw new AltaUnavailable('Alta B2B credentials are not configured.');
        }

        try {
            $response = $this->soap()->GetPriceList([
                'user' => $user,
                'password' => $password,
                'item' => $item,
            ]);
        } catch (\Throwable $e) {
            Log::channel('import')->warning('alta b2b refused', [
                'item' => $item ?: '(all)',
                'error' => Redact::secrets($e->getMessage()),
            ]);

            throw new AltaUnavailable('Alta B2B could not be reached. The previous data was preserved.', 0, $e);
        }

        $items = $response->PriceList->items->item ?? null;

        if ($items === null) {

            Log::channel('import')->warning('alta b2b answered with no items', [
                'item' => $item ?: '(all)',
            ]);

            throw new AltaUnavailable('Alta B2B returned an empty envelope. The previous data was preserved.');
        }

        $rows = [];

        foreach (is_array($items) ? $items : [$items] as $row) {
            $built = $this->row($row);

            if ($built) {
                $rows[] = $built;
            }
        }

        return $rows;
    }

    protected function row(object $item): ?array
    {
        $code = trim((string) ($item->item ?? ''));

        if ($code === '') {
            return null;
        }

        return [
            'item' => $code,
            'model' => trim((string) ($item->item_model ?? '')) ?: null,
            'name' => $this->name((string) ($item->item_full_name ?? '')),
            'quantity' => $this->quantity($item->qty_text ?? null),
            'price' => Figure::amount($item->price_unit ?? null, Figure::DOT),
            'brand' => trim((string) ($item->brand ?? '')) ?: null,
            'category' => trim((string) ($item->category ?? '')) ?: null,

            'weight' => ($kg = (float) ($item->weight_kg ?? 0)) > 0 ? (int) round($kg * 1000) : null,
        ];
    }

    public function quantity(mixed $text): int
    {
        $text = mb_strtolower(trim((string) $text));

        if ($text === '') {
            return 0;
        }

        if (preg_match('/\d+/', $text, $m)) {
            return max(0, (int) $m[0]);
        }

        return preg_match('/out|not|unavailable|არ/u', $text) ? 0 : 1;
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    protected function name(string $value): string
    {
        $clean = $this->clean($value);

        if (! preg_match('#^(\S+)/\s*(\S.*)$#u', $clean, $m)) {
            return $clean;
        }

        return str_starts_with(mb_strtolower($m[2]), mb_strtolower($m[1]).' ') ? $m[2] : $clean;
    }

    protected function soap(): SoapClient
    {
        return $this->soap ??= new SoapClient(
            (string) ($this->config['wsdl'] ?? 'http://extra.alta.com.ge/b2b/b2bEWS?WSDL'),
            [
                'trace' => false,
                'exceptions' => true,
                'encoding' => 'UTF-8',
                'connection_timeout' => (int) ($this->config['timeout'] ?? 30),
                'cache_wsdl' => WSDL_CACHE_MEMORY,
            ],
        );
    }
}
