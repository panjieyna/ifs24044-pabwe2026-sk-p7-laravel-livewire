# Ruang Stok — IFS24044

Aplikasi manajemen inventaris untuk studi kasus PABWE 2026 Praktikum 7. Dibangun dari referensi praktikum menggunakan Laravel 13, Livewire 4, Blade, Tailwind CSS 4, MySQL/MariaDB, PhpSpreadsheet, dan Toast UI Editor.

URL deployment yang ditentukan:

```text
https://ifs24044-pabwe2026-sk-p7-laravel-livewire
```

## Fitur dan hak akses

- Register sebagai member, login dengan username/email, logout, regenerasi sesi, dan pembatasan percobaan login.
- Dashboard inventaris dari database, pencarian nama/SKU/deskripsi, filter status, dan pagination.
- Tambah, lihat, edit, dan hapus barang: nama, SKU opsional, deskripsi Markdown, jumlah, satuan, harga opsional, minimum stok, serta gambar barang.
- Penyesuaian jumlah dari daftar dan detail barang. Operasi pengurangan menolak hasil negatif; pembaruan jumlah menggunakan transaksi dan penguncian baris.
- Status otomatis: `out_of_stock` untuk jumlah 0; `low_stock` untuk jumlah positif yang tidak melebihi minimum; `available` untuk jumlah di atas minimum.
- Editor Markdown dengan pratinjau dan rendering HTML yang disanitasi.
- Impor XLSX/XLS/CSV dengan validasi seluruh baris dan transaksi; ekspor XLSX dengan header, format angka/harga, filter, serta pembekuan header.
- OpenAI menghasilkan usulan barang, dilanjutkan pratinjau, pemilihan, lalu penyimpanan. Hasil tetap divalidasi sebelum disimpan.
- Profil pengguna: ubah nama/username/email, password dengan verifikasi password saat ini, dan foto profil yang dipotong di server menjadi 400 × 400 piksel.
- Admin melihat daftar pengguna, jumlah barang masing-masing, serta inventaris pengguna secara read-only dan mengekspornya.

Member hanya dapat mengelola barang miliknya. Admin menggunakan halaman `/admin/users` dan `/admin/users/{user}/stocks`; halaman pengelolaan `/stocks` dan `/stocks/{stock}` khusus member. Middleware role dan ownership dipertahankan pada permintaan Livewire, disertai policy pada tindakan barang. Identitas pengguna target admin dan properti sensitif komponen dikunci dari perubahan browser.

## Struktur Feature-First

```text
app/
  Features/
    Auth/       Actions, Livewire
    Stock/      Contracts, Enums, Models, Policies, Repositories, Services, Livewire
    Ai/         Contracts, DTO, Services
    Profile/    Services, Livewire
    Admin/      Services, Livewire
  Http/Middleware/
  Models/User.php
  Providers/AppServiceProvider.php
resources/views/livewire/{auth,stock,profile,admin}/
database/{migrations,factories,seeders}/
tests/{Unit,Feature}/
```

Repository menyediakan akses data; service menangani aturan stok, unggahan, spreadsheet, profil, dan komunikasi AI. DTO memetakan hasil AI. Livewire menangani interaksi, validasi formulir, otorisasi, dan umpan balik pengguna.

## Persiapan

Gunakan PHP yang memenuhi `composer.json` dan `composer.lock`; pengembangan menggunakan PHP 8.4 dan pipeline menggunakan image PHP 8.5. Diperlukan Composer, Node.js yang kompatibel dengan Vite 8 (pipeline: Node 24), npm, serta MySQL/MariaDB.

Ekstensi PHP mencakup `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `pdo_sqlite`, `session`, `tokenizer`, `xml`, `gd`, `zip`, `iconv`, `simplexml`, `xmlreader`, `xmlwriter`, `zlib`, `bcmath`, dan `exif`. Pengujian coverage membutuhkan Xdebug dengan mode `coverage` atau PCOV yang aktif.

Jalankan dari direktori proyek:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Di PowerShell, gunakan `Copy-Item .env.example .env` sebagai pengganti `cp` bila diperlukan. Salin environment hanya pada instalasi pertama agar konfigurasi yang sudah ada tetap tersimpan.

Buat database melalui klien MySQL:

```sql
CREATE DATABASE IF NOT EXISTS pabwe_p7_stock
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sesuaikan `.env` dengan koneksi MySQL setempat:

