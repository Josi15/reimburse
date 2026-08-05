# Cakupan Departemen, Anggota Proyek, & Lupa Password

Catatan perubahan 2026-08-05. Empat hal yang berubah dari sisi cara pakai:
data disaring per departemen, pengadaan punya menu sendiri, proyek punya daftar
anggota, dan alur lupa password sudah bisa dipakai.

---

## 1. Departemen melekat pada pengaju

Sebelumnya form pengajuan meminta memilih "Departemen Pengaju", dan pengaju
boleh membebankan biaya ke unit lain. Sekarang **departemen diambil dari profil
pengaju** dan tidak bisa dipilih.

- `ReimbursementService::resolveDepartment()` selalu memakai `users.department_id`.
- `department_id` dihapus dari aturan validasi `Store/UpdateReimbursementRequest`
  — kalaupun klien mengirimnya, server mengabaikannya.
- Form hanya menampilkan nama departemen (read-only).
- Akun tanpa departemen **tidak bisa mengajukan sama sekali**; pesannya
  mengarahkan menghubungi Admin. Ini disengaja: departemen adalah dasar rekap
  Finance sekaligus dasar penyaringan hak akses di bawah.

## 2. Siapa melihat data siapa

Sumber tunggal aturannya: `Reimbursement::scopeVisibleTo()`, dengan padanan
objek di `ReimbursementPolicy::inScope()`. Keduanya harus sepakat — kalau tidak,
daftar dan halaman detail bisa berbeda isi.

Tiga tingkat cakupan:

| Tingkat | Siapa | Melihat |
|---|---|---|
| Lintas departemen | Super Admin, Direktur, Finance, Auditor | Semua pengajuan |
| Satu departemen | Admin, Manager, Supervisor | Pengajuan departemennya sendiri |
| Pribadi | Employee, Magang, Project Manager | Pengajuannya sendiri |

Penandanya permission baru **`data.viewAllDepartments`**. Role yang memegang
`reimbursement.viewAny` TANPA permission ini otomatis terkunci di departemennya.

Diterapkan konsisten di:

- `GET /api/reimbursements` (termasuk halaman Persetujuan, yang memakai endpoint
  yang sama)
- `ReimbursementPolicy::view` dan seluruh `approveManager/Finance/Director`
  — Manager/Supervisor tidak bisa menyetujui klaim unit lain
- `DashboardService` — kartu, antrean, grafik; `top_departments` hanya untuk
  yang melihat lintas departemen. Respons kini punya `scope`
  (`personal|department|global`) + `scope_label`
- `ReportService` (via `->forUser()` di `ReportController`), termasuk export
- `GET /api/users` — Admin hanya mengelola user departemennya
- `GET /api/search`

**Jaring pengaman:** atasan yang `department_id`-nya masih kosong TIDAK mewarisi
seluruh perusahaan — ia hanya melihat pengajuannya sendiri sampai departemennya
diisi.

> Catatan operasional: pastikan tiap departemen punya Manager/Supervisor. Kalau
> tidak, pengajuan di departemen itu tidak punya penilai tingkat pertama.

## 3. Pengadaan berdiri sendiri, terpisah dari Reimbursement

Modul pengajuan dibagi menjadi **tiga bagian yang berdiri sendiri**, masing-
masing dengan menu, alamat, daftar, form, dan halaman detailnya sendiri:

| Bagian | Alamat | Jenis pengajuan | Syarat |
|---|---|---|---|
| Reimbursement | `/reimbursements` | biaya, lembur | `reimbursement.view` / `.viewAny` |
| Pengadaan Barang | `/goods` | barang | `reimbursement.procurement` |
| Layanan & Server | `/services` | layanan | `reimbursement.procurement` |

Sumber tunggalnya `App\Support\ClaimSection` — dipakai menu sidebar
(`Navigation`), route web, penyaringan daftar, dan pemilih jenis di form.
Menambah bagian baru cukup dilakukan di kelas itu.

Tiap bagian punya empat route: `index`, `create`, `{id}`, `{id}/edit`.
Halamannya tetap komponen React yang sama (`Reimbursements/Index|Form|Show`)
karena isinya memang identik — yang berbeda hanya bagian yang sedang aktif,
dikirim sebagai prop `section`. Menggandakan komponennya hanya akan
menggandakan pekerjaan setiap kali daftar atau form berubah.

