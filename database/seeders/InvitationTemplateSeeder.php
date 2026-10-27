<?php

namespace Database\Seeders;

use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Services\Template\TemplateSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Five demo templates. Each is pure configuration: sections, variants, theme, decoration, defaults,
 * and sample content, validated by TemplateSchema. Copy is in Indonesian, the app's default locale.
 */
class InvitationTemplateSeeder extends Seeder
{
    public function run(TemplateSchema $schema): void
    {
        $categoryIds = TemplateCategory::query()->pluck('id', 'slug');

        foreach ($this->templates() as $sortOrder => $template) {
            InvitationTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                [
                    ...Arr::only($template, ['name', 'description', 'is_premium']),
                    ...$schema->validate($template),
                    'template_category_id' => $categoryIds[$template['category']],
                    'status' => TemplateStatus::Published,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $weddingSample = $this->weddingSample();

        return [
            [
                'slug' => 'elegant-wedding',
                'name' => 'Elegant Wedding',
                'category' => 'wedding',
                'description' => 'Tipografi serif yang klasik, sampul berbingkai, dan palet hijau sage yang tenang.',
                'is_premium' => false,
                'theme' => ['preset' => 'elegant'],
                'decoration' => ['divider' => 'flourish', 'texture' => 'paper', 'animation' => 'fade-up'],
                'schema' => ['sections' => [
                    ['type' => 'cover', 'variant' => 'framed'],
                    ['type' => 'quote', 'background' => 'muted'],
                    ['type' => 'couple', 'variant' => 'side-by-side'],
                    ['type' => 'countdown', 'background' => 'primary'],
                    ['type' => 'event', 'variant' => 'cards', 'background' => 'muted'],
                    'story',
                    ['type' => 'gallery', 'variant' => 'grid', 'background' => 'muted'],
                    'maps',
                    ['type' => 'rsvp', 'background' => 'muted'],
                    'gift',
                    ['type' => 'wishes', 'background' => 'muted'],
                    'closing',
                ]],
                'default_configuration' => [
                    'couple' => ['heading' => 'Kedua Mempelai'],
                    'gift' => ['heading' => 'Amplop Digital'],
                ],
                'sample_content' => $weddingSample,
            ],
            [
                'slug' => 'botanical-romance',
                'name' => 'Botanical Romance',
                'category' => 'wedding',
                'description' => 'Nuansa merah muda lembut, judul bergaya tulisan tangan, dan galeri masonry.',
                'is_premium' => true,
                'theme' => ['preset' => 'romantic', 'primary_color' => '#8a5a63'],
                'decoration' => ['divider' => 'flourish', 'texture' => 'linen', 'animation' => 'fade'],
                'schema' => ['sections' => [
                    ['type' => 'cover', 'variant' => 'centered'],
                    ['type' => 'couple', 'variant' => 'stacked'],
                    ['type' => 'event', 'variant' => 'list', 'background' => 'muted'],
                    'countdown',
                    ['type' => 'gallery', 'variant' => 'masonry', 'background' => 'muted'],
                    'dress_code',
                    ['type' => 'rsvp', 'background' => 'primary'],
                    'gift',
                    ['type' => 'contact', 'background' => 'muted'],
                    'closing',
                ]],
                'default_configuration' => [
                    'couple' => ['heading' => 'Dua Hati'],
                    'gift' => ['heading' => 'Amplop Digital'],
                ],
                'sample_content' => [
                    ...$weddingSample,
                    'dress_code' => ['description' => 'Busana formal bernuansa pastel.', 'colors' => ['#e9b8c1', '#f6e7d8', '#c9d6c3']],
                    'contact' => ['contacts' => [['name' => 'Sarah (adik mempelai wanita)', 'role' => 'Koordinator tamu', 'phone' => '081234567890']]],
                ],
            ],
            [
                'slug' => 'golden-engagement',
                'name' => 'Golden Engagement',
                'category' => 'engagement',
                'description' => 'Tampilan gelap dan mewah dengan aksen emas untuk malam lamaran.',
                'is_premium' => true,
                'theme' => ['preset' => 'luxury'],
                'decoration' => ['divider' => 'line', 'texture' => 'grain', 'animation' => 'zoom'],
                'schema' => ['sections' => [
                    ['type' => 'cover', 'variant' => 'centered'],
                    'couple',
                    ['type' => 'event', 'background' => 'muted'],
                    'countdown',
                    ['type' => 'gallery', 'background' => 'muted'],
                    'rsvp',
                    'closing',
                ]],
                'default_configuration' => [
                    'couple' => ['heading' => 'Pasangan'],
                ],
                'sample_content' => [
                    ...$weddingSample,
                    'cover' => ['title' => 'Adam & Hawa', 'subtitle' => 'Malam Lamaran', 'date_text' => 'Sabtu, 12 Juni 2027', 'background_image' => '/images/demo/cover.svg', 'overlay' => true],
                    'event' => ['events' => [['title' => 'Makan Malam Lamaran', 'date' => '2027-06-12', 'start_time' => '19:00', 'end_time' => '22:00', 'venue' => 'The Glasshouse', 'address' => 'Jl. Senopati No. 8, Jakarta']]],
                ],
            ],
            [
                'slug' => 'joyful-birthday',
                'name' => 'Joyful Birthday',
                'category' => 'birthday',
                'description' => 'Cerah, modern, dan ceria — lengkap dengan susunan acara dan dinding ucapan.',
                'is_premium' => false,
                'theme' => ['preset' => 'modern', 'secondary_color' => '#f59e0b'],
                'decoration' => ['divider' => 'dots', 'texture' => 'none', 'animation' => 'fade-up'],
                'schema' => ['sections' => [
                    ['type' => 'cover', 'variant' => 'centered'],
                    'couple',
                    ['type' => 'event', 'background' => 'muted'],
                    ['type' => 'countdown', 'background' => 'primary'],
                    'timeline',
                    ['type' => 'gallery', 'background' => 'muted'],
                    'rsvp',
                    ['type' => 'wishes', 'background' => 'muted'],
                    'closing',
                ]],
                'default_configuration' => [
                    'couple' => ['heading' => 'Yang Berulang Tahun'],
                    'event' => ['heading' => 'Detail Pesta'],
                    'timeline' => ['heading' => 'Susunan Acara'],
                ],
                'sample_content' => [
                    'cover' => ['title' => 'Sarah Ulang Tahun ke-7!', 'subtitle' => 'Pesta Ulang Tahun', 'date_text' => 'Minggu, 14 Maret 2027'],
                    'couple' => ['intro' => 'Ayo rayakan bersama dengan kue, permainan, dan banyak keseruan.', 'people' => [['name' => 'Sarah', 'role' => '7 tahun', 'photo' => '/images/demo/gallery-1.svg']]],
                    'event' => ['events' => [['title' => 'Pesta Ulang Tahun', 'date' => '2027-03-14', 'start_time' => '15:00', 'end_time' => '18:00', 'venue' => 'Playland Kemang', 'address' => 'Jl. Kemang Raya No. 12, Jakarta']]],
                    'timeline' => ['items' => [['time' => '15:00', 'title' => 'Penyambutan & permainan'], ['time' => '16:00', 'title' => 'Pertunjukan sulap'], ['time' => '17:00', 'title' => 'Tiup lilin & potong kue']]],
                    'gallery' => ['images' => ['/images/demo/gallery-1.svg', '/images/demo/gallery-3.svg', '/images/demo/gallery-4.svg']],
                    'closing' => ['message' => 'Sampai jumpa di pesta!', 'signature' => 'Sarah & keluarga'],
                ],
            ],
            [
                'slug' => 'modern-corporate',
                'name' => 'Modern Corporate',
                'category' => 'corporate',
                'description' => 'Bersih dan minimalis untuk peluncuran produk, seminar, dan acara kantor.',
                'is_premium' => false,
                'theme' => ['preset' => 'minimal', 'primary_color' => '#1e3a5f', 'secondary_color' => '#3b82f6'],
                'decoration' => ['divider' => 'line', 'texture' => 'none', 'animation' => 'fade'],
                'schema' => ['sections' => [
                    ['type' => 'cover', 'variant' => 'centered'],
                    ['type' => 'event', 'background' => 'muted'],
                    'couple',
                    ['type' => 'timeline', 'background' => 'muted'],
                    'maps',
                    ['type' => 'rsvp', 'background' => 'primary'],
                    'contact',
                    'closing',
                ]],
                'default_configuration' => [
                    'couple' => ['heading' => 'Pembicara'],
                    'timeline' => ['heading' => 'Agenda'],
                    'closing' => ['heading' => 'Sampai Jumpa'],
                    'cover' => ['button_label' => 'Lihat Detail'],
                ],
                'sample_content' => [
                    'cover' => ['title' => 'Peluncuran Produk 2027', 'subtitle' => 'Acme Indonesia', 'date_text' => 'Kamis, 20 Mei 2027'],
                    'event' => ['events' => [['title' => 'Acara Peluncuran', 'date' => '2027-05-20', 'start_time' => '09:00', 'end_time' => '12:00', 'venue' => 'Jakarta Convention Center', 'address' => 'Jl. Gatot Subroto, Jakarta']]],
                    'couple' => ['people' => [['name' => 'Rina Hartono', 'role' => 'CEO, Acme Indonesia'], ['name' => 'Budi Santoso', 'role' => 'Head of Product']]],
                    'timeline' => ['items' => [['time' => '09:00', 'title' => 'Registrasi'], ['time' => '09:30', 'title' => 'Sambutan utama'], ['time' => '10:30', 'title' => 'Demo produk'], ['time' => '11:30', 'title' => 'Networking']]],
                    'maps' => ['venue' => 'Jakarta Convention Center', 'address' => 'Jl. Gatot Subroto, Jakarta', 'maps_url' => 'https://maps.google.com/?q=Jakarta+Convention+Center', 'latitude' => -6.2146, 'longitude' => 106.8076],
                    'contact' => ['contacts' => [['name' => 'Tim Acara', 'role' => 'Acme Indonesia', 'phone' => '081298765432']]],
                    'closing' => ['message' => 'Kami menantikan kehadiran Anda.', 'signature' => 'Acme Indonesia'],
                ],
            ],
            $this->mochaGlass(),
            $this->botanicalCream(),
        ];
    }

    /**
     * Second design pack, built from the "Ilham & Aisyah" cream botanical mockup.
     *
     * @return array<string, mixed>
     */
    private function botanicalCream(): array
    {
        $corners = [
            ['image' => '/illustrations/ornament-cream-blossom-corner.svg', 'x' => 0, 'y' => 100, 'width' => 38, 'anchor' => 'bottom-left', 'layer' => 'back', 'enter' => 'fade-up', 'motion' => 'sway'],
            ['image' => '/illustrations/ornament-cream-blossom-corner.svg', 'x' => 100, 'y' => 100, 'width' => 38, 'anchor' => 'bottom-right', 'flip' => true, 'layer' => 'back', 'enter' => 'fade-up', 'motion' => 'sway', 'delay' => 0.3],
        ];

        return [
            'slug' => 'botanical-cream',
            'name' => 'Botanical Cream',
            'category' => 'wedding',
            'description' => 'Halaman krem lembut dengan ilustrasi bunga: foto sampul yang memudar, nama bergaya tulisan tangan dengan monogram, kartu acara berikon, linimasa kisah, serta menu bawah berlabel.',
            'is_premium' => true,
            'design' => 'botanical-cream',
            'decoration' => ['animation' => 'fade-up', 'animation_speed' => 'normal'],
            'theme' => [
                'primary_color' => '#6b5444', 'secondary_color' => '#b49a7c', 'background_color' => '#f7f1e8',
                'surface_color' => '#fffdf9', 'text_color' => '#4b3a2f',
                'font_heading' => 'Cormorant Garamond', 'font_body' => 'Lora',
                'button_style' => 'pill', 'border_radius' => 'large', 'section_spacing' => 'normal',
            ],
            'schema' => ['sections' => [
                ['type' => 'cover', 'ornaments' => $corners],
                'quote',
                'countdown',
                ['type' => 'couple', 'variant' => 'stacked', 'ornaments' => $corners],
                ['type' => 'event', 'variant' => 'cards'],
                'maps',
                ['type' => 'story', 'ornaments' => $corners],
                ['type' => 'gallery', 'variant' => 'grid'],
                'rsvp',
                'gift',
                'wishes',
                ['type' => 'closing', 'background' => 'primary'],
            ]],
            'default_configuration' => [
                'cover' => ['subtitle' => 'The Wedding of', 'button_label' => 'Gulir ke bawah'],
                'countdown' => ['heading' => 'Menuju Hari Bahagia'],
                'couple' => ['heading' => 'Mempelai', 'photo_shape' => 'circle', 'photo_frame' => '/illustrations/frame-round-cream-wreath.svg', 'photo_frame_inset' => 12],
                'event' => ['heading' => 'Acara Pernikahan'],
                'maps' => ['heading' => 'Lokasi'],
                'story' => ['heading' => 'Kisah Cinta'],
                'gallery' => ['heading' => 'Galeri Foto'],
                'rsvp' => ['heading' => 'Konfirmasi Kehadiran', 'description' => ''],
                'gift' => ['heading' => 'Amplop Digital', 'description' => ''],
                'wishes' => ['heading' => 'Ucapan & Doa', 'description' => 'Tulis ucapan dan doa terbaik untuk kami…'],
                'closing' => ['heading' => 'Terima Kasih', 'message' => 'Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.'],
            ],
            'sample_content' => [
                'cover' => ['title' => 'Ilham & Aisyah', 'date_text' => 'Sabtu, 12 Desember 2026', 'background_image' => '/images/demo/cover.svg'],
                'quote' => [
                    'text' => 'Dan di antara tanda-tanda (kebesaran)-Nya ialah Dia menciptakan pasangan-pasangan untukmu dari jenismu sendiri, agar kamu cenderung dan merasa tenteram kepadanya, dan Dia menjadikan di antaramu rasa kasih dan sayang.',
                    'author' => 'QS. Ar-Rum: 21',
                ],
                'couple' => ['people' => [
                    ['name' => 'Ilham Pambudi Utomo', 'parents' => 'Putra pertama dari Bapak Lorem Ipsum & Ibu Dolor Sit Amet', 'photo' => '/illustrations/person-groom-suit.svg', 'instagram' => 'https://instagram.com/'],
                    ['name' => 'Aisyah Nur Rahma', 'parents' => 'Putri pertama dari Bapak Lorem Ipsum & Ibu Dolor Sit Amet', 'photo' => '/illustrations/person-bride-hijab.svg', 'instagram' => 'https://instagram.com/'],
                ]],
                'event' => ['events' => [
                    ['title' => 'Akad Nikah', 'date' => '2026-12-12', 'start_time' => '08:00', 'end_time' => '10:00', 'venue' => 'Masjid Agung Prambanan', 'address' => 'Sleman, Yogyakarta'],
                    ['title' => 'Resepsi', 'date' => '2026-12-12', 'start_time' => '11:00', 'end_time' => '14:00', 'venue' => 'Pendopo Prambanan', 'address' => 'Sleman, Yogyakarta', 'maps_url' => 'https://maps.google.com/?q=Candi+Prambanan', 'add_to_calendar' => true],
                ]],
                'maps' => [
                    'venue' => 'Pendopo Prambanan', 'address' => 'Jl. Raya Solo - Yogyakarta, Bokoharjo, Prambanan, Sleman, Yogyakarta',
                    'maps_url' => 'https://maps.google.com/?q=Candi+Prambanan', 'latitude' => -7.752, 'longitude' => 110.4915,
                ],
                'story' => [
                    'entries' => [
                        ['icon' => 'heart', 'title' => 'Pertemuan', 'date' => '2020', 'description' => 'Berawal dari pertemuan yang tak disengaja, namun memberikan kesan mendalam.'],
                        ['icon' => 'chat', 'title' => 'Pendekatan', 'date' => '2021', 'description' => 'Kami mulai saling mengenal dan menemukan banyak kesamaan.'],
                        ['icon' => 'ring', 'title' => 'Lamaran', 'date' => '2025', 'description' => 'Dengan penuh keyakinan, kami melangkah lebih serius dalam ikatan pertunangan.'],
                        ['icon' => 'home', 'title' => 'Menuju Halal', 'date' => '2026', 'description' => 'InsyaAllah, kami akan menempuh ibadah terpanjang bersama dalam ikatan suci pernikahan.'],
                    ],
                    'closing_quote' => 'Perjalanan ini bukan tentang siapa yang datang lebih dulu, tapi tentang siapa yang tetap tinggal hingga akhirnya kita sampai di titik ini.',
                ],
                'gallery' => ['images' => ['/images/demo/gallery-1.svg', '/images/demo/gallery-2.svg', '/images/demo/gallery-3.svg', '/images/demo/gallery-4.svg', '/illustrations/gallery-bouquet.svg', '/illustrations/gallery-doves.svg', '/illustrations/gallery-cake.svg']],
                'gift' => ['accounts' => [
                    ['type' => 'bank', 'provider' => 'Bank BCA', 'account_number' => '1234567890', 'account_name' => 'Ilham Pambudi Utomo'],
                ]],
                'closing' => [
                    'signature' => 'Wassalamu\'alaikum Warahmatullahi Wabarakatuh — Kami yang berbahagia, Ilham & Aisyah',
                    'background_photo' => '/images/demo/gallery-4.svg',
                    'background_overlay' => 'medium',
                ],
            ],
        ];
    }

    /**
     * First design-pack template, converted from the "Arina & Bagas" HTML design.
     *
     * @return array<string, mixed>
     */
    private function mochaGlass(): array
    {
        return [
            'slug' => 'mocha-glass',
            'name' => 'Mocha Glass',
            'category' => 'wedding',
            'description' => 'Sampul foto layar penuh yang terbuka ke atas, kartu kaca buram, kilau partikel, dan menu bawah. Di desktop tampil panel foto di samping undangan.',
            'is_premium' => true,
            'design' => 'mocha-glass',
            'decoration' => ['animation' => 'fade-up', 'animation_speed' => 'normal'],
            'theme' => [
                'primary_color' => '#0f0b08', 'secondary_color' => '#d8c3a0', 'background_color' => '#2e221a',
                'surface_color' => '#f7f2e9', 'text_color' => '#ffffff',
                'font_heading' => 'Cinzel Decorative', 'font_body' => 'Jost',
                'button_style' => 'pill', 'border_radius' => 'large', 'section_spacing' => 'normal',
            ],
            'schema' => ['sections' => [
                'cover', 'quote', ['type' => 'couple', 'variant' => 'stacked'], ['type' => 'event', 'variant' => 'list'], 'story',
                ['type' => 'gallery', 'variant' => 'slideshow'], 'rsvp', 'gift', 'wishes', 'dress_code', 'health_protocol', 'closing',
            ]],
            'default_configuration' => [
                'cover' => ['subtitle' => 'The Wedding of', 'button_label' => 'Buka Undangan'],
                'couple' => ['heading' => '', 'opening' => 'بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ', 'intro' => "Kami mohon do'a & restunya atas pernikahan kami"],
                'event' => ['heading' => 'Acara'],
                'story' => ['heading' => 'Kisah Cinta'],
                'rsvp' => ['heading' => 'RSVP', 'description' => ''],
                'gift' => ['heading' => 'Kado', 'description' => '', 'wishlist_heading' => 'Daftar Kado'],
                'wishes' => ['heading' => 'Ucapan & Doa', 'description' => ''],
                'closing' => ['heading' => 'Hormat Kami', 'message' => "Atas kehadiran saudara/(i) & do'a restunya, kami ucapkan terima kasih"],
            ],
            'sample_content' => [
                'cover' => ['title' => 'Arina & Bagas', 'date_text' => 'Kamis, 31 Desember 2026', 'background_image' => '/images/demo/cover.svg'],
                'quote' => [
                    'arabic_text' => 'وَمِنْ اٰيٰتِهٖٓ اَنْ خَلَقَ لَكُمْ مِّنْ اَنْفُسِكُمْ اَزْوَاجًا لِّتَسْكُنُوْٓا اِلَيْهَا وَجَعَلَ بَيْنَكُمْ مَّوَدَّةً وَّرَحْمَةً',
                    'text' => 'Dan di antara tanda-tanda kekuasaan-Nya ialah Dia menciptakan untukmu isteri-isteri dari jenismu sendiri, supaya kamu merasa tenang dan tentram kepadanya, dan dijadikan-Nya diantaramu rasa kasih dan sayang.',
                    'author' => 'QS. Ar-Rum: 21',
                ],
                'couple' => ['people' => [
                    ['name' => 'Arina Febriana', 'parents' => 'Putri ke-1 Bpk. Sumarni & Ibu Maryati', 'photo' => '/illustrations/person-bride-hijab.svg', 'instagram' => 'https://instagram.com/', 'facebook' => 'https://facebook.com/'],
                    ['name' => 'Bagas Sadewa', 'parents' => 'Putra ke-2 Bpk. Samuel & Ibu Masuya', 'photo' => '/illustrations/person-groom-peci.svg', 'instagram' => 'https://instagram.com/', 'tiktok' => 'https://tiktok.com/'],
                ]],
                'event' => ['events' => [
                    ['title' => 'Akad Nikah', 'date' => '2026-12-31', 'start_time' => '08:00', 'end_time' => '10:00', 'notice' => 'Acara ini diselenggarakan secara terbatas dan hanya diperuntukan bagi keluarga serta tamu undangan tertentu.'],
                    ['title' => 'Resepsi', 'date' => '2026-12-31', 'start_time' => '10:00', 'end_time' => '14:00', 'venue' => 'Grand Ballroom Hotel', 'address' => 'Jl. Contoh No. 123, Kota Bandung, Jawa Barat', 'maps_url' => 'https://maps.google.com/?q=Bandung', 'add_to_calendar' => true],
                ]],
                'story' => ['entries' => [
                    ['title' => 'Pertemuan Pertama', 'description' => 'Kisah ini berawal ketika jumpa pandangan pertama di kampus.', 'image' => '/illustrations/story-coffee.svg'],
                    ['title' => 'Lamaran', 'description' => 'Tak disangka, cerita ini semakin erat untuk memperkokoh niat beribadah. Sehingga prosesi lamaran ini dilaksanakan.', 'image' => '/illustrations/story-rings.svg'],
                ]],
                'gallery' => ['images' => ['/images/demo/gallery-1.svg', '/images/demo/gallery-2.svg', '/images/demo/gallery-3.svg', '/images/demo/gallery-4.svg']],
                'gift' => [
                    'accounts' => [
                        ['type' => 'bank', 'provider' => 'Bank BCA', 'account_number' => '1234567890', 'account_name' => 'Arina Febriana'],
                        ['type' => 'bank', 'provider' => 'Bank Mandiri', 'account_number' => '0987654321', 'account_name' => 'Bagas Sadewa'],
                    ],
                    'wishlist' => [
                        ['item' => 'Rice Cooker', 'url' => 'https://www.tokopedia.com/'],
                        ['item' => 'Set Peralatan Makan', 'url' => 'https://www.tokopedia.com/'],
                        ['item' => 'Sprei King Size', 'url' => 'https://www.tokopedia.com/'],
                    ],
                ],
                'dress_code' => [
                    'heading' => 'Dresscode',
                    'description' => 'Kami senang bila tamu berbusana sesuai momen istimewa kami.',
                    'men' => 'Semi formal / formal',
                    'women' => 'Semi formal / formal',
                    'color_note' => 'Earth tone, pastel, atau netral',
                    'avoid' => 'Jeans, kaos, celana pendek & sandal jepit',
                    'notes' => "Area parkir luas tersedia\nPetugas parkir siap membantu\nMohon parkir di area yang disediakan",
                ],
                'closing' => ['signature' => 'Arina & Bagas', 'inviters' => 'Kel. Bpk Sumarni & Ibu Sukmana'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function weddingSample(): array
    {
        return [
            'cover' => ['title' => 'Romeo & Juliet', 'subtitle' => 'Pernikahan', 'date_text' => 'Sabtu, 12 Juni 2027', 'background_image' => '/images/demo/cover.svg', 'overlay' => true],
            'quote' => ['text' => 'Dan di antara tanda-tanda kekuasaan-Nya ialah Dia menciptakan untukmu pasangan hidup dari jenismu sendiri, supaya kamu merasa tenteram kepadanya.', 'author' => 'QS. Ar-Rum: 21'],
            'couple' => [
                'intro' => 'Dengan memohon rahmat dan rida Tuhan Yang Maha Esa, kami bermaksud menyelenggarakan pernikahan kami.',
                'people' => [
                    ['name' => 'Romeo Pratama', 'role' => 'Mempelai Pria', 'parents' => 'Putra dari Bapak & Ibu Pratama', 'photo' => '/images/demo/gallery-2.svg'],
                    ['name' => 'Juliet Lestari', 'role' => 'Mempelai Wanita', 'parents' => 'Putri dari Bapak & Ibu Lestari', 'photo' => '/images/demo/gallery-3.svg'],
                ],
            ],
            'event' => ['events' => [
                ['title' => 'Akad Nikah', 'date' => '2027-06-12', 'start_time' => '10:00', 'end_time' => '11:30', 'venue' => 'Masjid Agung Al-Azhar', 'address' => 'Jl. Sisingamangaraja, Jakarta'],
                ['title' => 'Resepsi', 'date' => '2027-06-12', 'start_time' => '19:00', 'end_time' => '21:30', 'venue' => 'Jakarta Convention Center', 'address' => 'Jl. Gatot Subroto, Jakarta'],
            ]],
            'story' => ['entries' => [
                ['date' => '2019', 'title' => 'Pertama Bertemu', 'description' => 'Kami bertemu di acara ulang tahun seorang teman.'],
                ['date' => '2022', 'title' => 'Lamaran', 'description' => 'Lamaran sederhana saat matahari terbenam di tepi pantai.'],
                ['date' => '2027', 'title' => 'Pernikahan', 'description' => 'Dan kini, selamanya dimulai.'],
            ]],
            'gallery' => ['images' => ['/images/demo/gallery-1.svg', '/images/demo/gallery-2.svg', '/images/demo/gallery-3.svg', '/images/demo/gallery-4.svg']],
            'maps' => ['venue' => 'Jakarta Convention Center', 'address' => 'Jl. Gatot Subroto, Jakarta', 'maps_url' => 'https://maps.google.com/?q=Jakarta+Convention+Center', 'latitude' => -6.2146, 'longitude' => 106.8076],
            'rsvp' => ['deadline' => '2027-05-30'],
            'gift' => ['accounts' => [['type' => 'bank', 'provider' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Romeo Pratama']]],
            'closing' => ['message' => 'Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.', 'signature' => 'Romeo & Juliet'],
        ];
    }
}