```dotenv
APP_NAME="Ruang Stok"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FORCE_HTTPS=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pabwe_p7_stock
DB_USERNAME=nama_pengguna_database
DB_PASSWORD=
```

Isi password database pada `.env` lokal. Jangan menyalin nilai rahasia ke README, source, atau `.env.example`.

```bash
php artisan migrate --seed
php artisan storage:link
npm ci --ignore-scripts
npm run build
```

**Pada komputer Windows ini**, jalankan dari terminal VS Code:

```powershell
.\start-local.bat
```

Launcher menyalakan MariaDB praktik di port `3307`, menunggu database siap, lalu menyalakan web server jika belum berjalan. Jika web server sudah aktif, launcher memulihkan database tanpa membuat web server kedua. Jalankan kembali perintah ini setelah restart komputer. Database dan `.env` lokal tetap disimpan di komputer ini.

Untuk instalasi lain yang memakai MySQL/MariaDB tersendiri, pastikan layanan database sesuai `.env` sudah berjalan, lalu jalankan `php artisan serve --host=127.0.0.1 --port=8000`. Perintah `php artisan serve` sendiri hanya menyalakan web server, bukan database.

Buka `http://127.0.0.1:8000`. Untuk pengembangan aset, jalankan `npm run dev` di terminal terpisah. Aset production dibuat melalui `npm run build`.

Seeder menyediakan akun praktik dan beberapa contoh barang dari database:

| Peran | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `admin` |
| Member | `member` | `member` |

Akun tersebut merupakan data demonstrasi yang diwajibkan praktikum. Ganti password melalui halaman profil sebelum aplikasi digunakan pada server yang dapat diakses orang lain. Registrasi biasa menetapkan peran `member`.

## Excel dan AI

Gunakan hasil ekspor sebagai template impor. Header ekspor:

```text
name, sku, description, quantity, unit, price, min_stock, status, created_at
```

Impor memerlukan header `name`, `sku`, `description`, `quantity`, `unit`, `price`, dan `min_stock`, dengan maksimal 1000 baris barang per berkas. Kolom nullable boleh kosong. Status dihitung ulang dari jumlah dan minimum stok; kepemilikan ditentukan dari pengguna yang sedang masuk. Formula spreadsheet ditolak. Impor menambahkan barang dan dibatalkan seluruhnya apabila validasi gagal.

Konfigurasikan OpenAI pada environment server:

```dotenv
OPENAI_ENDPOINT=https://api.openai.com/v1
OPENAI_KEY=
OPENAI_MODEL=gpt-4o-mini
```

Isi `OPENAI_KEY` dengan API key milik Anda melalui `.env` lokal atau secret environment hosting. Endpoint harus HTTPS dan mendukung Chat Completions serta Structured Outputs sesuai request aplikasi. Contoh prompt: `Buatkan 5 barang persediaan untuk toko alat tulis, gunakan harga rupiah.`

Generator memerlukan koneksi API dan kuota yang tersedia. Aplikasi menampilkan pesan kegagalan ketika layanan belum dikonfigurasi atau tidak dapat dihubungi; aplikasi tidak mengganti respons API dengan data palsu. Pengujian memakai HTTP fake atau stub sehingga tidak mengirim request berbayar.

## Pengujian

Setelah dependency dan aset terpasang:

```bash
composer test
composer audit
```

Untuk Xdebug, aktifkan `XDEBUG_MODE=coverage`. Di PowerShell:

```powershell
$env:XDEBUG_MODE = 'coverage'
composer test
```

