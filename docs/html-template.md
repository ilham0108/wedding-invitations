# Impor template dari HTML

Super admin bisa mengimpor desain buatan sendiri di **Admin → Template → Impor HTML**. Paketnya diperiksa dulu, dan tidak ada yang disimpan sampai semua pemeriksaan lolos. Hasilnya berupa template draf yang bisa diatur di builder.

Contoh paket lengkap ada di [`docs/contoh-template-html/`](contoh-template-html/). Zip isi folder itu, lalu unggah.

## Struktur zip

```
desain-saya.zip
├── index.html   wajib, di tingkat teratas zip (bukan di dalam folder)
├── style.css    wajib
└── assets/      gambar (jpg, png, webp) dan font (woff, woff2)
```

Batas ukuran: 12 MB per zip. File jenis lain ditolak, termasuk SVG.

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

Isi field yang ditandai menjadi **Contoh konten** template. Teks umum dan gambar desain juga menjadi **Konten bawaan**, sehingga muncul di undangan baru: label tombol dan foto sampul; ornamen, kalimat pembuka, dan kalimat sambutan; judul section; serta pesan dan salam penutup. Data pribadi seperti nama, tanggal, tempat, dan daftar orang tetap hanya menjadi contoh. Subjudul sampul juga menjadi konten bawaan template, tetapi pada undangan baru selalu diisi dari jenis acara yang dipilih klien.

Cara menandai sesuai jenis field:
- **Teks:** elemen apa pun, isinya hanya teks.
- **Gambar:** harus pada `<img>`.
- **Tautan:** harus pada `<a>`.

Elemen yang field-nya kosong otomatis tidak ditampilkan.

## Section dan field yang didukung

| Section | Field | Berulang |
|---|---|---|
| `cover` | `title`, `subtitle`, `date_text`, `background_image`, `button_label` | |
| `greeting` | `emblem`, `opening`, `message`, `names` | |
| `couple` | `heading`, `opening`, `intro` | `people`: `name`, `role`, `parents`, `description`, `photo` |
| `event` | `heading` | `events`: `title`, `date`, `start_time`, `end_time`, `venue`, `address`, `description`, `notice`, `maps_url` |
| `closing` | `heading`, `photo`, `message`, `sign_off`, `signature`, `inviters` | |

Section lain seperti RSVP dan Galeri belum bisa ditulis di HTML. Tambahkan lewat builder, dan section itu memakai tampilan classic.

## Warna dan font

Agar pengaturan di builder tetap berlaku, pakai variabel tema di `style.css`:

`var(--inv-primary)`, `var(--inv-secondary)`, `var(--inv-bg)`, `var(--inv-surface)`, `var(--inv-text)`, `var(--inv-font-heading)`, `var(--inv-font-body)`, `var(--inv-radius)`.

CSS otomatis dibatasi hanya berlaku di desain itu. Selector `body`, `html`, dan `:root` mengarah ke halaman undangan.

## Yang ditolak

- `<script>`, `<iframe>`, `<form>`, `<style>`, `<svg>`, dan tag berbahaya lain.
- Atribut seperti `onclick`.
- Gambar atau file dari luar paket, dan `@import` di CSS. Semua `url()` harus menunjuk ke `assets/`.
- Aturan CSS bersarang (nesting).

JavaScript sengaja tidak diizinkan. Efek disediakan aplikasi lewat `data-inv-effect`.

## Batasan

- Pilihan layout section di builder (misalnya Kartu dan Daftar) tidak berlaku pada section dari HTML.
- Paket desain tersimpan di server tempat diimpor. Saat memakai ekspor template `.zip` ke server lain, impor ulang paket HTML-nya dulu.
