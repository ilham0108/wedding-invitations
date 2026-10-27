<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter legal pages for the admin CMS. They are drafts: the owner must review the text
 * (it is an outline, not legal advice) and publish them. Existing pages are never overwritten.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $app = config('app.name');

        $pages = [
            'syarat-ketentuan' => [
                'title' => 'Syarat & Ketentuan',
                'sort_order' => 1,
                'body' => <<<MD
                > Draf awal — tinjau dan sesuaikan sebelum dipublikasikan.

                ## 1. Layanan
                {$app} menyediakan layanan pembuatan dan pengelolaan undangan digital, termasuk manajemen tamu, RSVP, dan pengiriman melalui WhatsApp.

                ## 2. Akun
                Anda bertanggung jawab menjaga kerahasiaan akun dan semua aktivitas yang terjadi di dalamnya.

                ## 3. Konten pengguna
                Anda bertanggung jawab atas foto, teks, dan musik yang Anda unggah, termasuk memastikan Anda berhak menggunakannya.

                ## 4. Pembayaran dan langganan
                Harga dan fitur setiap paket tercantum di halaman harga. Paket yang berakhir dapat membuat sebagian undangan tidak dapat diakses tamu.

                ## 5. Penangguhan akun
                Kami dapat menangguhkan akun yang melanggar ketentuan ini atau hukum yang berlaku.

                ## 6. Perubahan ketentuan
                Ketentuan ini dapat diperbarui sewaktu-waktu. Tanggal pembaruan terakhir tercantum di bagian atas halaman.
                MD,
            ],
            'kebijakan-privasi' => [
                'title' => 'Kebijakan Privasi',
                'sort_order' => 2,
                'body' => <<<'MD'
                > Draf awal — tinjau dan sesuaikan sebelum dipublikasikan.

                ## 1. Data yang kami kumpulkan
                - Data akun: nama, email, dan nomor telepon.
                - Data undangan: teks, foto, dan musik yang Anda unggah.
                - Data tamu yang Anda masukkan, serta jawaban RSVP dan ucapan dari tamu.

                ## 2. Penggunaan data
                Data digunakan untuk menjalankan layanan, mengirim undangan atas permintaan Anda, dan memberi dukungan.

                ## 3. Berbagi data
                Kami tidak menjual data Anda. Data hanya dibagikan ke penyedia layanan yang diperlukan (misalnya pembayaran dan WhatsApp).

                ## 4. Penyimpanan dan keamanan
                Data disimpan selama akun aktif atau selama diperlukan untuk layanan dan kewajiban hukum.

                ## 5. Hak Anda
                Anda dapat meminta akses, perbaikan, atau penghapusan data Anda dengan menghubungi kami.
                MD,
            ],
        ];

        foreach ($pages as $slug => $page) {
            Page::query()->firstOrCreate(['slug' => $slug], [...$page, 'is_published' => false, 'show_in_footer' => true]);
        }
    }
}
