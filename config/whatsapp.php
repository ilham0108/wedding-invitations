<?php

/*
|--------------------------------------------------------------------------
| WhatsApp
|--------------------------------------------------------------------------
|
| Providers and their credentials are configured by the admin in the
| dashboard (/admin/whatsapp). These values only tune the queue.
|
*/

return [

    // Queue that carries WhatsApp jobs. "default" works with a plain `php artisan queue:work`;
    // set WHATSAPP_QUEUE=whatsapp and run a dedicated worker (--queue=whatsapp) to isolate it.
    'queue' => env('WHATSAPP_QUEUE', 'default'),

    // Tutorial videos on the "My WhatsApp number" page, per provider. A YouTube/Vimeo link,
    // or a .mp4/.webm file in public/ (e.g. /videos/fonnte-tutorial.mp4). Empty = hidden.
    'tutorials' => [
        'fonnte' => env('WHATSAPP_FONNTE_TUTORIAL_URL'),
    ],

    // Attempts per recipient and the wait (seconds) before each retry.
    'tries' => 3,
    'backoff' => [30, 120, 600],

];
