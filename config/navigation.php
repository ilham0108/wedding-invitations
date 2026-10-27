<?php

/*
|--------------------------------------------------------------------------
| Sidebar Navigation
|--------------------------------------------------------------------------
|
| Items with a null route belong to a later development phase and are
| rendered as disabled ("Soon") instead of linking to a mock page.
| "active" is an optional route-name pattern used for highlighting.
|
*/

return [
    'dashboard' => [
        ['label' => 'Dashboard', 'icon' => 'fa-solid fa-house', 'route' => 'dashboard'],
        ['label' => 'My Invitations', 'icon' => 'fa-regular fa-envelope-open', 'route' => 'dashboard.invitations.index', 'active' => 'dashboard.invitations.*'],
        ['label' => 'Templates', 'icon' => 'fa-solid fa-swatchbook', 'route' => 'dashboard.templates.index'],
        ['label' => 'Guests', 'icon' => 'fa-solid fa-user-group', 'route' => 'dashboard.guests.index'],
        ['label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp', 'route' => 'dashboard.whatsapp.index', 'active' => 'dashboard.whatsapp.*'],
        ['label' => 'RSVP', 'icon' => 'fa-regular fa-circle-check', 'route' => 'dashboard.rsvp.index'],
        ['label' => 'Analytics', 'icon' => 'fa-solid fa-chart-line', 'route' => null],
        ['label' => 'Subscription', 'icon' => 'fa-regular fa-gem', 'route' => 'dashboard.subscription.index', 'active' => 'dashboard.subscription.*'],
        ['label' => 'Settings', 'icon' => 'fa-solid fa-gear', 'route' => 'profile.edit'],
    ],

    'admin' => [
        ['label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge', 'route' => 'admin.dashboard'],
        ['label' => 'Users', 'icon' => 'fa-solid fa-users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
        ['label' => 'Templates', 'icon' => 'fa-solid fa-swatchbook', 'route' => 'admin.templates.index'],
        ['label' => 'Categories', 'icon' => 'fa-solid fa-tags', 'route' => 'admin.template-categories.index'],
        ['label' => 'Illustrations', 'icon' => 'fa-solid fa-palette', 'route' => 'admin.illustrations.index'],
        ['label' => 'Music', 'icon' => 'fa-solid fa-music', 'route' => 'admin.music.index'],
        ['label' => 'Plans', 'icon' => 'fa-regular fa-gem', 'route' => 'admin.plans.index'],
        ['label' => 'Orders', 'icon' => 'fa-solid fa-receipt', 'route' => 'admin.orders.index'],
        ['label' => 'Payments', 'icon' => 'fa-regular fa-credit-card', 'route' => 'admin.payments.index'],
        ['label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp', 'route' => 'admin.whatsapp.index'],
        ['label' => 'Site content', 'icon' => 'fa-regular fa-newspaper', 'route' => 'admin.content.index'],
        ['label' => 'Audit log', 'icon' => 'fa-solid fa-clock-rotate-left', 'route' => 'admin.audit-logs.index'],
        ['label' => 'Settings', 'icon' => 'fa-solid fa-sliders', 'route' => 'admin.settings.index'],
    ],
];