Yang berubah secara nyata bagi pengguna:

- **Daftar tidak lagi bercampur.** `GET /api/reimbursements?section=` menyaring
  per bagian, jadi pengadaan tidak muncul di daftar Reimbursement dan
  sebaliknya. Antrean lintas jenis (Persetujuan, Pembayaran) sengaja TIDAK
  memakai parameter ini — approver memang perlu melihat semuanya.
- **Bagian berjenis tunggal** menyembunyikan kolom & filter "Jenis" serta
  pemilih jenis di formnya. `GET /api/options/claim-types?section=` memastikan
  form Pengadaan tidak pernah menawarkan jenis dari menu lain.
- **Judul, breadcrumb, dan tombol kembali** mengikuti bagiannya
  ("Detail Pengadaan Barang", bukan "Detail Reimbursement").

Halaman detail bisa dibuka dari tiga alamat sekaligus (`/goods/5`,
`/services/5`, `/reimbursements/5`) karena tautan notifikasi, antrean
persetujuan, dan pembayaran tidak tahu jenis klaimnya. Karena itu bagian yang
ditampilkan **diturunkan dari `claim_type` pengajuan yang termuat**, bukan dari
alamat yang dipakai membukanya — begitu juga halaman edit. Pembagiannya
dibagikan ke frontend lewat prop Inertia `claimSections` (`lib/sections.js`),
supaya label dan alamat di UI tidak pernah berbeda dari aturan backend.

Dua penyesuaian pendukung:

- Penanda menu aktif di `AuthenticatedLayout` memakai **pencocokan terpanjang**,
  supaya submenu tidak ikut menyalakan menu lain yang berawalan sama.
- Middleware `permission:` kini menerima beberapa permission dipisah koma dengan
  arti **any-of**, sama seperti aturan menu di `Navigation`. Kalau kedua tempat
  berbeda arti, menu bisa tampil tapi halamannya menolak.

## 4. Upah lembur tidak ditampilkan

Field `hourly_rate` pada `ClaimType::Overtime` diberi tanda `'hidden' => true`.

- Nilainya tetap ditentukan server dari `roles.overtime_rate` dan tetap dipakai
  menghitung nominal (`hours × hourly_rate`) — hanya tampilannya yang hilang.
- `ClaimTypeFields.jsx` melewati field bertanda `hidden`.
- `ClaimType::displayDetails()` juga melewatinya, jadi tarif tidak muncul di
  halaman detail pengajuan.
- Pratinjau nominal di form tidak lagi menulis "3 × Rp 30.000", cukup totalnya.

## 5. Anggota proyek

Migrasi `2026_08_05_100000_create_project_user_table` menambah pivot
`project_user` (unik per pasangan proyek+user).

- `Project::members()` ↔ `User::projects()`; satu orang boleh masuk beberapa
  proyek.
- Master Data → Project punya daftar centang **Anggota Proyek** (komponen baru
  `MultiSelectList`, ada kotak cari). Sumber opsinya
  `GET /api/options/project-members`.
- `member_ids` pada store/update: dikirim = daftar diganti seluruhnya, tidak
  dikirim = penugasan dibiarkan.
- `GET /api/options/projects` kini **hanya** menampilkan proyek tempat user
  ditugaskan (atau yang dipegangnya sebagai PM). Pemegang `project.manage`
  tetap melihat semua.
- `App\Rules\AssignedProject` menegakkan hal yang sama di server, supaya
  anggaran tim lain tidak bisa digerus dari request yang dirakit manual.
  Nilai `project_id` yang **tidak berubah** selalu lolos, agar pemilik draft
  tidak terkunci dari pengajuannya sendiri bila penugasannya dicabut.
- Halaman Anggaran Proyek menampilkan daftar anggota; daftar proyek menampilkan
  jumlah anggota.

> Proyek yang belum punya anggota tidak akan muncul di dropdown pengajuan siapa
> pun. Tugaskan anggotanya lewat Master Data → Project.

## 6. Lupa password

Alur bawaan Breeze sudah ada; yang ditambahkan:

- `App\Notifications\ResetPasswordNotification` — email bahasa Indonesia,
  menyebut masa berlaku tautan. Sengaja **tidak** `ShouldQueue`: alur ini
  ditunggu di depan layar dan di dev queue worker sering tidak hidup.
