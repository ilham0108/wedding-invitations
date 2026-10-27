<?php

/*
|--------------------------------------------------------------------------
| Plan features
|--------------------------------------------------------------------------
|
| The catalog of features a plan can grant (rules.md section 47). Values live
| in the database per plan; this list only defines what exists, its type,
| and the value used when a plan does not set it.
|
| "limit"   integer, null = unlimited
| "boolean" true / false
|
*/

return [

    'features' => [
        'max_invitations' => ['type' => 'limit', 'label' => 'Invitations', 'default' => 1],
        'max_guests' => ['type' => 'limit', 'label' => 'Guests per invitation', 'default' => 50],
        'max_whatsapp_messages' => ['type' => 'limit', 'label' => 'Platform WhatsApp messages per month', 'default' => 50],
        'premium_templates' => ['type' => 'boolean', 'label' => 'Premium templates', 'default' => false],
        'premium_illustrations' => ['type' => 'boolean', 'label' => 'Premium illustrations', 'default' => false],
        'remove_branding' => ['type' => 'boolean', 'label' => 'Remove branding', 'default' => false],
        'premium_music' => ['type' => 'boolean', 'label' => 'Premium music', 'default' => false],
        'custom_music' => ['type' => 'boolean', 'label' => 'Upload own music', 'default' => false],
        'theme_customization' => ['type' => 'boolean', 'label' => 'Theme customization', 'default' => false],
        'analytics' => ['type' => 'boolean', 'label' => 'Analytics', 'default' => false],
        'custom_domain' => ['type' => 'boolean', 'label' => 'Custom domain', 'default' => false],
    ],

    // Days before a paid plan ends when the owner is reminded to renew.
    'expiry_reminder_days' => 7,

];
