# Impor template dari HTML

Super admin bisa mengimpor desain buatan sendiri di **Admin → Template → Impor HTML**. Paketnya diperiksa dulu, dan tidak ada yang disimpan sampai semua pemeriksaan lolos. Hasilnya berupa template draf yang bisa diatur di builder. Template baru terlihat oleh klien setelah dipublikasikan dari builder.

Contoh paket lengkap ada di [`docs/contoh-template-html/`](contoh-template-html/). Zip isi folder itu, lalu unggah.

## Alur impor

1. Pilih atau seret file `.zip`.
2. Baca hasil pemeriksaan. Semua **masalah** wajib diperbaiki. **Perlu dicek** hanya peringatan.
3. Perbaiki paket, lalu pilih file itu lagi sampai lolos.
4. Isi nama template dan kategori, lalu klik **Impor sebagai draf**.

## Struktur zip

```
desain-saya.zip
├── index.html   wajib, di tingkat teratas zip (bukan di dalam folder)
├── style.css    wajib
└── assets/      gambar (jpg, jpeg, png, webp) dan font (woff, woff2)
```

| Batas | Nilai |
|---|---|
| Ukuran zip saat diunggah | 12 MB |
| Jumlah file di dalam zip | 300 |
| Ukuran satu file | 10 MB |
| Total ukuran setelah diekstrak | 50 MB |

- Semua aset harus berada di dalam `assets/`. Subfolder boleh, misalnya `assets/img/foto.webp`.
- Nama file hanya boleh berisi huruf, angka, titik, garis bawah, strip, dan garis miring. Hindari spasi.
- Isi file ikut diperiksa. Gambar harus benar-benar gambar, dan font harus benar-benar WOFF atau WOFF2.
- File jenis lain ditolak, termasuk SVG, GIF, JS, dan README.
- Folder `__MACOSX/` dan file `.DS_Store` diabaikan.
- Aset yang tidak dipakai di HTML atau CSS hanya memunculkan peringatan.

## Menandai HTML

| Penanda | Fungsi |
|---|---|
| `<section id="cover">` | Satu section. `id` harus nama section yang didukung (lihat tabel di bawah). |
| `data-inv-field="title"` | Bagian ini menjadi dinamis. Teks atau gambar di dalamnya menjadi contoh konten. |
| `data-inv-repeat="events"` | Elemen yang berulang. Yang pertama menjadi pola, semua elemen menjadi data contoh. |
| `data-inv-value="2026-12-12"` | Nilai asli untuk field tanggal (`YYYY-MM-DD`) atau jam (`HH:MM`), kalau teks tampilannya berbeda. |
| `<inv-guest-name></inv-guest-name>` | Nama tamu dari tautan pribadi. |
| `data-inv-effect="animate"` | Animasi masuk, mengikuti pengaturan Animasi di tab Tema. |
| `data-inv-effect="next"` | Klik elemen ini berpindah ke section berikutnya. |

Bagian yang tidak ditandai tetap statis.

Sapaan tamu di cover:
- Jika cover memakai `<inv-guest-name>`, desainer menulis sapaannya sendiri. Tanpa tautan pribadi, nama diganti "Tamu undangan".
- Jika tidak ada `<inv-guest-name>`, aplikasi menyisipkan "Kepada Yth. Bapak/Ibu/Saudara/i" dan nama tamu tepat sebelum elemen `data-inv-effect="next"`, atau di akhir cover jika elemen itu tidak ada.
- Blok sisipan bisa diberi gaya di `style.css` lewat `.inv-cover-guest`, `.inv-cover-guest__label`, dan `.inv-cover-guest__name`.

Field `title` di cover mengikuti pengaturan "Tata letak judul" di builder. Pilihan tiga baris mengganti isinya dengan tiga `<span class="inv-title-line">`, yang bisa diberi gaya lewat `.inv-title-line` dan `.inv-title-amp`.

Menu bagian melayang diatur di builder, bukan di HTML. Setiap section otomatis punya anchor, jadi menu bisa menaut ke section mana pun.

Aturan section:
- Setiap `id` section hanya boleh muncul satu kali.
- `index.html` harus punya minimal satu section yang valid.
- `data-inv-repeat` tidak boleh berada di dalam repeat lain, dan hanya boleh dipakai untuk field berulang (`people`, `events`).
- Tag lain berawalan `inv-` selain `<inv-guest-name>` belum tersedia.

Isi field yang ditandai menjadi **Contoh konten** template. Teks umum dan gambar desain juga menjadi **Konten bawaan**, sehingga muncul di undangan baru: label tombol dan foto sampul; ornamen, kalimat pembuka, dan kalimat sambutan; judul section; serta pesan dan salam penutup. Data pribadi seperti nama, tanggal, tempat, dan daftar orang tetap hanya menjadi contoh. Subjudul sampul juga menjadi konten bawaan template, tetapi pada undangan baru selalu diisi dari jenis acara yang dipilih klien.