Pengujian memakai SQLite in-memory, cache/session array, dan konfigurasi testing; database MySQL aplikasi tetap terpisah. Source coverage mencakup seluruh `app/`. Konfigurasi PHPUnit menggagalkan pengujian yang menghasilkan notice, warning, deprecation, atau risky test.

Untuk menghasilkan laporan yang sama dengan stage Jenkins:

```bash
php vendor/bin/phpunit --colors=never --coverage-text=coverage.txt --coverage-clover=coverage.xml --coverage-html=coverage-html
```

Jenkins memeriksa Clover dan menggagalkan build jika coverage statements, methods, atau elements kurang dari 100%. Laporan coverage, `.phpunit.cache`, hasil browser QA, `.qa`, `.env`, `vendor`, dan `node_modules` diabaikan Git. Jalankan verifikasi ulang setelah mengubah aplikasi; hasil terakhir yang terukur dilaporkan pada handoff pekerjaan.

## Deployment dan Jenkins

Untuk **audit HTTPS lokal di Windows**, alamat yang dipakai adalah:

```text
https://ifs24044-pabwe2026-sk-p7-laravel-livewire
```

Jalankan setup satu kali dari folder proyek, lalu jalankan server HTTPS:

```powershell
.\setup-local-https.bat
.\start-https.bat
```

Setup membuat sertifikat lokal baru, memasukkan CA lokal ke trust store pengguna Windows, dan menambahkan satu entri hostname ke file `hosts`. Windows meminta izin Administrator untuk perubahan `hosts`. Sertifikat, private key, konfigurasi Apache, dan log disimpan di `.qa/https/` yang diabaikan Git; jangan menyalinnya ke repository. `.env` lokal disesuaikan ke alamat HTTPS tersebut.

Launcher HTTPS menggunakan Apache XAMPP dengan konfigurasi terpisah dan modul PHP dari instalasi PHP aktif. Database lokal tetap memakai data yang sudah ada. Server hanya mendengarkan `127.0.0.1:443`; konfigurasi Apache global tidak diubah. Setelah restart komputer, jalankan kembali `start-https.bat`. Alamat ini hanya tersedia pada komputer yang menjalankan setup tersebut; publikasi internet memerlukan domain dan server hosting tersendiri.

Konfigurasi production wajib memakai hostname yang telah ditentukan:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ifs24044-pabwe2026-sk-p7-laravel-livewire
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

Arahkan document root web server ke direktori `public/`, gunakan PHP-FPM yang sesuai, dan sediakan DNS/hosts serta sertifikat TLS untuk hostname tersebut. `APP_URL` tidak membuat DNS atau sertifikat secara otomatis. Untuk deployment pada origin yang sama, biarkan `ASSET_URL` tidak diisi agar aset mengikuti URL aplikasi. Jika HTTPS dihentikan pada reverse proxy, atur forwarded headers dan kepercayaan hanya kepada proxy milik deployment tersebut.

Siapkan koneksi MySQL production dan `APP_KEY` tersendiri di server. Simpan key aplikasi secara persisten; jangan menggantinya setiap deploy. Pastikan direktori runtime `storage/app/public`, `storage/app/private`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, dan `bootstrap/cache` tersedia. Berikan izin tulis pada `storage/` dan `bootstrap/cache/`, jalankan `storage:link`, migrasi dengan `php artisan migrate --force`, kemudian cache konfigurasi/view sesuai proses hosting. File `public/build/` hasil build harus tersedia. Seeder demonstrasi digunakan untuk lingkungan praktik; deployment ke database yang sudah terisi tidak membutuhkan `migrate:fresh`.

`Jenkinsfile` mengikuti tahapan yang diberikan: checkout, instalasi Composer dan audit, build frontend, PHPUnit dan gate coverage 100%, Trivy HIGH/CRITICAL, packaging, publikasi artifact, request deployment, lalu polling status. Audit atau coverage yang gagal menghentikan pipeline; pengujian tidak dikecualikan dari perhitungan `app/`.

Infrastruktur Jenkins perlu menyediakan:

