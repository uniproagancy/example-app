<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsOffice
{
    public function isConfigured(): bool
    {
        return filled(config('services.smsoffice.key')) && filled(config('services.smsoffice.sender'));
    }

    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '995'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '9955')) {
            return $digits;
        }

        return null;
    }

    public function send(?string $phone, string $text, ?string $reference = null): bool
    {
        $destination = static::normalize($phone);

        if (! $this->isConfigured() || $destination === null) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(15)->post((string) config('services.smsoffice.url'), array_filter([
                'key' => config('services.smsoffice.key'),
                'destination' => $destination,
                'sender' => config('services.smsoffice.sender'),
                'content' => $text,
                'urgent' => 'true',
                'reference' => $reference ? mb_substr($reference, 0, 20) : null,
            ]));
        } catch (Throwable $exception) {
            Log::warning('sms not sent', ['destination' => $destination, 'error' => $exception->getMessage()]);

            return false;
        }

        if ($response->successful() && $response->json('Success') === true) {
            return true;
        }

        Log::warning('sms refused', [
            'destination' => $destination,
            'status' => $response->status(),
            'code' => $response->json('ErrorCode'),
            'message' => $response->json('Message'),
        ]);

        return false;
    }
}
