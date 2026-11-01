<?php

/*
|--------------------------------------------------------------------------
| Marketing site content (Admin CMS, rules.md section 52)
|--------------------------------------------------------------------------
|
| Each section is edited at /admin/content and stored in site_contents
| (one row per section). "defaults" is what the site shows until the admin
| saves the section. Content is written in one language (Indonesian), so it
| is not translated. Templates and pricing amounts stay database-driven.
|
| Headlines accept *asterisks* around a word to highlight it.
| "image" keys are optional uploaded photos; without one, the page draws an
| illustrated fallback.
|
*/

return [

    // Font Awesome icons offered for hero highlights, steps and features (validated against this list).
    'icons' => [
        'fa-regular fa-pen-to-square', 'fa-solid fa-mobile-screen', 'fa-regular fa-heart', 'fa-solid fa-leaf',
        'fa-solid fa-table-cells-large', 'fa-solid fa-pencil', 'fa-regular fa-eye', 'fa-solid fa-share-nodes',
        'fa-solid fa-camera', 'fa-regular fa-map', 'fa-regular fa-calendar-days', 'fa-regular fa-comment',
        'fa-solid fa-user-group', 'fa-solid fa-music', 'fa-brands fa-whatsapp', 'fa-regular fa-envelope-open',
        'fa-brands fa-instagram', 'fa-solid fa-gift', 'fa-regular fa-images', 'fa-regular fa-clock',
        'fa-regular fa-circle-check', 'fa-solid fa-location-dot', 'fa-solid fa-swatchbook', 'fa-solid fa-palette',
        'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-user-plus', 'fa-solid fa-qrcode', 'fa-regular fa-gem',
        'fa-solid fa-shield-halved', 'fa-solid fa-bolt', 'fa-regular fa-comment-dots', 'fa-solid fa-paper-plane',
        'fa-regular fa-star', 'fa-solid fa-crown', 'fa-solid fa-headset', 'fa-solid fa-link',
    ],

    'sections' => [
        'hero' => [
            'label' => 'Hero',
            'defaults' => [
                'badge' => 'Modern · Elegan · Berkesan',
                'title' => 'Undangan Digital untuk Momen Spesial Anda',
                'subtitle' => 'Bagikan kebahagiaan dengan undangan digital yang elegan, praktis, dan ramah lingkungan.',
                'secondary_label' => 'Lihat Template',
                'primary_label' => 'Buat Undangan Sekarang',
                // Photo filling the right half (a styled table, flowers, a phone…).
                'image' => null,
                'items' => [
                    ['icon' => 'fa-regular fa-pen-to-square', 'title' => 'Desain Elegan dan Modern'],
                    ['icon' => 'fa-solid fa-mobile-screen', 'title' => 'Responsif di Semua Perangkat'],
                    ['icon' => 'fa-regular fa-heart', 'title' => 'Mudah Dibagikan'],
                    ['icon' => 'fa-solid fa-leaf', 'title' => 'Ramah Lingkungan'],
                ],
            ],
        ],
        // Live demo: a real published template in a phone frame; each item jumps the frame to one
        // section type (config invitation.sections), so guests' view is shown, not described.
        'demo' => [
            'label' => 'Live demo',
            'defaults' => [
                'eyebrow' => 'Coba sendiri',
                'heading' => 'Begini undangan Anda *dibuka* tamu',
                'subtitle' => 'Ini undangan sungguhan dari salah satu template kami. Pilih bagian di bawah, atau gulir langsung di layar ponsel.',
                // Slug of the template shown first; empty = the first published template.
                'template' => '',
                'items' => [
                    ['icon' => 'fa-regular fa-envelope-open', 'section' => 'cover', 'title' => 'Sampul dengan nama tamu', 'description' => 'Setiap tamu membuka tautan yang menyapa namanya sendiri.'],
                    ['icon' => 'fa-regular fa-calendar-days', 'section' => 'event', 'title' => 'Waktu dan tempat acara', 'description' => 'Akad, resepsi, atau acara lain lengkap dengan jamnya.'],
                    ['icon' => 'fa-regular fa-map', 'section' => 'maps', 'title' => 'Peta lokasi', 'description' => 'Tamu langsung membuka petunjuk arah dari undangan.'],
                    ['icon' => 'fa-regular fa-circle-check', 'section' => 'rsvp', 'title' => 'Konfirmasi kehadiran', 'description' => 'Jawaban tamu masuk ke dasbor Anda beserta jumlah orangnya.'],
                    ['icon' => 'fa-regular fa-comment', 'section' => 'wishes', 'title' => 'Ucapan dan doa', 'description' => 'Tampil langsung, atau setelah Anda setujui dari dasbor.'],
                    ['icon' => 'fa-solid fa-gift', 'section' => 'gift', 'title' => 'Amplop digital', 'description' => 'Nomor rekening atau dompet digital yang bisa disalin tamu.'],
                ],
            ],
        ],
        'templates' => [
            'label' => 'Templates',
            'defaults' => [
                'eyebrow' => 'Koleksi Desain',
                'heading' => 'Template Pilihan',
                'subtitle' => 'Berbagai pilihan desain undangan digital yang dapat disesuaikan dengan tema acara Anda.',
                'link_label' => 'Lihat Semua Template',
            ],
        ],
        'steps' => [
            'label' => 'How it works',
            'defaults' => [
                'eyebrow' => 'Mudah dan Praktis',
                'heading' => 'Cara Kerja',
                'subtitle' => 'Buat undangan digital impian Anda hanya dalam beberapa langkah.',
                'items' => [
                    ['icon' => 'fa-solid fa-table-cells-large', 'title' => 'Pilih Template', 'description' => 'Pilih desain yang sesuai dengan tema acara.'],
                    ['icon' => 'fa-solid fa-pencil', 'title' => 'Sesuaikan Konten', 'description' => 'Isi detail acara, foto, dan informasi penting.'],
                    ['icon' => 'fa-regular fa-eye', 'title' => 'Preview', 'description' => 'Lihat hasil undangan sebelum dipublikasikan.'],
                    ['icon' => 'fa-solid fa-share-nodes', 'title' => 'Bagikan', 'description' => 'Dapatkan link undangan dan bagikan ke tamu Anda.'],
                ],
            ],
        ],
        'features' => [
            'label' => 'Features',
            'defaults' => [
                'eyebrow' => 'Lebih dari Sekadar Undangan',
                'heading' => 'Fitur Unggulan',
                'subtitle' => 'Dilengkapi berbagai fitur modern untuk pengalaman yang lebih berkesan.',
                'items' => [
                    ['icon' => 'fa-solid fa-camera', 'title' => 'Galeri Foto & Video', 'description' => 'Bagikan momen spesial Anda dengan mudah.'],
                    ['icon' => 'fa-regular fa-map', 'title' => 'Lokasi & Navigasi', 'description' => 'Integrasi Google Maps untuk memudahkan tamu.'],
                    ['icon' => 'fa-regular fa-calendar-days', 'title' => 'Hitung Mundur Acara', 'description' => 'Tampilkan countdown menuju hari spesial.'],
                    ['icon' => 'fa-regular fa-comment', 'title' => 'Kolom Ucapan', 'description' => 'Tamu dapat memberikan ucapan dan doa.'],
                    ['icon' => 'fa-solid fa-user-group', 'title' => 'Konfirmasi Kehadiran', 'description' => 'Kelola RSVP dengan praktis.'],
                    ['icon' => 'fa-solid fa-music', 'title' => 'Musik Latar', 'description' => 'Tambahkan musik untuk suasana yang lebih romantis.'],
                    ['icon' => 'fa-brands fa-whatsapp', 'title' => 'Kirim via WhatsApp', 'description' => 'Kirim undangan personal ke banyak tamu sekaligus.'],
                    ['icon' => 'fa-regular fa-envelope-open', 'title' => 'Undangan Personal', 'description' => 'Setiap tamu mendapat tautan dengan namanya sendiri.'],
                    ['icon' => 'fa-brands fa-instagram', 'title' => 'Instagram Story', 'description' => 'Unduh gambar story siap dibagikan.'],
                ],
            ],
        ],
        'pricing' => [
            'label' => 'Pricing',
            'defaults' => [
                'eyebrow' => 'Pilihan Paket',
                'heading' => 'Harga',
                'subtitle' => 'Mulai gratis. Upgrade saat acara Anda butuh lebih banyak tamu, pesan, atau desain premium.',
            ],
        ],
        'faq' => [
            'label' => 'FAQ',
            'defaults' => [
                'eyebrow' => 'Tanya Jawab',
                'heading' => 'Pertanyaan yang Sering Diajukan',
                'items' => [],
            ],
        ],
        'cta' => [
            'label' => 'Call to action',
            'defaults' => [
                'eyebrow' => 'Wujudkan Undangan Impian Anda',
                'heading' => 'Mulai Buat Undangan Digital Anda Sekarang',
                'text' => 'Pilih template, sesuaikan dengan mudah, dan bagikan ke orang-orang terkasih dalam hitungan menit.',
                'button_label' => 'Buat Undangan Sekarang',
                // Wide background photo.
                'image' => null,
            ],
        ],
        'contact' => [
            'label' => 'Footer & contact',
            'defaults' => [
                'tagline' => 'Solusi undangan digital yang elegan, praktis, dan berkesan untuk setiap momen spesial Anda.',
                'email' => '',
                'whatsapp' => '',
                'address' => '',
                'instagram' => '',
                'facebook' => '',
                'tiktok' => '',
                'youtube' => '',
            ],
        ],
    ],

    // Lists are capped so the page stays readable.
    'max_items' => ['hero' => 6, 'demo' => 8, 'steps' => 6, 'features' => 24, 'faq' => 30],

];