- `lang/en/passwords.php` — pesan status berbahasa Indonesia. Ditaruh di locale
  `en` karena `APP_LOCALE` sengaja tetap `en` agar pesan validasi bawaan Laravel
  tetap tersedia.
- Email tidak dikenal mendapat **jawaban yang sama** dengan yang terdaftar
  (bukan error), supaya halaman ini tidak bisa dipakai menebak siapa saja
  penggunanya.
- Rate limit `throttle:password-reset` (6/menit per IP) di atas throttle bawaan
  broker yang hanya per akun.
- Reset yang berhasil **membuka kunci akun** (`failed_login_attempts`,
  `locked_until` di-nol-kan) dan dicatat ke audit log. Tanpa ini, orang yang
  lupa password dan sudah kena lockout 5x gagal login tetap ditolak walau
  passwordnya sudah diganti.

### Mengaktifkan pengiriman email sungguhan

`MAIL_MAILER=log` (default `.env.example`) berarti email **tidak dikirim ke mana
pun** — isinya, termasuk tautan reset, ditulis ke `storage/logs/laravel.log`.
Alurnya tetap jalan penuh, jadi gejalanya membingungkan: halaman bilang
"tautan sudah dikirim", tapi inbox tidak pernah menerima apa-apa. Kalau ada
laporan "lupa password tidak jalan padahal emailnya benar", periksa baris ini
lebih dulu.

Untuk mengirim betulan lewat Gmail:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null                       # 587 memakai STARTTLS otomatis
MAIL_USERNAME=akun-anda@gmail.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxx         # App Password 16 karakter, tanpa spasi
MAIL_FROM_ADDRESS="akun-anda@gmail.com"
```

Catatan penting:

- `MAIL_PASSWORD` adalah **App Password** dari
  <https://myaccount.google.com/apppasswords> (perlu 2FA aktif di akun Google),
  bukan password akun. Password akun biasa selalu ditolak dengan
  `535-5.7.8 Username and Password not accepted`.
- `MAIL_FROM_ADDRESS` harus alamat Gmail yang sama dengan `MAIL_USERNAME` —
  kalau berbeda, Gmail menimpanya dengan alamat yang terautentikasi.
- Jika port 587 diblokir jaringan: `MAIL_PORT=465` + `MAIL_SCHEME=smtps`.
- Gmail punya batas kirim harian; untuk produksi pakai layanan khusus
  (Resend/Brevo/SES) dengan domain terverifikasi.

Alternatif tanpa kredensial: **Mailpit** (`MAIL_HOST=127.0.0.1`,
`MAIL_PORT=1025`) menahan semua email di kotak masuk lokal — cocok untuk
memeriksa tampilan email tanpa mengirim ke alamat asli.

### Memverifikasi konfigurasi

```bash
php artisan config:clear
php artisan mail:test tujuan@example.com
```

`App\Console\Commands\SendTestMail` mencetak mailer/host/pengirim yang sedang
dipakai, memperingatkan bila masih `log`, lalu menampilkan **penyebab kegagalan
SMTP apa adanya di terminal**. Tanpa ini, error SMTP baru terlihat setelah
pengguna menunggu di halaman lupa password, dan pesannya tenggelam di log.

## 7. Ikon mata pada password

Komponen baru `resources/js/Components/PasswordInput.jsx`: input password dengan
tombol ikon mata untuk memperlihatkan/menyembunyikan isinya. Dipakai di Login,
Register, Reset Password, Konfirmasi Password, dan Ubah Password di Profil.
Tombolnya `tabIndex={-1}` supaya urutan fokus form tetap email → password →
submit.

---

## Dampak pada test

`UserFactory` kini menempatkan setiap user di departemen bersama
(`DepartmentFactory::shared()`), dan `ReimbursementFactory` menurunkan
`department_id` dari pengajunya — mencerminkan aturan aplikasi, sekaligus
membuat pengaju dan approver berada di satu unit seperti kondisi normal.
`userWithRole()`/`employeeUser()` menerima argumen `Department` opsional untuk
menguji kasus lintas departemen.

Berkas test baru: `DepartmentScopeTest` (7 test) dan `ProcurementMenuTest`
(4 test). Total suite: **275 test / 880 assertion** hijau.
