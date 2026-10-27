<?php

namespace Database\Seeders;

use App\Enums\IllustrationCategory;
use App\Models\Illustration;
use Illuminate\Database\Seeder;

/**
 * Built-in starter illustrations shipped in public/illustrations (trusted, hand-written SVG).
 * Idempotent: existing rows are left as the admin configured them.
 */
class IllustrationSeeder extends Seeder
{
    private const ILLUSTRATIONS = [
        ['cover', 'cover-floral-arch.svg', 'Lengkung Bunga', 1200, 1600],
        ['cover', 'cover-botanical.svg', 'Daun Botani', 1200, 1600],
        ['cover', 'cover-sunset-hills.svg', 'Bukit Senja', 1200, 1600],
        ['person', 'person-groom-peci.svg', 'Mempelai Pria (Peci)', 600, 600],
        ['person', 'person-groom-suit.svg', 'Mempelai Pria (Jas)', 600, 600],
        ['person', 'person-bride.svg', 'Mempelai Wanita', 600, 600],
        ['person', 'person-bride-hijab.svg', 'Mempelai Wanita (Hijab)', 600, 600],
        ['person', 'person-child.svg', 'Anak', 600, 600],
        ['story', 'story-rings.svg', 'Cincin', 600, 600],
        ['story', 'story-plane.svg', 'Perjalanan', 600, 600],
        ['story', 'story-coffee.svg', 'Kencan Kopi', 600, 600],
        ['story', 'story-home.svg', 'Rumah Kita', 600, 600],
        ['gallery', 'gallery-bouquet.svg', 'Buket Bunga', 600, 600],
        ['gallery', 'gallery-doves.svg', 'Sepasang Merpati', 600, 600],
        ['gallery', 'gallery-cake.svg', 'Kue Pengantin', 600, 600],
        ['ornament', 'ornament-flower-corner.svg', 'Rangkaian Bunga (pojok)', 400, 400],
        ['ornament', 'ornament-hanging-branch.svg', 'Ranting Menjuntai (pojok atas)', 400, 400],
        ['ornament', 'ornament-side-vine.svg', 'Sulur (tepi)', 160, 600],
        ['ornament', 'ornament-cream-blossom-corner.svg', 'Bunga Krem (pojok)', 400, 400],
        ['frame', 'frame-oval-gold.svg', 'Bingkai Oval Emas', 400, 500],
        ['frame', 'frame-oval-floral.svg', 'Bingkai Oval Bunga Melati', 400, 500],
        ['frame', 'frame-arch-gold.svg', 'Bingkai Lengkung Emas (bentuk foto: Lengkung)', 400, 500],
        ['frame', 'frame-round-cream-wreath.svg', 'Bingkai Bulat Bunga Krem (bentuk foto: Lingkaran)', 400, 400],
    ];

    public function run(): void
    {
        foreach (self::ILLUSTRATIONS as $sortOrder => [$category, $file, $name, $width, $height]) {
            Illustration::query()->firstOrCreate(
                ['disk' => null, 'path' => 'illustrations/'.$file],
                [
                    'category' => IllustrationCategory::from($category),
                    'name' => $name,
                    'width' => $width,
                    'height' => $height,
                    'sort_order' => $sortOrder,
                ],
            );
        }
    }
}
