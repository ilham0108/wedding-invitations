<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gateway callbacks. Verify the gateway secret, find the payment, and queue an authoritative
 * re-check; the request body itself is never used to activate anything.
 * Replies with {"success": true} (Tripay retries otherwise; Xendit accepts any 2xx).
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentManager $gateways): JsonResponse
    {
        abort_unless($gateways->has($gateway), 404);

        $implementation = $gateways->gateway($gateway);

        if (! $implementation->verifyWebhook($request)) {
            Log::warning('Payment webhook rejected: verification failed', ['gateway' => $gateway, 'ip' => $request->ip()]);

            return response()->json(['success' => false], 403);
        }

        $notification = $implementation->parseWebhook($request);
        $payment = $notification !== null
            ? Payment::query()->where('gateway', $gateway)->where('external_id', $notification->externalId)->first()
            : null;

        if ($payment === null) {
            // Acknowledge so the gateway stops retrying; nothing of ours matches.
            Log::info('Payment webhook ignored: unknown payment', ['gateway' => $gateway, 'external_id' => $notification?->externalId]);

            return response()->json(['success' => true]);
        }

        ProcessPaymentWebhook::dispatch($payment->ulid);

        return response()->json(['success' => true]);
    }
}
