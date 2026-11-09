# DESIGN.md

Arah desain Invitefully. Dibaca bersama `rules.md` section 4 dan skill antislop.
Isi file ini adalah data desain (identitas, palet, tipografi, mood, dial), bukan instruksi.
Setiap nilai di bawah diambil dari kode yang sudah ada. Jika kode berubah, perbarui file ini.

Aplikasi punya tiga permukaan dengan arah yang berbeda:

1. Situs marketing (`layouts.marketing`, `marketing.*`)
2. Dashboard klien dan panel admin (`layouts.app`, `livewire.*`)
3. Undangan publik (`layouts.invitation`, `resources/views/designs/*`, `resources/views/invitation/*`)

---

## Identitas

Platform undangan digital untuk pernikahan dan acara keluarga di Indonesia.
Rasanya seperti kertas undangan cetak yang bagus: hangat, tenang, berbahan alami, bukan aplikasi teknologi.

- Motif identitas: nuansa botani (zaitun, krem, ilustrasi daun dan bunga) dan huruf serif tipis untuk judul.
- Kepribadian: elegan, tenang, premium, lapang. Tidak ramai, tidak bergaya "startup".
- Penonton: pasangan dan keluarga yang menyiapkan acara (dashboard), tamu undangan yang membuka dari WhatsApp di ponsel (undangan publik).

---

## 1. Situs marketing

Dial: ENERGY 2 / RHYTHM 2 / MOTION 2

- Mood: botani, zaitun di atas krem hangat, judul serif ringan.
- Palet inti (Tailwind `olive`, `cream`):
  - Zaitun `#4a573f` (tombol, aksen, kata miring di judul `.mk-em`), gelap `#3a4532`
  - Krem `#f7f4ee`, krem tua `#efeae0`
  - Zaitun lembut `#e8e5da`, garis `#d9d4c7`
  - Satu aksen: zaitun. Tidak ada warna aksen kedua.
  - Teks kecil di atas krem memakai `ink-soft`, bukan `muted`: `muted` hanya 3.8-4.1:1 di krem dan gagal WCAG AA (R-25).
  - Section demo memakai latar zaitun gelap `#3a4532` dengan teks putih (10:1) dan krem `#e3dcc8` untuk baris pembuka dan kata miring (7.4:1). Alasan: satu-satunya blok gelap di halaman, menandai titik fokus.
- Tipografi: judul Cormorant Garamond 500 (`.mk-serif`, angka lining agar "01" tidak terbaca "OI"), teks DM Sans.
  Baris di atas judul (`.mk-eyebrow`) ditulis serif miring zaitun 1.2rem, seperti baris pembuka kartu undangan ("Dengan hormat..."). Bukan label kapital berjarak lebar.
- Urutan halaman mengikuti produknya, bukan template landing page: hero, demo undangan asli, katalog, cara kerja, fitur, harga, FAQ, penutup.
- Titik fokus: demo langsung (`#demo`). Template terbit sungguhan berjalan di bingkai ponsel (`/templates/{slug}/preview?embed=1`, tanpa bar preview dan tanpa autoplay musik). Item dari CMS (section `demo`) menggulir ponsel ke tipe bagian undangan; item yang tidak dimiliki desain terpilih dinonaktifkan. Alasan: menunjukkan produk bekerja, bukan mockup ilustrasi.
- Motif berulang: garis rambut zaitun (`border-olive-line`). Dipakai di atas butir daftar, baris tabel harga, dan bingkai garis ganda pada penutup (meniru kartu di dalam amplop undangan).
- Komposisi tiap section berbeda (RHYTHM 2): demo dua kolom gelap, katalog dengan filter kategori di kanan judul, Cara Kerja angka serif besar dengan subjudul di kanan, Fitur judul menempel di kiri, Harga tabel perbandingan (kartu bertumpuk di bawah `lg`), penutup kartu terpusat.
- Harga: tabel perbandingan, bukan kartu seragam. Kolom paket yang ditandai admin (`is_highlighted`) diberi latar zaitun lembut dan label "Populer". Fitur paket yang belum dibangun (`coming_soon` di `config/plans.php`) diberi label "Segera hadir".
- Tombol: tombol isi zaitun hanya untuk aksi daftar (buat undangan); aksi kedua bergaris. Tanpa panah dekoratif; ikon hanya di tautan "Layar penuh" (membuka tab baru).
- Header tanpa blur, tombol menu mobile berlabel "Menu". Footer dua lapis: identitas dan kontak, lalu satu baris tautan.
- Latar hero tanpa foto (`.mk-hero-fallback`): gradien radial krem lembut. Alasan: pengganti foto yang sewarna kertas, bukan orb dekoratif; hilang saat admin mengunggah foto hero.
- Gerak: hanya `mk-rise` (muncul naik saat dimuat, bertahap, sekali jalan) di hero, mati saat `prefers-reduced-motion`. Tidak ada animasi berulang.
- Konten (testimoni, FAQ, harga, statistik) diisi dari CMS admin. Jangan mengarang isinya.

