# KasirKu

KasirKu adalah aplikasi kasir dan manajemen toko berbasis web untuk UMKM Indonesia. Aplikasi ini menyediakan alur transaksi yang cepat untuk staf kasir serta dashboard, stok, dan laporan untuk pemilik toko.

Antarmuka KasirKu responsif untuk ponsel, tablet, dan desktop, termasuk mode keranjang berbentuk drawer pada perangkat yang lebih kecil.

## Fitur utama

- Point of Sale dengan pencarian dan filter kategori produk.
- Keranjang belanja, pilihan nominal bayar, perhitungan kembalian, dan catatan transaksi.
- Pembuatan serta pencetakan struk.
- Pengurangan stok secara atomik saat transaksi berhasil.
- Manajemen produk, kategori, harga, stok, dan satuan.
- Riwayat transaksi dengan pencarian, filter tanggal, detail, dan cetak ulang struk.
- Dashboard pendapatan, jumlah transaksi, produk, dan stok menipis.
- Laporan pendapatan bulanan dan tahunan dengan grafik, ekspor CSV, dan cetak.
- Hak akses terpisah untuk `owner` dan `staff`.
- Penggantian password melalui halaman pengaturan.
- Proteksi CSRF, prepared statement, validasi role, dan session hardening.
- Navigasi keyboard, fokus dialog, reduced-motion, dan target sentuh yang ramah perangkat mobile.

## Hak akses

| Fitur | Owner | Staff |
| --- | :---: | :---: |
| Dashboard | Ya | Tidak |
| Point of Sale | Ya | Ya |
| Riwayat transaksi | Ya | Ya |
| Stok dan produk | Ya | Tidak |
| Laporan pendapatan | Ya | Tidak |
| Pengaturan akun | Ya | Ya |

## Teknologi

- PHP 8.1 atau lebih baru
- MySQL atau MariaDB
- HTML, CSS, dan JavaScript tanpa framework
- PDO MySQL
- Chart.js untuk grafik dashboard dan laporan
- Apache/XAMPP sebagai lingkungan lokal yang direkomendasikan

## Instalasi dengan XAMPP

### 1. Clone repository

Clone project ke dalam folder `htdocs`:

```bash
git clone https://github.com/chogusss/kasirku.git C:/xampp/htdocs/kasirku
```

### 2. Siapkan database

1. Jalankan Apache dan MySQL dari XAMPP Control Panel.
2. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
3. Buat database bernama `kasir_umkm` dengan charset `utf8mb4`.
4. Pilih database tersebut, lalu import [database/if0_42279288_kasir_umkm.sql](database/if0_42279288_kasir_umkm.sql).

Script SQL akan membuat tabel, kategori, produk contoh, akun awal, dan data transaksi demo.

### 3. Jalankan aplikasi

Buka alamat berikut di browser:

```text
http://localhost/kasirku
```

## Akun awal

| Peran | Username | Password |
| --- | --- | --- |
| Owner | `admin` | `admin123` |
| Staff | `staff` | `staff123` |

> Segera ganti password akun awal dari menu Pengaturan sebelum aplikasi digunakan dengan data nyata.

## Konfigurasi

Konfigurasi utama berada di [config/db.php](config/db.php). Nilai bawaan cocok untuk instalasi XAMPP standar dan dapat diganti melalui environment variable berikut:

| Variable | Nilai bawaan | Keterangan |
| --- | --- | --- |
| `KASIRKU_DB_HOST` | `localhost` | Host database |
| `KASIRKU_DB_NAME` | `kasir_umkm` | Nama database |
| `KASIRKU_DB_USER` | `root` | Username database |
| `KASIRKU_DB_PASS` | kosong | Password database |
| `KASIRKU_TZ` | `Asia/Jakarta` | Zona waktu aplikasi |

Nama toko, alamat, dan nomor telepon pada struk juga dapat disesuaikan melalui konstanta di `config/db.php`.

## Struktur project

```text
kasirku/
├── api/          Endpoint autentikasi, produk, transaksi, dan laporan
├── assets/       CSS, JavaScript, dan stylesheet cetak
├── config/       Konfigurasi database dan helper API
├── database/     Skema serta data contoh MySQL
├── includes/     Bootstrap, autentikasi, header, dan footer bersama
├── pages/        Dashboard, POS, riwayat, stok, laporan, dan pengaturan
├── tests/        HTTP, browser, dan responsive smoke tests
├── index.php     Entry point aplikasi
├── login.php     Halaman autentikasi
├── logout.php    Proses logout berbasis POST
└── setup.php     Helper lokal untuk mereset akun awal
```

## Menjalankan pengujian

### HTTP smoke test

Pastikan Apache dan MySQL aktif, kemudian jalankan:

```bash
php tests/http_smoke.php http://127.0.0.1/kasirku
```

Test ini memeriksa autentikasi, otorisasi role, produk, transaksi atomik, stok, laporan, CSRF, penggantian password, dan logout. Data sementara yang dibuat oleh test akan dibersihkan kembali.

### Browser smoke test

Browser test menggunakan Chrome DevTools Protocol. Jalankan Chrome headless pada port debugging, lalu jalankan test dengan Node.js:

```powershell
& 'C:\Program Files\Google\Chrome\Application\chrome.exe' `
  --headless=new `
  --remote-debugging-port=9223 `
  --user-data-dir="$env:TEMP\kasirku-browser-test"

node tests/browser_smoke.mjs http://127.0.0.1:9223 http://127.0.0.1/kasirku
```

### Responsive probe

Setelah Chrome dijalankan dengan remote debugging pada port `9225`:

```bash
node tests/responsive_probe.mjs http://127.0.0.1:9225 http://127.0.0.1/kasirku
```

Probe memeriksa seluruh halaman pada viewport 320–1440 piksel serta drawer POS pada layar pendek.

## Catatan keamanan produksi

- Ganti seluruh password akun awal.
- Gunakan kredensial database khusus melalui environment variable.
- Aktifkan HTTPS dan cookie secure di server produksi.
- Hapus atau blokir akses ke `setup.php` setelah proses setup selesai.
- Jangan menyimpan file `.env`, private key, dump data pelanggan, atau kredensial produksi di repository.
- Folder `tests` telah dilindungi melalui `.htaccess` untuk instalasi Apache.

## Responsivitas

KasirKu telah diuji pada lebar viewport 320, 360, 390, 600, 768, 820, 1024, 1100, dan 1440 piksel. Tabel berubah menjadi kartu pada ponsel, sidebar menjadi off-canvas pada tablet, dan keranjang POS tetap dapat digunakan pada layar dengan tinggi terbatas.