- Docker Pipeline, Pipeline Utility Steps (`readJSON`), dan Warnings NG/SARIF (`recordIssues`).
- Image `delcom-ci-php:8.5` dengan ekstensi dan driver coverage; image Node/Trivy/curl sesuai Jenkinsfile.
- Akses Docker untuk publikasi artifact melalui container `cicd-jenkins`, jaringan `cicd-network`, serta akses unduh artifact oleh server deployment.
- Environment `URL_REDEPLOY`, `URL_PROGRESS`, `WEBSITE_ID`, dan `DEPLOY_TOKEN`. Hubungkan token dengan Jenkins Credentials Binding agar nilainya terlindungi di log; jangan menyimpan token sebagai teks di Git.
- Konfigurasi environment aplikasi pada hosting, termasuk APP_URL yang tepat, database, dan OpenAI key jika fitur AI diaktifkan.

ZIP deployment membawa `.env.example` dan aset terbangun; dependency dipasang kembali oleh server deployment sesuai pola praktikum. `.env` aktual dan data runtime lokal harus tetap berada di luar artifact. Eksekusi Jenkins, scan Trivy, pemetaan hostname/TLS, dan publikasi server memerlukan infrastruktur tersebut dan tidak dibuktikan hanya oleh pengujian lokal.

Referensi materi: [proyek latihan praktikum](https://github.com/auxtern/abdullah_ubaid-pabwe2026-latihan-p7-laravel-livewire).

## Hasil verifikasi lokal

- PHP 8.4 dengan Xdebug: 180 tes, 612 assertions; coverage app/ 100% (664 statements, 119 methods, 783 elements).
- PHP 8.5: seluruh 180 tes dan 612 assertions lulus tanpa notice/warning.
- Composer audit dan npm audit: tidak ada kerentanan yang dilaporkan pada pemeriksaan lokal.
- Browser: CRUD, penyesuaian stok, editor Markdown, ekspor, dan admin read-only diperiksa; Axe menemukan 0 pelanggaran pada 12 halaman/keadaan dialog, tanpa error JavaScript.
- Artefak pengujian tersimpan lokal dan diabaikan Git. Skor Lighthouse mengacu pada laporan aktual di .qa, bukan jaminan skor deployment.

Pada komputer Windows ini, jalankan `start-local.bat` dari direktori proyek untuk membuka server lokal di http://127.0.0.1:8000. Launcher menggunakan instance MariaDB praktik terpisah di port 3307 jika konfigurasi .env dan .qa/mysql/my.ini lokal tersedia. Data lokal tersebut tidak termasuk repository/artifact. Instalasi baru tetap mengikuti konfigurasi MySQL standar di atas.

### Lighthouse mobile (cache aset kosong)

Pengukuran lokal memakai aset produksi (`APP_DEBUG=false`), Edge headless, dan throttling mobile Lighthouse.

| Halaman | Performance | Accessibility | Best Practices | SEO |
| --- | ---: | ---: | ---: | ---: |
| Login | 98 | 100 | 100 | 100 |
| Register | 99 | 100 | 100 | 100 |
| Inventaris | 99 | 100 | 100 | 100 |
| Profil | 99 | 100 | 100 | 100 |

Laporan HTML/JSON berada di `.qa/lighthouse/`. Jalankan server lokal lalu `npm run test:browser` untuk smoke test/Axe dan `npm run audit:pages` untuk pengukuran Lighthouse ulang. Di Windows gunakan `npm.cmd` jika kebijakan PowerShell memblokir npm.ps1. Atur `CHROME_PATH` ke executable Chromium/Chrome/Edge pada komputer lain. Skor dapat berubah mengikuti server, jaringan, isi inventaris, dan perangkat.

Belum diverifikasi pada infrastruktur eksternal: eksekusi Jenkins/Trivy, DNS/TLS dan deployment hostname yang ditentukan, serta panggilan OpenAI dengan API key nyata. Integrasi AI sudah diuji menggunakan fake respons HTTP untuk respons sukses dan gagal; API key diisi melalui environment server.