Cara menandai sesuai jenis field:
- **Teks dan teks panjang:** elemen apa pun. Isinya hanya teks dan `<br>`, tanpa elemen lain di dalamnya.
- **Gambar:** harus pada `<img>`, dan `src` menunjuk ke file di `assets/`.
- **Tautan:** harus pada `<a>`.
- **Tanggal dan jam:** wajib punya `data-inv-value` dengan format `YYYY-MM-DD` atau `HH:MM`, kecuali teks tampilannya sudah dalam format itu.

Elemen yang field-nya kosong otomatis tidak ditampilkan.

## Section dan field yang didukung

| Section | Field | Berulang |
|---|---|---|
| `cover` | `arabic_text`, `title`, `subtitle`, `date_text`, `photo`, `background_image`, `button_label` | |
| `greeting` | `emblem`, `opening`, `message`, `names` | |
| `couple` | `heading`, `opening`, `intro` | `people`: `name`, `role`, `parents`, `description`, `photo`, `instagram`, `tiktok`, `facebook`, `x`, `youtube` |
| `event` | `heading` | `events`: `title`, `date`, `start_time`, `end_time`, `venue`, `address`, `description`, `notice`, `maps_url` |
| `closing` | `heading`, `photo`, `message`, `sign_off`, `signature`, `inviters` | |

Field teks atau gambar yang tidak ditandai dilaporkan sebagai peringatan dan tidak tampil di undangan. Pengaturan seperti posisi foto, bingkai, bentuk foto, dan warna diatur dari builder, bukan dari HTML.

Section lain seperti RSVP dan Galeri belum bisa ditulis di HTML. Tambahkan lewat builder, dan section itu memakai tampilan classic.

## Warna dan font

Agar pengaturan di builder tetap berlaku, pakai variabel tema di `style.css`:

| Variabel | Isi |
|---|---|
| `var(--inv-primary)` | Warna utama |
| `var(--inv-secondary)` | Warna kedua |
| `var(--inv-bg)` | Latar halaman |
| `var(--inv-surface)` | Latar kartu |
| `var(--inv-text)` | Warna teks |
| `var(--inv-on-primary)` | Teks di atas warna utama |
| `var(--inv-accent-text)` | Warna kedua yang tetap terbaca di atas latar |
| `var(--inv-font-heading)` | Font judul |
| `var(--inv-font-body)` | Font isi |
| `var(--inv-radius)` | Lengkungan sudut |

CSS otomatis dibatasi hanya berlaku di desain itu. Selector `body`, `html`, dan `:root` mengarah ke halaman undangan.

## Aturan style.css

- At-rule yang diizinkan: `@media`, `@supports`, `@container`, `@keyframes`, `@font-face`, dan `@charset`.
- `@import` dan at-rule lain ditolak.
- Aturan CSS bersarang (nesting) tidak didukung.
- Semua `url()` harus menunjuk ke file di `assets/`.
- Nilai `expression(`, `behavior:`, `-moz-binding`, dan `javascript:` ditolak.
- Setiap blok `{` harus ditutup dengan `}`.

## Yang ditolak di HTML

- Tag `script`, `style`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `textarea`, `select`, `base`, `svg`, `math`, `template`, `link`, dan `meta`.
- Semua atribut berawalan `on`, seperti `onclick`, serta `srcdoc`, `srcset`, dan `formaction`.
- Atribut `style` yang berisi `url(`, `expression(`, `javascript:`, atau `@import`. Pindahkan ke `style.css`.
- `src` yang bukan file dari paket.
- `href` selain file paket, `https://`, `http://`, `mailto:`, `tel:`, atau `#`.

JavaScript sengaja tidak diizinkan. Efek disediakan aplikasi lewat `data-inv-effect`.

## Contoh

```html
<section id="cover" class="cover">
  <p data-inv-field="subtitle">The Wedding of</p>
  <h1 data-inv-field="title" data-inv-effect="animate">Raka &amp; Sinta</h1>
  <img data-inv-field="photo" src="assets/cover.webp" alt="">
  <p>Kepada <inv-guest-name></inv-guest-name></p>
  <a href="#" data-inv-effect="next" data-inv-field="button_label">Buka Undangan</a>
</section>

<section id="event">
  <h2 data-inv-field="heading">Acara</h2>
  <div data-inv-repeat="events" class="card">
    <h3 data-inv-field="title">Akad Nikah</h3>
    <time data-inv-field="date" data-inv-value="2027-02-14">14 Februari 2027</time>
    <span data-inv-field="start_time" data-inv-value="08:00">08.00</span>
    <p data-inv-field="venue">Masjid Agung</p>
    <a data-inv-field="maps_url" href="https://maps.google.com/">Lihat lokasi</a>
  </div>
</section>
```

## Batasan

- Pilihan layout section di builder (misalnya Kartu dan Daftar) tidak berlaku pada section dari HTML.
- Paket desain tersimpan di server tempat diimpor. Saat memakai ekspor template `.zip` ke server lain, impor ulang paket HTML-nya dulu.
