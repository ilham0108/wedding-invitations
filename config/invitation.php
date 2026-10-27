<?php

/*
|--------------------------------------------------------------------------
| Invitation Engine
|--------------------------------------------------------------------------
|
| Templates are pure configuration: they pick sections from this registry,
| choose variants, and set a theme and decoration. Adding a template never
| requires code (see docs/templates.md). Every variant is its own Blade file
| at resources/views/invitation/sections/{type}/{variant}.blade.php, so a
| new variant is one file plus its name in that section's "variants" list.
|
| "fields" drive both the editor form and its validation rules. Supported
| types: text, textarea, toggle, select, date, time, url, number, image,
| images, colors, repeater (with nested "fields" and "max" items).
| A field with "variants" is only shown in the editors for those layouts.
| A field with "admin_only" => true is only shown in the template builder: clients
| keep the template's value and cannot change it (SectionSettings).
| An image field with "focus" => true gets a "{key}_focus" field (type "focus",
| "x% y%") where the editor picks the part of a cropped photo that stays visible.
|
*/

$heading = ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'];

return [

    'fields' => [
        'cover' => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
            ['key' => 'date_text', 'label' => 'Date text', 'type' => 'text'],
            ['key' => 'background_image', 'label' => 'Background image', 'type' => 'image', 'illustrations' => 'cover', 'focus' => true],
            ['key' => 'background_motion', 'label' => 'Background motion', 'type' => 'select', 'options' => ['none' => 'Still', 'zoom-out' => 'Slow zoom-out on open', 'drift' => 'Slow continuous zoom']],
            ['key' => 'overlay', 'label' => 'Dark overlay on image', 'type' => 'toggle'],
            ['key' => 'frame_style', 'label' => 'Frame style', 'type' => 'select', 'variants' => ['framed'], 'options' => ['blur' => 'Blur (frosted glass)', 'transparent' => 'Transparent (outline only)', 'solid' => 'Solid']],
            ['key' => 'alignment', 'label' => 'Alignment', 'type' => 'select', 'options' => ['center' => 'Center', 'left' => 'Left']],
            ['key' => 'button_label', 'label' => 'Button label', 'type' => 'text'],
            // Desktop side panel (designs with the "desktop_panel" feature); empty fields reuse the cover's.
            // Admin only (the owner's choice, 2026-10-25): the template sets the panel, clients only fill the cover.
            ['key' => 'desktop_image', 'label' => 'Desktop panel: background image', 'type' => 'image', 'illustrations' => 'cover', 'admin_only' => true, 'feature' => 'desktop_panel', 'focus' => true],
            ['key' => 'desktop_subtitle', 'label' => 'Desktop panel: subtitle', 'type' => 'text', 'admin_only' => true, 'feature' => 'desktop_panel'],
            ['key' => 'desktop_title', 'label' => 'Desktop panel: title', 'type' => 'text', 'admin_only' => true, 'feature' => 'desktop_panel'],
            ['key' => 'desktop_text', 'label' => 'Desktop panel: text', 'type' => 'textarea', 'admin_only' => true, 'feature' => 'desktop_panel'],
            ['key' => 'desktop_text_position', 'label' => 'Desktop panel: text position', 'type' => 'select', 'admin_only' => true, 'feature' => 'desktop_panel', 'options' => ['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom']],
        ],
        'couple' => [
            $heading,
            ['key' => 'opening', 'label' => 'Opening line (e.g. Bismillah in Arabic)', 'type' => 'text'],
            ['key' => 'intro', 'label' => 'Introduction', 'type' => 'textarea'],
            // Portrait mask and an optional frame image laid over every person's photo (invitation.partials.framed-photo).
            ['key' => 'photo_shape', 'label' => 'Photo shape', 'type' => 'select', 'admin_only' => true, 'options' => ['default' => 'Design default', 'rounded' => 'Rounded rectangle', 'circle' => 'Circle', 'oval' => 'Oval', 'arch' => 'Arch']],
            ['key' => 'photo_frame', 'label' => 'Photo frame (transparent image with an opening in the middle)', 'type' => 'image', 'admin_only' => true, 'illustrations' => 'frame'],
            ['key' => 'photo_frame_inset', 'label' => 'Photo frame: photo margin inside the frame (%)', 'type' => 'number', 'admin_only' => true, 'min' => 0, 'max' => 40],
            ['key' => 'people', 'label' => 'People', 'type' => 'repeater', 'max' => 6, 'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role (e.g. The Groom, 7 years old, Speaker)', 'type' => 'text'],
                ['key' => 'parents', 'label' => 'Parents / organization', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'photo', 'label' => 'Photo', 'type' => 'image', 'illustrations' => 'person', 'focus' => true],
                ['key' => 'instagram', 'label' => 'Instagram URL', 'type' => 'url'],
                ['key' => 'tiktok', 'label' => 'TikTok URL', 'type' => 'url'],
                ['key' => 'facebook', 'label' => 'Facebook URL', 'type' => 'url'],
                ['key' => 'x', 'label' => 'X (Twitter) URL', 'type' => 'url'],
                ['key' => 'youtube', 'label' => 'YouTube URL', 'type' => 'url'],
            ]],
        ],
        'quote' => [
            ['key' => 'arabic_text', 'label' => 'Arabic text (optional)', 'type' => 'textarea'],
            ['key' => 'text', 'label' => 'Quote', 'type' => 'textarea'],
            ['key' => 'author', 'label' => 'Author / source', 'type' => 'text'],
        ],
        'countdown' => [
            $heading,
            ['key' => 'expired_message', 'label' => 'Message after the event starts', 'type' => 'textarea'],
        ],
        'event' => [
            $heading,
            ['key' => 'events', 'label' => 'Events', 'type' => 'repeater', 'max' => 10, 'fields' => [
                ['key' => 'title', 'label' => 'Event title', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'start_time', 'label' => 'Start time', 'type' => 'time'],
                ['key' => 'end_time', 'label' => 'End time', 'type' => 'time'],
                ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'maps_url', 'label' => 'Google Maps URL', 'type' => 'url'],
                ['key' => 'notice', 'label' => 'Notice (e.g. family and invited guests only)', 'type' => 'textarea'],
                ['key' => 'add_to_calendar', 'label' => 'Show "Save to calendar" button', 'type' => 'toggle'],
            ]],
        ],
        'story' => [
            $heading,
            // Proportions of the entry photos (App\Support\PhotoStyle::RATIOS); "original" shows them uncropped.
            ['key' => 'photo_ratio', 'label' => 'Photo shape', 'type' => 'select', 'options' => ['default' => 'Design default', 'original' => 'Original (not cropped)', 'landscape' => 'Landscape 4:3', 'square' => 'Square 1:1', 'portrait' => 'Portrait 4:5']],
            ['key' => 'entries', 'label' => 'Story entries', 'type' => 'repeater', 'max' => 20, 'fields' => [
                ['key' => 'date', 'label' => 'Date or year', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                // Marker on the timeline (config "story_icons").
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => [
                    'heart' => 'Heart', 'chat' => 'Conversation', 'ring' => 'Ring', 'home' => 'Home', 'star' => 'Star',
                    'plane' => 'Travel', 'calendar' => 'Calendar', 'camera' => 'Photo', 'graduation' => 'Graduation', 'gift' => 'Gift',
                ]],
                ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'illustrations' => 'story', 'focus' => true],
            ]],
            ['key' => 'closing_quote', 'label' => 'Closing quote', 'type' => 'textarea'],
        ],
        'timeline' => [
            $heading,
            ['key' => 'items', 'label' => 'Items', 'type' => 'repeater', 'max' => 30, 'fields' => [
                ['key' => 'time', 'label' => 'Time', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ]],
        ],
        'gallery' => [
            $heading,
            ['key' => 'images', 'label' => 'Photos', 'type' => 'images', 'max' => 30, 'illustrations' => 'gallery'],
        ],
        'video' => [
            $heading,
            ['key' => 'url', 'label' => 'YouTube or Vimeo URL', 'type' => 'url'],
        ],
        'rsvp' => [
            $heading,
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'deadline', 'label' => 'RSVP deadline', 'type' => 'date'],
            ['key' => 'closed_message', 'label' => 'Message after the deadline', 'type' => 'textarea'],
            ['key' => 'max_guests', 'label' => 'Max people per response (public link)', 'type' => 'number', 'min' => 1, 'max' => 20],
            ['key' => 'ask_meal_preference', 'label' => 'Ask for meal preference', 'type' => 'toggle'],
            ['key' => 'meal_options', 'label' => 'Meal options (comma-separated)', 'type' => 'text'],
        ],
        'wishes' => [
            $heading,
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'show_messages', 'label' => 'Show approved wishes on the invitation', 'type' => 'toggle'],
        ],
        'gift' => [
            $heading,
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'accounts', 'label' => 'Accounts', 'type' => 'repeater', 'max' => 6, 'fields' => [
                ['key' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['bank' => 'Bank', 'ewallet' => 'E-wallet']],
                ['key' => 'provider', 'label' => 'Bank / e-wallet', 'type' => 'text'],
                ['key' => 'account_number', 'label' => 'Account number', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account name', 'type' => 'text'],
            ]],
            ['key' => 'address', 'label' => 'Gift address', 'type' => 'textarea'],
            ['key' => 'wishlist_heading', 'label' => 'Gift registry heading', 'type' => 'text'],
            ['key' => 'wishlist', 'label' => 'Gift registry', 'type' => 'repeater', 'max' => 30, 'fields' => [
                ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
                ['key' => 'url', 'label' => 'Shop link', 'type' => 'url'],
            ]],
        ],
        'maps' => [
            $heading,
            ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'],
            ['key' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ['key' => 'maps_url', 'label' => 'Google Maps URL', 'type' => 'url'],
            ['key' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'min' => -90, 'max' => 90],
            ['key' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'min' => -180, 'max' => 180],
        ],
        'dress_code' => [
            $heading,
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'colors', 'label' => 'Palette', 'type' => 'colors', 'max' => 8],
            ['key' => 'men', 'label' => 'Men', 'type' => 'text'],
            ['key' => 'women', 'label' => 'Women', 'type' => 'text'],
            ['key' => 'color_note', 'label' => 'Colors (text)', 'type' => 'text'],
            ['key' => 'avoid', 'label' => 'Please avoid', 'type' => 'text'],
            ['key' => 'notes', 'label' => 'Other notes (one per line, e.g. parking)', 'type' => 'textarea'],
        ],
        'contact' => [
            $heading,
            ['key' => 'contacts', 'label' => 'Contacts', 'type' => 'repeater', 'max' => 6, 'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'WhatsApp number', 'type' => 'text'],
            ]],
        ],
        'closing' => [
            $heading,
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea'],
            ['key' => 'signature', 'label' => 'Signature', 'type' => 'text'],
            ['key' => 'inviters', 'label' => 'Also inviting (one per line)', 'type' => 'textarea'],
        ],
        'health_protocol' => [
            $heading,
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['key' => 'items', 'label' => 'Guidelines', 'type' => 'repeater', 'max' => 8, 'fields' => [
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => [
                    'healthy' => 'Healthy', 'clean' => 'Hand washing', 'cough' => 'Cough etiquette', 'respect' => 'Respect',
                    'mask' => 'Mask', 'distance' => 'Distance', 'temperature' => 'Temperature check', 'sanitizer' => 'Hand sanitizer',
                ]],
                ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
            ]],
        ],
    ],

    /*
    | Fields every section has, appended after its own fields ("except" lists the
    | section types without them). Their default value is null.
    */
    'common_fields' => [
        // Shown behind the page while this section is on screen; see invitation.partials.scroll-backdrop.
        ['key' => 'background_photo', 'label' => 'Background photo (changes while scrolling)', 'type' => 'image', 'illustrations' => 'cover', 'focus' => true, 'except' => ['cover']],
        // How much of the theme color covers that photo; see invitation.partials.backdrop-attributes.
        ['key' => 'background_overlay', 'label' => 'Background photo: overlay', 'type' => 'select', 'admin_only' => true, 'except' => ['cover'], 'options' => ['strong' => 'Strong (78%)', 'medium' => 'Medium (55%)', 'light' => 'Light (30%)', 'none' => 'None (photo only)']],
    ],

    'sections' => [
        'cover' => [
            'label' => 'Cover',
            'icon' => 'fa-regular fa-image',
            'variants' => ['centered', 'framed'],
            'defaults' => ['title' => '', 'subtitle' => '', 'date_text' => '', 'background_image' => null, 'background_motion' => 'none', 'overlay' => true, 'frame_style' => 'blur', 'alignment' => 'center', 'button_label' => 'Open Invitation', 'desktop_image' => null, 'desktop_subtitle' => '', 'desktop_title' => '', 'desktop_text' => '', 'desktop_text_position' => 'center'],
        ],
        'couple' => [
            'label' => 'Couple / Host',
            'icon' => 'fa-solid fa-user-group',
            'variants' => ['side-by-side', 'stacked'],
            'defaults' => ['heading' => 'The Hosts', 'opening' => '', 'intro' => '', 'photo_shape' => 'default', 'photo_frame' => null, 'photo_frame_inset' => 12, 'people' => []],
        ],
        'quote' => [
            'label' => 'Quote',
            'icon' => 'fa-solid fa-quote-left',
            'variants' => ['default'],
            'defaults' => ['arabic_text' => '', 'text' => '', 'author' => ''],
        ],
        'countdown' => [
            'label' => 'Countdown',
            'icon' => 'fa-regular fa-clock',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Counting Down', 'expired_message' => 'The celebration has begun. Thank you for being part of it.'],
        ],
        'event' => [
            'label' => 'Event',
            'icon' => 'fa-regular fa-calendar',
            'variants' => ['cards', 'list'],
            'defaults' => ['heading' => 'Save the Date', 'events' => []],
        ],
        'story' => [
            'label' => 'Story',
            'icon' => 'fa-solid fa-book-open',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Our Story', 'photo_ratio' => 'default', 'entries' => [], 'closing_quote' => ''],
        ],
        'timeline' => [
            'label' => 'Timeline',
            'icon' => 'fa-solid fa-list-ol',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Rundown', 'items' => []],
        ],
        'gallery' => [
            'label' => 'Gallery',
            'icon' => 'fa-regular fa-images',
            'variants' => ['grid', 'masonry', 'slideshow'],
            'defaults' => ['heading' => 'Gallery', 'images' => []],
        ],
        'video' => [
            'label' => 'Video',
            'icon' => 'fa-solid fa-play',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Watch', 'url' => ''],
        ],
        'rsvp' => [
            'label' => 'RSVP',
            'icon' => 'fa-regular fa-circle-check',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Will You Attend?', 'description' => 'Kindly let us know if you can join us.', 'deadline' => null, 'closed_message' => 'RSVP is now closed. Thank you!', 'max_guests' => 5, 'ask_meal_preference' => false, 'meal_options' => ''],
        ],
        'wishes' => [
            'label' => 'Wishes',
            'icon' => 'fa-regular fa-comment-dots',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Wishes', 'description' => 'Leave a kind note for the hosts.', 'show_messages' => true],
        ],
        'gift' => [
            'label' => 'Digital Gift',
            'icon' => 'fa-solid fa-gift',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Wedding Gift', 'description' => 'Your presence is the greatest gift.', 'accounts' => [], 'address' => '', 'wishlist_heading' => 'Gift Registry', 'wishlist' => []],
        ],
        'maps' => [
            'label' => 'Maps',
            'icon' => 'fa-solid fa-location-dot',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Location', 'venue' => '', 'address' => '', 'maps_url' => '', 'latitude' => null, 'longitude' => null],
        ],
        'dress_code' => [
            'label' => 'Dress Code',
            'icon' => 'fa-solid fa-shirt',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Dress Code', 'description' => '', 'colors' => [], 'men' => '', 'women' => '', 'color_note' => '', 'avoid' => '', 'notes' => ''],
        ],
        'contact' => [
            'label' => 'Contact',
            'icon' => 'fa-solid fa-phone',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Contact', 'contacts' => []],
        ],
        'closing' => [
            'label' => 'Closing',
            'icon' => 'fa-regular fa-heart',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Thank You', 'message' => '', 'signature' => '', 'inviters' => ''],
        ],
        'health_protocol' => [
            'label' => 'Health Protocol',
            'icon' => 'fa-solid fa-shield-heart',
            'variants' => ['default'],
            'defaults' => ['heading' => 'Health Protocol', 'description' => 'This event follows health protocols.', 'items' => [
                ['icon' => 'healthy', 'text' => 'Come in good health'],
                ['icon' => 'clean', 'text' => 'Keep the venue clean'],
                ['icon' => 'cough', 'text' => 'Cover coughs and sneezes'],
                ['icon' => 'respect', 'text' => 'Respect and care for each other'],
            ]],
        ],
    ],

    /*
    | Icons for health protocol items (Font Awesome classes, never user input).
    */
    // Story entry markers (story field "icon").
    'story_icons' => [
        'heart' => 'fa-regular fa-heart',
        'chat' => 'fa-regular fa-comments',
        'ring' => 'fa-solid fa-ring',
        'home' => 'fa-solid fa-house',
        'star' => 'fa-regular fa-star',
        'plane' => 'fa-solid fa-plane',
        'calendar' => 'fa-regular fa-calendar-check',
        'camera' => 'fa-solid fa-camera-retro',
        'graduation' => 'fa-solid fa-graduation-cap',
        'gift' => 'fa-solid fa-gift',
    ],

    'health_protocol_icons' => [
        'healthy' => 'fa-solid fa-heart-pulse',
        'clean' => 'fa-solid fa-hands-bubbles',
        'cough' => 'fa-solid fa-head-side-cough',
        'respect' => 'fa-solid fa-handshake-angle',
        'mask' => 'fa-solid fa-head-side-mask',
        'distance' => 'fa-solid fa-people-arrows',
        'temperature' => 'fa-solid fa-temperature-half',
        'sanitizer' => 'fa-solid fa-pump-soap',
    ],

    /*
    | Safe web fonts (served from fonts.bunny.net). Clients may only pick from
    | this list — arbitrary external font URLs are never accepted.
    */
    'fonts' => [
        'Playfair Display' => ['category' => 'serif', 'bunny' => 'playfair-display:400,500,600', 'fallback' => 'Georgia, serif'],
        'Cormorant Garamond' => ['category' => 'serif', 'bunny' => 'cormorant-garamond:400,500,600', 'fallback' => 'Georgia, serif'],
        'Lora' => ['category' => 'serif', 'bunny' => 'lora:400,500,600', 'fallback' => 'Georgia, serif'],
        'Libre Baskerville' => ['category' => 'serif', 'bunny' => 'libre-baskerville:400,700', 'fallback' => 'Georgia, serif'],
        'DM Sans' => ['category' => 'sans-serif', 'bunny' => 'dm-sans:400,500,600', 'fallback' => 'system-ui, sans-serif'],
        'Inter' => ['category' => 'sans-serif', 'bunny' => 'inter:400,500,600', 'fallback' => 'system-ui, sans-serif'],
        'Poppins' => ['category' => 'sans-serif', 'bunny' => 'poppins:400,500,600', 'fallback' => 'system-ui, sans-serif'],
        'Montserrat' => ['category' => 'sans-serif', 'bunny' => 'montserrat:400,500,600', 'fallback' => 'system-ui, sans-serif'],
        'Jost' => ['category' => 'sans-serif', 'bunny' => 'jost:300,400,500', 'fallback' => 'system-ui, sans-serif'],
        'Cinzel' => ['category' => 'display', 'bunny' => 'cinzel:400,500,600', 'fallback' => 'Georgia, serif'],
        'Cinzel Decorative' => ['category' => 'display', 'bunny' => 'cinzel-decorative:400,700', 'fallback' => 'Georgia, serif'],
        'Amiri' => ['category' => 'serif', 'bunny' => 'amiri:400,700', 'fallback' => 'serif'],
        'Marcellus' => ['category' => 'display', 'bunny' => 'marcellus:400', 'fallback' => 'Georgia, serif'],
        'Great Vibes' => ['category' => 'handwritten', 'bunny' => 'great-vibes:400', 'fallback' => 'cursive'],
        'Dancing Script' => ['category' => 'handwritten', 'bunny' => 'dancing-script:400,600', 'fallback' => 'cursive'],
        'Parisienne' => ['category' => 'handwritten', 'bunny' => 'parisienne:400', 'fallback' => 'cursive'],
    ],

    'theme_options' => [
        'button_style' => ['solid', 'outline', 'pill'],
        'border_radius' => ['none', 'small', 'medium', 'large'],
        'section_spacing' => ['compact', 'normal', 'relaxed'],
    ],

    'default_theme' => [
        'primary_color' => '#365149',
        'secondary_color' => '#c8634b',
        'background_color' => '#f6f4ef',
        'surface_color' => '#ffffff',
        'text_color' => '#1f2d2a',
        'font_heading' => 'Playfair Display',
        'font_body' => 'DM Sans',
        'button_style' => 'solid',
        'border_radius' => 'medium',
        'section_spacing' => 'normal',
    ],

    'theme_presets' => [
        'elegant' => ['primary_color' => '#365149', 'secondary_color' => '#b08d57', 'background_color' => '#f6f4ef', 'surface_color' => '#ffffff', 'text_color' => '#1f2d2a', 'font_heading' => 'Cormorant Garamond', 'font_body' => 'DM Sans', 'button_style' => 'solid', 'border_radius' => 'small'],
        'classic' => ['primary_color' => '#2b3a55', 'secondary_color' => '#8c6a43', 'background_color' => '#fbf8f2', 'surface_color' => '#ffffff', 'text_color' => '#232323', 'font_heading' => 'Libre Baskerville', 'font_body' => 'Lora', 'button_style' => 'outline', 'border_radius' => 'none'],
        'modern' => ['primary_color' => '#111827', 'secondary_color' => '#2563eb', 'background_color' => '#ffffff', 'surface_color' => '#f3f4f6', 'text_color' => '#111827', 'font_heading' => 'Montserrat', 'font_body' => 'Inter', 'button_style' => 'pill', 'border_radius' => 'large'],
        'minimal' => ['primary_color' => '#262626', 'secondary_color' => '#737373', 'background_color' => '#ffffff', 'surface_color' => '#fafafa', 'text_color' => '#262626', 'font_heading' => 'DM Sans', 'font_body' => 'DM Sans', 'button_style' => 'outline', 'border_radius' => 'small', 'section_spacing' => 'relaxed'],
        'luxury' => ['primary_color' => '#c9a227', 'secondary_color' => '#e8d9a8', 'background_color' => '#141414', 'surface_color' => '#1f1f1f', 'text_color' => '#f5f0e1', 'font_heading' => 'Cinzel', 'font_body' => 'Montserrat', 'button_style' => 'outline', 'border_radius' => 'none'],
        'romantic' => ['primary_color' => '#b5576b', 'secondary_color' => '#e9b8c1', 'background_color' => '#fdf5f6', 'surface_color' => '#ffffff', 'text_color' => '#4a2c33', 'font_heading' => 'Great Vibes', 'font_body' => 'Lora', 'button_style' => 'pill', 'border_radius' => 'large'],
    ],

    /*
    | Instagram Story image: one-tap suggestions for its headline. They are content
    | printed on the image as written (not UI text), so they are not translated.
    */
    'story_headlines' => ['Save the Date', 'The Wedding of', 'Kami Menikah', 'Undangan Pernikahan'],

    /*
    | Background music (rules.md section 30): MP3 only. Clients upload up to
    | "max_upload_kb" (plan feature "custom_music"); admins fill the library.
    */
    'music' => [
        'max_upload_kb' => 10240,
        // Livewire's temporary uploads stop at 12 MB (its default rule), so stay at or below that.
        'max_library_upload_kb' => 12288,
        // Latest second a song may start at.
        'max_start_at' => 600,
    ],

    /*
    | Design packs: the look of an invitation page. A design lives in
    | resources/views/designs/{key}/ with an optional page.blade.php and optional
    | sections/{type}.blade.php overrides; sections it does not override use the
    | classic views. "fonts" are always loaded on top of the theme fonts and
    | "assets" are extra Vite entries (also listed in vite.config.js). "features" unlock
| design-specific fields (a field with "feature" => 'x' only shows for designs with x).
    | See docs/templates.md → "Paket desain".
    */
    'designs' => [
        'classic' => [
            'label' => 'Classic',
            'description' => 'Centered sections on the theme colors. Works with every section.',
            'fonts' => [],
            'assets' => [],
            'features' => [],
        ],
        'mocha-glass' => [
            'label' => 'Mocha Glass',
            'description' => 'Full-screen photo cover that slides away, frosted glass cards, sparkles, and a bottom menu. Desktop shows a photo panel beside the invitation.',
            'fonts' => ['Cormorant Garamond', 'Amiri'],
            'assets' => ['resources/css/designs/mocha-glass.css', 'resources/js/designs/mocha-glass.js'],
            // A photo panel beside the invitation on wide screens, with its own content and ornaments.
            'features' => ['desktop_panel'],
        ],
        'botanical-cream' => [
            'label' => 'Botanical Cream',
            'description' => 'Soft cream pages with botanical line art: a cover photo fading into the page, script names with a monogram, icon cards, a story timeline, and a labelled bottom menu with a full menu. Desktop shows a photo panel beside the invitation.',
            'fonts' => ['Cormorant Garamond', 'Great Vibes', 'Amiri'],
            'assets' => ['resources/css/designs/botanical-cream.css', 'resources/js/designs/botanical-cream.js'],
            'features' => ['desktop_panel'],
        ],
    ],

    /*
    | Decoration layer: ornamental styling on top of the theme. Like the theme,
    | it is copied onto an invitation when the invitation is created.
    */
    'decoration_options' => [
        'divider' => ['line', 'dots', 'flourish', 'none'],
        'texture' => ['none', 'paper', 'linen', 'grain'],
        // Entrance animation of sections and design blocks (.inv-animate), see resources/css/invitation.css.
        'animation' => ['none', 'fade', 'fade-up', 'zoom', 'slide'],
        'animation_speed' => ['slow', 'normal', 'fast'],
        // Wide screens, for designs without their own page frame: a phone-width column on a blurred
        // cover photo, the column beside a cover panel (unlocks the "desktop_panel" feature), or full width.
        'desktop_layout' => ['column', 'split', 'full'],
    ],

    'default_decoration' => [
        'divider' => 'line',
        'texture' => 'none',
        'animation' => 'none',
        'animation_speed' => 'normal',
        'desktop_layout' => 'column',
    ],

    /*
    | Ornaments: transparent images (illustration category "ornament") placed freely on a
    | section. Every section of a template has its own list (schema.sections.*.ornaments,
    | copied to invitation_sections.theme). Positions are percentages of the section box;
    | "anchor" is the point of the image that sits on (x, y) and the pivot of its motion.
    | Each ornament enters with its own effect, duration and delay, so a template can
    | choreograph its sections. Cleaned by App\Services\Invitation\Ornaments.
    */
    'ornament_options' => [
        'max_per_section' => 60,
        'anchors' => ['top-left', 'top', 'top-right', 'left', 'center', 'right', 'bottom-left', 'bottom', 'bottom-right'],
        'layers' => ['front', 'back'],
        'enters' => ['none', 'fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'zoom-in-up', 'zoom-in-left', 'zoom-in-right', 'zoom-out'],
        'motions' => ['none', 'sway', 'float'],
        'defaults' => ['x' => 0, 'y' => 100, 'width' => 35, 'anchor' => 'bottom-left', 'flip' => false, 'layer' => 'front', 'enter' => 'fade-up', 'duration' => 1.6, 'delay' => 0, 'motion' => 'sway'],
    ],

];
