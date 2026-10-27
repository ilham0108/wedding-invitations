# Struktur Template

Template di Invitefully adalah **konfigurasi murni** (rules.md §14, §87). Renderer tidak mengenal template
tertentu; ia hanya membaca konfigurasi. Karena itu template baru tidak membutuhkan perubahan pada model,
controller, sistem tamu, RSVP, maupun WhatsApp.

## Lapisan sebuah template

| Lapisan | Kolom `invitation_templates` | Isi |
|---|---|---|
| Desain | `design` | Paket desain (tampilan halaman), lihat bagian *Paket desain* di bawah |
| Struktur | `schema.sections` | Urutan section; tiap section punya `type`, `variant`, `background` (`default`/`muted`/`primary`) |
| Tema | `theme` | `preset` (lihat `config/invitation.php` → `theme_presets`) + override warna, font, tombol, radius, spasi |
| Dekorasi | `decoration` | `divider`, `texture`, `animation` (lihat `decoration_options`; **belum dirender**, cadangan untuk desain) |
| Isi bawaan | `default_configuration` | Teks bawaan per section, disalin ke undangan klien saat dibuat |
| Contoh isi | `sample_content` | Hanya untuk halaman preview template |
| Versi format | `schema_version` | Versi format definisi (`TemplateSchema::VERSION`) |

Contoh definisi:

```php
[
    'schema_version' => 1,
    'design' => 'classic',
    'schema' => ['sections' => [
        ['type' => 'cover', 'variant' => 'framed'],
        ['type' => 'quote', 'background' => 'muted'],
        'couple',                                  // string = varian pertama, latar default
        ['type' => 'event', 'variant' => 'cards'],
        'closing',
    ]],
    'theme' => ['preset' => 'elegant', 'primary_color' => '#365149'],
    'decoration' => ['divider' => 'flourish', 'texture' => 'paper', 'animation' => 'fade-up'],
    'default_configuration' => ['couple' => ['heading' => 'Kedua Mempelai']],
    'sample_content' => ['cover' => ['title' => 'Romeo & Juliet']],
]
```

## Validasi: `App\Services\Template\TemplateSchema`

Setiap definisi template **wajib** lewat `TemplateSchema::validate()` sebelum disimpan (seeder, builder admin,
dan import). Validator ini:

- menolak jenis section, varian, latar, warna, font, preset, atau nilai dekorasi yang tidak dikenal;
- memvalidasi `default_configuration` dan `sample_content` dengan aturan field yang sama seperti editor klien;
- mengembalikan bentuk kanonik (setiap section eksplisit `{type, variant, background}`) dan membuang key asing.

## Template disalin ke undangan

Saat klien membuat undangan, **susunan section, isi bawaan, desain, tema, dan dekorasi disalin** ke undangan
(`invitation_sections`, `invitation_settings.design`, `invitation_settings.template_theme`, `invitation_settings.decoration`).
Mengedit template setelahnya hanya berlaku untuk undangan **baru**; undangan yang sudah dikirim ke tamu tidak
berubah. Perubahan tema oleh klien disimpan di `invitation_settings.theme` dan menimpa salinan template.

Urutan penggabungan tema: `default_theme` → salinan tema template → tema klien.

## Menambah template baru

**Dengan komponen yang sudah ada** (tanpa kode): Admin → Templates → *Template baru* (atau *Duplikat* dari template
yang mirip). Template baru selalu berstatus draf dan hanya terlihat oleh admin (termasuk halaman pratinjaunya).
Tombol *Publikasikan* memvalidasi ulang konfigurasinya dengan `TemplateSchema`. Template yang sudah dipakai
undangan tidak bisa dihapus; ia hanya dibatalkan publikasinya.

Struktur dan isi diatur di **Builder** (tombol *Builder* pada kartu template):

