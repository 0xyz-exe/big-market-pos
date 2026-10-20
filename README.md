# Big Market POS

Sistem point of sale (POS) dan manajemen operasional supermarket multi-cabang
berbasis Laravel. Aplikasi ini menggunakan antarmuka berbahasa Indonesia,
mata uang IDR, dan zona waktu `Asia/Jakarta`.

> **Status proyek:** Fitur inti autentikasi staf, isolasi cabang, katalog
> produk, penyesuaian stok, checkout tunai, struk, laporan penjualan, serta
> pencatatan dan persetujuan biaya operasional sudah tersedia. Fitur
> purchasing, shift kasir, refund, laporan keuangan yang lebih luas, dan
> pembayaran berbasis provider belum tersedia.

## Fitur

- Login dengan rate limiting, regenerasi sesi, logout invalidation, dan
  penolakan akun nonaktif.
- Setup interaktif untuk membuat Super Admin dan cabang pertama.
- Role, permission, dan pembatasan akses berbasis cabang.
- Katalog produk dengan pencarian, SKU/barcode unik, harga per cabang, dan
  minimum stok.
- Penyesuaian stok yang transaksional, aman terhadap race condition, dan
  mencegah stok negatif.
- Checkout POS tunai dengan penguncian stok, perhitungan harga/pajak dari
  server, idempotency protection, kembalian, serta struk yang dapat dicetak.
- Riwayat penjualan dan laporan harian dengan total transaksi, pajak, HPP,
  dan laba kotor.
- Pencatatan biaya operasional per cabang dengan alur
  pending/approved/rejected, alasan penolakan, dan pencegahan self-approval.
- Test suite SQLite serta test PostgreSQL untuk migrasi, row locking, dan
  checkout bersamaan.

## Teknologi

- PHP 8.3+
- Laravel 13
- Blade
- Tailwind CSS 4 dan Vite
- PostgreSQL 17 (database target)
- PHPUnit 12
- Node.js dan npm
- Docker Desktop (opsional, untuk PostgreSQL lokal)

Ekstensi PHP yang diperlukan antara lain `pdo_pgsql`, `fileinfo`, `zip`, dan
`bcmath`.

## Instalasi lokal

Instruksi berikut ditulis untuk Windows PowerShell.

### 1. Siapkan dependensi

Pastikan PHP, Composer, Node.js, npm, dan PostgreSQL tersedia:

```powershell
php -v
composer -V
node --version
npm --version
```

Dari direktori proyek, install dependensi:

```powershell
composer install
npm install
```

### 2. Konfigurasi environment

Salin file environment dan buat application key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` dan isi setidaknya nilai berikut:

```dotenv
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=big_market
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_strong_local_password
```

Jangan commit file `.env`.

### 3. Jalankan PostgreSQL dengan Docker (opsional)

Jika belum memiliki PostgreSQL lokal, isi `DB_DATABASE`, `DB_USERNAME`, dan
`DB_PASSWORD` di `.env`, lalu jalankan:

```powershell
docker compose up -d postgres
```

Container hanya bind ke `127.0.0.1:5432` dan menggunakan volume persisten
`big-market-pos-pgdata-v1`. Perintah berikut menghentikan container tanpa
menghapus data:

```powershell
docker compose down
```

Gunakan `docker compose down -v` hanya jika memang ingin menghapus database
lokal dan seluruh datanya.

### 4. Migrasi dan buat admin pertama

Pastikan database yang dipilih benar sebelum menjalankan migrasi:

```powershell
php artisan migrate --seed
php artisan app:bootstrap
```

Perintah `app:bootstrap` berjalan secara interaktif, meminta data Super Admin
dan cabang pertama, serta menolak dijalankan jika sudah ada user. Tidak ada
akun demo atau password default yang disediakan.

### 5. Build dan jalankan aplikasi

```powershell
npm run build
php artisan serve
```

Buka <http://localhost:8000> di browser. Untuk pengembangan frontend dengan
hot reload, gunakan `npm run dev` di terminal terpisah.

## Testing

Test suite default menggunakan SQLite. Jalankan:

```powershell
php artisan test
```

Test PostgreSQL yang mencakup row locking dan concurrent checkout harus
dijalankan terhadap database PostgreSQL yang kosong dan disposable karena
`RefreshDatabase` akan menerapkan migrasi serta mereset schema test.

Contoh menjalankan test tertentu:

```powershell
php artisan test --filter=SaleCheckoutTest
php artisan test --filter=InventoryAdjustmentTest
```

## Batasan saat ini

Fitur berikut belum diimplementasikan:

- Pengeditan/import produk dan UI administrasi kategori.
- Stock count, transfer stok, penerimaan barang, dan purchasing.
- Shift kasir, refund, supplier payment, dan payment gateway.
- Laporan keuangan yang memasukkan biaya operasional secara menyeluruh.
- Administrasi user/cabang, audit-log UI, dan upload bukti biaya.
- Immutability pada level database untuk seluruh data historis.

Metode pembayaran penjualan yang tersedia saat ini hanya **cash**. Biaya
operasional menyimpan metode pembayaran sebagai metadata dan belum melakukan
rekonsiliasi shift kasir atau memanggil gateway.

## Catatan keamanan dan deployment

- Gunakan password database yang kuat dan jangan commit `.env`.
- Jangan gunakan `migrate:fresh` pada database yang berisi data.
- `app:bootstrap` ditujukan untuk aplikasi baru dan tidak menyediakan
  kredensial bawaan.
- Konfigurasi PostgreSQL produksi, HTTPS, backup, queue worker, dan observability
  harus disiapkan sebelum deployment produksi.
- Verifikasi ulang konfigurasi database dan `APP_DEBUG=false` untuk environment
  production.

## Struktur proyek

```text
app/          Logika aplikasi, model, service, dan command
database/     Migration, factory, dan seeder
resources/    Blade views dan aset frontend
routes/       Definisi route web
tests/        Feature dan unit test
docs/         Catatan implementasi dan verifikasi
```

Detail checklist implementasi dan bukti verifikasi tersedia di
[`docs/implementation-plan.md`](docs/implementation-plan.md).

## Lisensi

Proyek ini menggunakan lisensi MIT sesuai konfigurasi Composer.
