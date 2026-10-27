<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsApp\ReceivesStatusWebhooks;
use App\Enums\WhatsAppDriver;
use App\Services\WhatsApp\Providers\MetaWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppDeliveryService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Delivery-status webhooks. Each request is verified with the secret of the provider it is
 * addressed to, and may only update messages that were sent through that provider.
 */
class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, string $driver, WhatsAppManager $whatsapp, WhatsAppDeliveryService $delivery, ?string $provider = null): Response
    {
        $driverEnum = WhatsAppDriver::tryFrom($driver);
        $config = $driverEnum?->supportsStatusWebhook() ? $whatsapp->configForWebhook($driverEnum, $provider) : null;

        abort_if($config === null, 404);

        $implementation = $whatsapp->make($config);
        abort_unless($implementation instanceof ReceivesStatusWebhooks, 404);

        if ($request->isMethod('get')) {
            $challenge = $implementation instanceof MetaWhatsAppProvider ? $implementation->verificationChallenge($request) : null;

            return $challenge !== null ? response($challenge, 200) : response('', 403);
        }

        if (! $implementation->verifyWebhook($request)) {
            Log::warning('WhatsApp webhook rejected: invalid signature', ['provider' => $driver, 'connection' => $provider, 'ip' => $request->ip()]);

            return response('', 403);
        }

        $applied = 0;

        foreach ($implementation->parseStatusWebhook($request) as $update) {
            $applied += (int) $delivery->applyUpdate($update, $config);
        }

        Log::info('WhatsApp webhook processed', ['provider' => $driver, 'connection' => $provider, 'updates' => $applied]);

        return response('', 200);
    }
}