- daftar bagian di kiri: tambah, seret untuk mengurutkan, duplikat, hapus;
- panel *Bagian*: tata letak (varian), latar, lalu **Konten bawaan** (disalin ke setiap undangan baru) dan
  **Contoh konten** (hanya untuk pratinjau template). Yang disimpan hanya nilai yang berbeda dari bawaan;
- panel *Tema*: preset, warna, font, gaya tombol, radius, spasi;
- pratinjau di tengah menampilkan draf yang belum disimpan, dengan atau tanpa contoh konten.

Perubahan builder disimpan sebagai **draf pribadi per admin** (`App\Services\Template\TemplateDraft`, cache 12 jam).
Template baru berubah setelah *Simpan template*; *Buang* mengembalikan versi tersimpan. Karena undangan menyimpan
salinan sendiri, menyimpan template tidak mengubah undangan yang sudah ada.

Kategori (Admin → Categories) masing-masing terhubung ke satu **jenis acara** (`template_categories.event_type`).
Wizard pembuatan undangan menampilkan template terpublikasi dari semua kategori aktif untuk jenis acara yang dipilih,
jadi beberapa kategori (misalnya "Wedding Islami" dan "Wedding Modern") bisa melayani satu jenis acara.

Alternatif untuk developer: tambahkan satu entri di `database/seeders/InvitationTemplateSeeder.php` lalu jalankan:

```sh
php artisan db:seed --class=InvitationTemplateSeeder
```

Seeder akan gagal dengan pesan jelas bila definisinya tidak valid.

## Menambah varian section baru

Setiap varian adalah satu file Blade:

```
resources/views/invitation/sections/{type}/{variant}.blade.php
```

1. Buat file, misalnya `resources/views/invitation/sections/quote/framed.blade.php`.
   Variabel yang tersedia: `$config` (isi section yang sudah dibersihkan), `$variant`, dan `$invitation`
   (`RenderedInvitation`). Potongan yang dipakai beberapa varian diletakkan di file berawalan `_`
   (contoh: `couple/_person.blade.php`).
2. Tambahkan nama varian ke `config/invitation.php` → `sections.{type}.variants`.
   Varian pertama dalam daftar menjadi varian bawaan.
3. Tambahkan label varian ke `lang/id.json` (`LocalizationTest` memeriksanya).
4. Jalankan `php artisan test --filter=SectionRegistryTest`. Test ini gagal bila ada varian tanpa file Blade.

Gunakan hanya class dan variabel CSS tema (`inv-heading`, `inv-card`, `inv-btn`, `var(--inv-primary)`, dll.)
agar varian otomatis mengikuti tema setiap template.

## Menambah jenis section baru

Butuh kode: entri baru di `config/invitation.php` (`sections` dan `fields`), folder view
`resources/views/invitation/sections/{type}/` dengan minimal satu varian, dan normalisasi URL/data di
`InvitationRenderer::normalizeConfig()` bila section menampilkan URL atau media.

### Field khusus admin

Field dengan `'admin_only' => true` hanya tampil di builder template. Klien tidak melihatnya di editor
undangan, dan nilainya selalu dikembalikan ke nilai tersimpan saat klien menyimpan (`SectionSettings`),
jadi request buatan pun tidak bisa mengubahnya. Pakai untuk pengaturan tampilan yang ditentukan template,
misalnya bentuk dan bingkai foto mempelai (`photo_shape`, `photo_frame`, `photo_frame_inset`) dan lapisan
warna foto latar (`background_overlay`). Penanda ini berlaku untuk field tingkat atas, bukan field di dalam
repeater.

## Paket desain

Paket desain menentukan **tampilan** halaman undangan; datanya (bagian, konten, RSVP, ucapan) tetap sama untuk
semua desain. Satu desain bisa dipakai banyak template (misalnya warna berbeda lewat tema).

```
config/invitation.php → designs.{key}      label, deskripsi, font tambahan, aset Vite
resources/views/designs/{key}/page.blade.php            kerangka halaman (opsional)
resources/views/designs/{key}/sections/{type}.blade.php tampilan per bagian (opsional)
resources/css/designs/{key}.css, resources/js/designs/{key}.js
```

