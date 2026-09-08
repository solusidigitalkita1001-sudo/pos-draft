<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Organizations\HandleMidtransNotificationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MidtransWebhookController extends Controller
{
    /**
     * Public endpoint Midtrans calls with a payment notification. No
     * auth (Midtrans isn't a logged-in user), no CSRF (see
     * bootstrap/app.php `preventRequestForgery(except: [...])`).
     *
     * Always responds 200 — even for a payload we can't process — so
     * Midtrans doesn't endlessly retry a notification we've already
     * logged and given up on. All possible failure paths are caught
     * and logged instead of bubbling into a 500.
     */
    public function handle(Request $request, HandleMidtransNotificationAction $handleNotification): JsonResponse
    {
        try {
            $handleNotification->execute($request->all());
        } catch (Throwable $e) {
            Log::error('Gagal memproses webhook Midtrans.', [
                'message' => $e->getMessage(),
                'order_id' => $request->input('order_id'),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
