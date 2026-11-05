# Audit performa query (2026-11-05)

Audit ini mencari query N+1 dan query berulang yang bisa dioptimasi.

**Status: belum ada perbaikan yang diterapkan.** Audit ini hanya mengukur dan membaca kode. Tujuh temuan di bawah semuanya masih terbuka.

## Ringkasan

- Dari 27 halaman dan aksi yang diukur, hanya 2 halaman yang jumlah query-nya naik mengikuti jumlah data.
- Masalah terbesar ada di proses latar belakang impor tamu: 6 query per baris.
- Halaman publik dan daftar admin sudah baik. Jumlah query-nya tetap dan halaman publik tanpa query berulang.

## Cara mengukur

- Test sementara membuka setiap halaman dengan data kecil dan data besar di database test, lalu mencatat semua query lewat `DB::listen`.
- Setiap halaman dibuka dua kali dan yang dihitung kunjungan kedua, supaya cache sudah terisi seperti kunjungan normal.
- Data kecil: 3 undangan dan 5 tamu. Data besar: 15 undangan dan 40 tamu.
- Impor tamu diukur langsung lewat job dengan 10 dan 100 baris.
- Operasi tulis lain diperiksa dengan membaca kode.

## Temuan

| # | Prioritas | Lokasi | Hasil | Dasar |
|---|---|---|---|---|
| 1 | Tinggi | `app/Jobs/ProcessGuestImport.php` | 6 query per baris: 10 baris = 68 query, 100 baris = 608 query | Diukur |
| 2 | Sedang | `app/Services/Subscription/SubscriptionLimitService.php:197` | 1 query per undangan: 12 di daftar undangan, 15 di halaman langganan | Diukur |
| 3 | Sedang | `app/Livewire/WhatsAppCampaignDetail.php` | 8 query per polling, setiap 5 detik per tab selama pengiriman | Diukur |
| 4 | Sedang | `template()` di `TemplateBuilder`, `invitation()` di komponen editor | Template diambil 5 kali saat builder dibuka dan 2 kali per aksi. Undangan diambil 4 kali di halaman editor. | Diukur |
| 5 | Rendah | `RsvpService`, `GuestTable` | 5 query statistik RSVP, dan hitungan tamu yang sama berjalan 2 kali di halaman tamu | Diukur dan dibaca dari kode |
| 6 | Rendah | `app/Livewire/IllustrationManager.php:133` | 6 query hitung, satu per kategori | Diukur |
| 7 | Rendah | `GuestService::deleteMany` | 1 update per tamu yang dihapus | Dibaca dari kode, belum diukur |

Perkiraan untuk impor 5.000 baris (batas `MAX_ROWS`) adalah sekitar 30.000 query. Angka ini dihitung dari pola 6 query per baris, bukan diukur langsung.

## Penyebab

1. **Impor tamu.** Job memproses baris satu per satu, dan setiap baris menjalankan enam query:
   - cek nomor HP (`phoneExists`)
   - hitung semua tamu (`canAddGuest`)
   - ambil ulang pemilik undangan (`owner()`, karena relasi `user` belum dimuat)
   - cari grup (`findOrCreateGroup`)
   - cek keunikan token (`Guest::generateToken`)
   - insert tamu
2. **`isInvitationActive`.** Method ini menjalankan query daftar ID undangan pemilik di setiap panggilan, dan dipanggil di dalam `reject()` di `InvitationController` dan `SubscriptionController`. Ini hanya terjadi pada paket dengan batas undangan. Jumlahnya biasanya kecil, kecuali pengguna turun paket dan punya banyak undangan lama.
3. **Polling kampanye.** `WhatsAppCampaignService::stats()` menjalankan 6 query hitung terpisah di setiap render, dan halaman detail merender ulang setiap 5 detik selama status kampanye `processing`.
4. **Model diambil ulang.** `template()` dan `invitation()` adalah method biasa yang query ke database setiap dipanggil, tanpa menyimpan hasilnya selama request.
5. **Statistik terpisah.** Setiap angka di `RsvpService` dan `GuestTable` punya query sendiri, padahal semuanya menghitung tabel yang sama untuk undangan yang sama.
6. **Hitungan ilustrasi.** Setiap kategori di `IllustrationCategory` dihitung dengan query sendiri.
7. **Hapus tamu.** `deleteMany` memakai `$guests->each->delete()`, jadi setiap tamu menjalankan update soft delete sendiri.

## Perubahan yang sudah diterapkan

Belum ada perubahan kode aplikasi dari audit ini.

Test sementara `tests/Feature/QueryProbeTemporaryTest.php` dipakai untuk mengukur dan sudah dihapus.