- Bagian tanpa file di `sections/` otomatis memakai tampilan klasik (`App\Services\Invitation\DesignRegistry`).
  Di Mocha Glass, tampilan klasik itu dibungkus kartu kaca (`native = false`); di Botanical Cream dalam kartu krem.
- Paket yang ada: **Mocha Glass** (dari `undangan.html`) dan **Botanical Cream** (dari mockup "Ilham & Aisyah":
  monogram otomatis dari judul sampul, menu bawah berlabel + menu lengkap, ikon acara dipilih dari judulnya,
  galeri "Lihat Semua Foto", ucapan per halaman). Ilustrasi bawaannya: `ornament-cream-blossom-corner.svg`
  dan `frame-round-cream-wreath.svg`.
- Pakai variabel tema (`var(--inv-primary)`, `var(--inv-surface)`, `var(--inv-font-heading)`, …) agar admin
  tetap bisa mengganti warna dan font di Builder.
- Form RSVP dan ucapan **wajib** memakai `invitation.partials.rsvp-form` / `invitation.partials.wishes-form`
  (CSRF, token tamu, honeypot, mode pratinjau). Beri pembungkusnya `id="rsvp-form"` / `id="wishes-form"`:
  setelah form dikirim, halaman kembali ke anchor itu.
- URL gambar di `style="…"` harus lewat `App\Support\CssUrl::wrap()`; di atribut `src`/`href` cukup URL
  yang sudah dibersihkan renderer.
- Daftarkan aset di `designs.{key}.assets` **dan** di `vite.config.js` (`DesignPackTest` memeriksanya), lalu
  `npm run build`.

### Mengubah HTML menjadi paket desain

1. Desain bebas sebagai HTML statis (seperti `undangan.html` → Mocha Glass).
2. Petakan setiap blok ke jenis bagian (`cover`, `couple`, `event`, …). Data yang belum ada menjadi field baru
   di `config/invitation.php` (`fields` + `defaults`), bukan teks tertanam di HTML.
3. Pindahkan CSS/JS ke file desain; ganti nilai `CONFIG` di JavaScript dengan `$config` dari Blade.
4. Tambahkan template contoh di `InvitationTemplateSeeder` (`'design' => '{key}'`), lalu sempurnakan lewat Builder.


## Ekspor & impor template (.zip)

Admin → Template: ikon **Ekspor (.zip)** di tiap template, tombol **Impor** di atas daftar
(`App\Services\Template\TemplatePackage`).

Isi arsip:

- `template.json` — `format: "invitefully-template"`, `version`, data katalog (nama, slug, deskripsi,
  premium, kategori), dan `definition` (struktur, tema, dekorasi, konten bawaan & contoh, ornamen).
- `assets/…` — semua gambar unggahan (`/storage/…`) yang dipakai konfigurasi, plus thumbnail.
  File bawaan aplikasi (`/illustrations/…`, `/images/…`) tidak ikut dikemas karena sudah ada di kode.

Saat impor:

- Template selalu masuk sebagai **Draft** dengan slug unik; periksa di Builder lalu publikasikan.
- Gambar disalin ke `storage/app/public/templates/imported/{ulid}/` dan URL di konfigurasi diganti.
- Kategori dicocokkan lewat slug; bila belum ada, dibuat kategori baru yang **nonaktif**.
- Paket desain (`design`) harus terpasang di aplikasi tujuan, kalau tidak impor ditolak.
- Keamanan: hanya file yang tercantum di `template.json` yang dibaca (tanpa *zip slip*), jumlah dan
  ukuran file dibatasi (tanpa *zip bomb*), gambar dicek dari isinya (PNG/JPG/WebP saja), dan
  konfigurasi divalidasi ulang dengan `TemplateSchema`.
- Ukuran arsip maksimal 12 MB (batas upload sementara Livewire).