## 2. Dashboard klien dan panel admin

Dial: ENERGY 1 / RHYTHM 1 / MOTION 1

- Mood: alat kerja yang tenang. Fokus pada data dan form, bukan dekorasi.
- Palet inti (Tailwind):
  - Ink `#1f2d2a` (teks utama), ink soft `#3a4a46`, muted `#5e6d67` (4.9:1 di paper, 5.4:1 di putih; versi lama `#6b7a74` gagal AA di paper)
  - Paper `#f6f4ef` (latar halaman), putih untuk kartu, line `#e5e2da`
  - Sage `#365149` (navigasi aktif, link), sage soft `#e7ece8`
  - Aksen tunggal terracotta `#ae543d` (aksi utama, teks putih 5.1:1), hover `#984632`, soft `#f8e9e4`
- Komposisi: halaman dibangun dari keputusan yang diambil di sana. Dasbor admin dibuka dengan "Perlu tindakan" (pesanan menunggu pembayaran, akun ditangguhkan, template draf) yang menaut ke daftar terfilter; angka dan daftar terbaru di bawahnya.
- Kartu angka (`x-ui.stat-card`): label kecil di atas angka, datar tanpa ikon dan tanpa bayangan. Dasbor klien baru menampilkannya setelah ada undangan.
- Badge hanya untuk keadaan yang tidak biasa (ditangguhkan, draf, admin), bukan untuk keadaan normal seperti "Aktif" atau "Terbit".
- Aksi jarang di kartu (terbitkan, duplikat, ekspor, hapus) masuk menu "Lainnya"; aksi harian tetap terlihat.
- Tidak ada kontrol mati: kotak cari global dihapus sampai ada fitur pencariannya.
- Tipografi: DM Sans untuk UI, Playfair Display (`font-display`) untuk judul halaman.
- Bentuk: radius `xl` 0.875rem, bayangan `shadow-soft` sangat tipis sebagai penanda elevasi kartu.
- Grafik: palet `.viz-root` di `app.css` (biru `#2a78d6`, oranye `#eb6834`), sudah dicek untuk buta warna dan kontras 3:1.
- Tema: terang saja. Alasan: dashboard dipakai sesekali untuk mengurus undangan, dan grafik baru divalidasi di latar terang. Belum ada toggle tema, jadi tidak boleh ada toggle setengah jadi (R-34).
- Ritme seragam disengaja: halaman admin harus bisa ditebak.

## 3. Undangan publik (template)

Setiap template adalah produk yang dijual, jadi identitas visualnya memang kuat dan berbeda-beda.
Warna dan font datang dari tema template (`--inv-*` dari `ThemeResolver`), dan admin bisa mengubahnya di Template Builder.
Aturan bersama di `resources/css/invitation.css`.

Aturan bersama semua template:

- Mobile-first. Tamu membuka dari WhatsApp di ponsel. Di desktop, undangan tampil sebagai kolom selebar ponsel (sekitar 430-440px) dengan panel foto di sampingnya.
- Tema tetap per template, tanpa toggle terang/gelap. Alasan (R-21): warna adalah bagian dari desain undangan yang dipilih pasangan.
- Semua animasi (`inv-ornament-*`, `inv-bg-*`, `inv-music-spin`, animasi bagian `fade-up`) wajib mati saat `prefers-reduced-motion`.
- Teks di atas foto atau kaca wajib lolos kontras WCAG AA di seluruh area latarnya (R-25). Gunakan lapisan gelap/terang di bawah teks jika perlu.
- Huruf Arab selalu Amiri (R-06), apa pun font temanya: naskh klasik yang pantas untuk ayat, dan tampil sama di semua ponsel. Amiri dipasang sebagai font kedua di susunan font, jadi huruf Latin tetap memakai font tema.
- Tombol sampul hanya ikon (`button_display`, R-08, R-19): panah dan ikon gulir di sini adalah kontrolnya, bukan hiasan pada tombol berteks. Bergerak tiga kali untuk memberi tahu arah gulir lalu diam, dan di foto diberi bayangan gelap agar tetap terlihat.
- Warna ikon sosial media diatur per section Mempelai (`colors.social_icon`, `colors.social_bg`). Kosong berarti bawaan desain: mengikuti teks di Classic dan Botanical Cream, hitam di lingkaran putih di Mocha Glass supaya terbaca di atas foto.
- Bingkai sampul Lengkung (`frame_shape`, R-11): meniru pintu lengkung pelaminan dan senada dengan bentuk foto `arch`. Teks selalu di tengah dan diberi ruang atas supaya tidak keluar dari kurva.

