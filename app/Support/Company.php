<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class Company
{
    public const KEYS = ['company_name', 'company_code', 'company_address', 'company_director', 'invoice_note', 'invoice_stamp'];

    public static function details(): array
    {
        return [
            'name' => Setting::get('company_name') ?: Site::name(),
            'code' => Setting::get('company_code'),
            'address' => Setting::get('company_address'),
            'director' => Setting::get('company_director'),
            'phone' => Site::phone(),
            'email' => Site::email(),
        ];
    }

    public static function bankAccounts(): array
    {
        $accounts = json_decode((string) Setting::get('bank_accounts'), true);

        return collect(is_array($accounts) ? $accounts : [])
            ->filter(fn ($account) => is_array($account) && filled($account['iban'] ?? null))
            ->map(fn (array $account) => [
                'bank' => $account['bank'] ?? null,
                'iban' => mb_strtoupper(preg_replace('/\s+/', '', (string) $account['iban'])),
                'swift' => filled($account['swift'] ?? null) ? mb_strtoupper(trim($account['swift'])) : null,
                'currency' => $account['currency'] ?? null,
            ])
            ->values()
            ->all();
    }

    public static function invoiceNote(): ?string
    {
        return Setting::get('invoice_note');
    }

    public static function stampUrl(): ?string
    {
        $path = Setting::get('invoice_stamp');

        return filled($path) && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