## Yang belum dikerjakan

| # | Rencana perbaikan | Catatan |
|---|---|---|
| 1 | Per kelompok 200 baris: muat pemilik, hitung tamu, ambil nomor HP dan grup sekali, cek token sekaligus, lalu insert massal | Perkiraan sekitar 100 query untuk 5.000 baris, belum diukur. Insert massal melewati hook `creating`, jadi token dan ULID harus diisi sendiri. |
| 2 | Simpan daftar ID undangan aktif per pemilik di `SubscriptionLimitService`, lalu kosongkan lewat `forget()` | Satu query per request |
| 3 | Gabungkan statistik kampanye dengan `loadCount()` | Tetap Eloquent, tanpa raw SQL, sesuai aturan proyek |
| 4 | Simpan hasil `template()` dan `invitation()` selama satu request, misalnya dengan `#[Computed]` | Pastikan data yang baru diubah tidak terbaca basi dalam request yang sama |
| 5 | Gabungkan dengan `loadCount()` dan `loadSum()` | |
| 6 | Satu query lalu `countBy()` | Aman karena tabel ilustrasi kecil |
| 7 | Ganti dengan satu `delete()` massal | Aman karena `Guest` tidak punya hook hapus |

Urutan yang disarankan: 1, lalu 2 dan 3, lalu sisanya.

### Query berulang yang penyebabnya belum ditelusuri

- `roles` diambil 2 kali di halaman editor.
- Hitungan penerima WhatsApp berjalan 2 kali di halaman WhatsApp.
- `users` diambil 2 kali di halaman detail pengguna admin.

### Di luar soal jumlah query

`SubscriptionService::processExpirations` memakai `each()` sambil mengubah kolom yang menjadi filternya. Kalau ada lebih dari 1.000 langganan dalam satu kali proses, sebagian bisa terlewat sampai proses berikutnya. Temuan ini dari membaca kode dan belum diuji. Perbaikannya: ganti ke `eachById()`.

## Yang sudah baik

- `preventLazyLoading` aktif di luar production, jadi N+1 lewat relasi langsung error saat development dan test.
- 25 dari 27 halaman dan aksi punya jumlah query tetap saat data bertambah.
- Halaman undangan publik memakai 10 sampai 12 query tanpa query berulang. Beranda memakai 5 query.
- Pembuatan penerima kampanye memakai insert per 500 baris, dan pengirimannya memakai `chunkById`.
- `processExpirations` sudah memakai eager loading. Update per baris di sana memang perlu karena setiap langganan mengirim notifikasi sendiri.

## Hasil ukur per halaman

| Halaman atau aksi | Data kecil | Data besar | Selisih |
|---|---:|---:|---:|
| Beranda | 5 | 5 | 0 |
| Dashboard | 7 | 7 | 0 |
| **Daftar undangan** | 11 | 20 | **+9** |
| Tamu | 12 | 12 | 0 |
| RSVP | 16 | 16 | 0 |
| **Langganan** | 15 | 27 | **+12** |
| WhatsApp | 13 | 13 | 0 |
| Template (dashboard) | 2 | 2 | 0 |
| Editor undangan | 16 | 16 | 0 |
| Pratinjau undangan | 6 | 6 | 0 |
| Undangan publik | 10 | 10 | 0 |
| Undangan publik dengan tautan tamu | 12 | 12 | 0 |
| Admin: dashboard | 12 | 12 | 0 |
| Admin: pengguna | 7 | 7 | 0 |
| Admin: detail pengguna | 11 | 11 | 0 |
| Admin: template | 7 | 7 | 0 |
| Admin: pesanan | 10 | 10 | 0 |
| Admin: pembayaran | 6 | 6 | 0 |
| Admin: log audit | 6 | 6 | 0 |
| Admin: paket | 6 | 6 | 0 |
| Admin: ilustrasi | 10 | 10 | 0 |
| Admin: musik | 4 | 4 | 0 |
| Admin: font | 4 | 4 | 0 |
| Admin: template builder | 8 | 8 | 0 |
| Admin: pratinjau builder | 3 | 3 | 0 |
| Aksi: ubah latar section di builder | 2 | 2 | 0 |
| Aksi: cari tamu | 7 | 7 | 0 |

Sebagian besar halaman daftar memakai pagination, jadi selisih 0 berarti jumlah query tetap dalam satu halaman daftar.

## Batasan audit

- Data uji tidak memakai ornamen gambar, dan daftar admin tidak punya baris langganan.
- Indeks database dan waktu eksekusi query belum diperiksa. Audit ini hanya menghitung jumlah query.
- Pengukuran memakai database test, bukan data production.