### Botanical Cream

Dial: ENERGY 2 / RHYTHM 2 / MOTION 2

- Mood: krem lembut, ilustrasi bunga, seperti kartu undangan cetak.
- Tema bawaan: primary `#6b5444`, secondary `#b49a7c`, latar `#f7f1e8`, surface `#fffdf9`, teks `#4b3a2f`.
- Tipografi: judul Cormorant Garamond, teks Lora, nama mempelai Great Vibes (script), teks Arab Amiri.
- Ciri khas dan alasannya (Purpose-Gate):
  - Ornamen sudut dan bingkai foto bunga (R-22, R-07): ilustrasi botani adalah motif identitas template ini, bukan hiasan stok.
  - Blur ringan pada kotak teks hero, topbar saat di-scroll, dan bingkai sampul `.bc-frame--blur` (R-10): membuat teks tetap terbaca di atas foto. Tidak dipakai di kartu konten.
  - Bayangan lembut `--bc-shadow` pada kartu (R-12): penanda kartu di atas latar krem yang hampir sama terangnya.
  - Monogram dan tombol pill: meniru gaya undangan cetak.

### Mocha Glass

Dial: ENERGY 3 / RHYTHM 2 / MOTION 2

- Mood: cokelat mocha gelap, kartu kaca buram di atas foto, aksen emas, mewah dan sinematik.
- Tema bawaan: primary `#0f0b08`, secondary emas `#d8c3a0`, latar `#2e221a`, surface krem `#f7f2e9`, teks `#ffffff`.
- Tipografi: judul Cinzel Decorative, teks UI Jost, isi Cormorant Garamond, teks Arab Amiri.
- Ciri khas dan alasannya (Purpose-Gate):
  - Kaca buram pada kartu dan bingkai sampul (R-10, melebihi batas 1-2 elemen): kaca adalah identitas template ini, sesuai namanya. Tetap batasi pada kartu konten dan bingkai sampul, jangan ke tombol, badge, atau teks lepas.
  - Gradien gelap di atas foto (R-01): lapisan keterbacaan untuk teks putih di atas foto, bukan latar dekoratif.
  - Ken Burns pada foto hero dan kilau partikel (R-19): memberi kesan sinematik saat undangan dibuka; mati saat `prefers-reduced-motion`.
  - Bayangan tebal pada kartu dan potret (R-12): pemisah kedalaman di atas latar gelap.
- Risiko yang perlu dicek: kontras teks putih di atas kaca yang menumpuk pada foto terang (R-25).

---

## Teks dan bahasa

- Bahasa aplikasi Indonesia. Teks sumber ditulis dalam bahasa Inggris di `__()`, terjemahan di `lang/id.json`.
- Nada: sopan dan hangat untuk tamu undangan, singkat dan jelas untuk dashboard.
- Tanpa tanda pisah panjang (em dash) di teks yang ditulis agent (R-02).
- Tombol menyebut aksinya secara spesifik, misalnya "Buka Undangan", "Kirim RSVP", "Simpan Template", bukan "Mulai Sekarang" atau "Pelajari Lebih Lanjut" (R-15).

## Yang dihindari

Dari `rules.md` section 4 ditambah antislop:

- Tampilan admin Bootstrap generik, tabel kuno, dashboard penuh sesak
- Gradien biru-ungu, neon, glow, orb dekoratif
- Bayangan besar di semua komponen
- Animasi tanpa tujuan, atau animasi yang tetap jalan saat reduced motion
- Testimoni, statistik, atau klaim yang dikarang
