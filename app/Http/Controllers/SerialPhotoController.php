<?php

namespace App\Http\Controllers;

use App\Filament\Courier\Pages\Deliveries;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SerialPhotoController extends Controller
{
    public function __invoke(Request $request, OrderItem $item): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && ($user->is_admin === true || $user->is_operator === true || $user->is_courier === true), 403);
        abort_unless(filled($item->serial_photo) && Storage::disk(Deliveries::PHOTO_DISK)->exists($item->serial_photo), 404);

        return Storage::disk(Deliveries::PHOTO_DISK)->response($item->serial_photo, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
