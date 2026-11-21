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
- Pengaturan section (builder dan editor klien) dibagi menjadi kelompok yang bisa dibuka-tutup (`config('invitation.field_groups')`, `livewire.partials.section-fields`). Alasan: sampul punya lebih dari 40 field. Kelompok pertama terbuka, kelompok tanpa field yang berlaku tidak tampil, dan sakelar yang berurutan disusun dua kolom.
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
- Foto sampul (`photo`, `invitation.partials.cover-photo`): foto pasangan, di depan gambar latar. Bentuk dan bingkainya memakai pilihan yang sama dengan potret Mempelai (lingkaran, oval, lengkung, sudut bulat), supaya satu undangan konsisten. Dimuat langsung (bukan lazy) karena ada di layar pertama (R-40).
  - Posisi "Di atas nama" (bawaan): ikut kolom teks, lebar dibatasi 30% tinggi layar karena sampul Mocha Glass terkunci setinggi layar; nama, tanggal, dan tombol harus tetap muat di ponsel pendek.
  - Posisi "Bebas": titik tengah foto di `photo_x`/`photo_y` persen sampul, bisa digeser di pratinjau editor dan builder (`cover-photo-drag.js`), lebar sampai 60% tinggi layar. Lapisan "di belakang teks" (bawaan, z-index 0) menjaga nama tetap terbaca; "di depan teks" (z-index 20) untuk komposisi yang sengaja menumpuk.
  - Gaya (R-12, R-31): garis tepi dan bayangan bukan bawaan (0 px, tanpa bayangan), dipilih pemilik undangan untuk memisahkan foto dari latar yang ramai. Kemiringan -180 sampai 180 derajat, bisa diputar langsung lewat pegangan di pratinjau.
  - "Tanpa bentuk": foto utuh dengan proporsi aslinya, tanpa dipotong dan tanpa sudut membulat, untuk foto yang komposisinya sudah jadi.
  - Gradasi tepi (R-01): tepi atas, bawah, kiri, atau kanan memudar ke transparan (mask CSS); dua tepi yang bersebelahan memudarkan sudutnya. Alasan: foto menyatu dengan latar sampul, bukan dekorasi. Kepekatan foto 10 sampai 100%.
- Menu bagian melayang (`invitation.partials.nav`, `invitation-nav.js`), satu komponen untuk semua desain. Diatur per template di builder (tab Tema, "Gerak & navigasi"): tampil atau tidak, isi maksimal 5 item, label, dan ikon. Tanpa isi sendiri, item dipilih otomatis dari bagian yang ada.
  - Melayang, tidak memakan tinggi layar (R-12): satu bayangan menandai menu berada di atas konten. Disembunyikan selama sampul terlihat, supaya tidak menutupi tombol buka undangan dan nama tamu.
  - Setiap item menaut ke bagian yang benar-benar ada di halaman (R-24). Item yang bagiannya disembunyikan tidak ditampilkan, dan menu dengan kurang dari dua item tidak muncul.
  - Ikon Font Awesome dipilih dari daftar tetap yang sesuai isi bagian (R-04). Ikon disimpan sebagai nama, bukan class.
  - Gaya per desain: Classic dan desain HTML memakai warna tema (surface, item aktif primary). Mocha Glass tetap pil krem dengan ikon hitam, dan hanya item aktif yang menampilkan labelnya. Botanical Cream berupa pil krem berlabel, item aktif berwarna primary, tanpa blur.
- Tata letak judul sampul (`title_layout`, `invitation.partials.cover-title`): bawaan desain, satu baris, tiga baris bertumpuk, atau tiga baris bertingkat ("Raka / & / Sinta", tiap baris bergeser ke kanan seperti kartu undangan cetak). Nama dipisah di tanda "&", dan "&" dibuat lebih kecil supaya nama tetap jadi fokus. Judul tanpa "&" tampil apa adanya.
- Animasi "Singkap" (`reveal`, R-19): bagian tersingkap seperti tirai dibuka ke arah gerak tamu, dari bawah saat menggulir dan dari tepi kanan saat menggeser samping. Alasan: menandai pergantian bagian tanpa menggeser tata letak. Mati saat `prefers-reduced-motion`, sama seperti animasi lain.
- Galeri "Kolase" (`invitation.partials.gallery-collage`): foto sebagai cetakan polaroid berbingkai warna surface tema, pola berulang dua foto berdampingan lalu satu melintang di bawahnya, miring bergantian dan sedikit menumpuk. Alasan: meniru foto cetak yang ditata di meja, cocok dengan nuansa undangan. Bayangan menandai foto yang menumpuk di atas halaman dan foto lain (R-12). Kemiringan memakai properti `rotate` agar tidak bertabrakan dengan animasi masuk.
- Sapaan tamu di sampul (`invitation.partials.cover-guest`): "Kepada Yth. Bapak/Ibu/Saudara/i" lalu nama tamu, tepat di atas tombol buka undangan, mengikuti sisi tombol. Tanpa tautan pribadi, nama diganti "Tamu undangan". Di pengaturan sampul, sapaan bisa dimatikan, dan teks sapaan serta nama pengganti bisa diganti (kosong = teks bawaan).
- Petunjuk "Geser layar untuk melanjutkan" (mode geser samping): jika sampul punya tombol, petunjuk tidak tampil di sampul karena tombol sudah menjadi ajakan lanjut dan petunjuk akan menimpa sapaan tamu. Petunjuk muncul di halaman kedua dan hilang setelah tamu menggeser lagi. Nama tamu dibuat lebih besar karena itu yang dicari tamu. Desain HTML tanpa `<inv-guest-name>` mendapat blok ini otomatis di atas elemen `data-inv-effect="next"`.
- Kontrol ornamen di pratinjau builder (`builder-preview.js`): geser, ubah ukuran, putar lewat pegangan (Shift = kelipatan 15 derajat); klik memunculkan toolbar (putar 15 derajat, cermin, pindah lapisan, duplikat, hapus) dan tombol panah untuk geser presisi. Toolbar 44 px per tombol, ikon Font Awesome dengan label.
- Rotasi ornamen (`rotate`, -180 sampai 180 derajat, R-19 bukan animasi): satu ilustrasi bisa dipakai di sudut dan sisi berbeda tanpa admin membuat file baru. Berputar di titik tengah gambar, terpisah dari cermin (`flip`), jadi sudutnya terbaca sama di layar.

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
