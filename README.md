# SIPUS API — Sistem Informasi Perpustakaan Sekolah REST API

Backend REST API untuk **Sistem Informasi Perpustakaan Sekolah (SIPUS)** yang dibangun menggunakan **Laravel 13** & **PHP 8.4**. API ini dirancang untuk melayani:
1. **Aplikasi Siswa**: Melihat katalog buku, ketersediaan stok, mengajukan peminjaman, dan melihat riwayat transaksi peminjaman.
2. **Dashboard Petugas**: Mengelola inventaris buku, memantau statistik perpustakaan, menyetujui/menolak pengajuan, mengonfirmasi serah terima buku, mencatat pengembalian, dan meninjau histori transaksi.

---

## 1. Persyaratan Sistem

* **PHP** >= 8.3 (Diuji pada PHP 8.4)
* **Composer** >= 2.0
* **Ekstensi PHP**: `pdo`, `pdo_sqlite` (atau `pdo_mysql` untuk MySQL/MariaDB), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
* **Database**: SQLite (default lokal/testing) atau MySQL 8.0+ / MariaDB 10.4+

---

## 🌟 Live VPS Deployment

Aplikasi ini telah aktif dan dapat diakses langsung secara publik di VPS:
* **Base URL API**: `http://43.173.6.21:8080/api/v1`
* **Swagger UI (Online)**: `http://43.173.6.21:8080/api/documentation`
* **Database**: MySQL `sipus_db` di VPS
* **Web Server**: Nginx Virtual Host (Port `8080`), berjalan berdampingan secara aman dengan *Smart Talaqqi Admin* (Port `80` & `3000`).

---

## 2. Instalasi & Konfigurasi

### Langkah 1: Clone & Pasang Dependensi
```bash
composer install
```

### Langkah 2: Konfigurasi Environment
Salin file environment dan generate application key:
```bash
cp .env.example .env
php artisan key:generate
```

### Langkah 3: Konfigurasi Database
Secara default, project menggunakan SQLite yang praktis tanpa konfigurasi tambahan:
```env
DB_CONNECTION=sqlite
```
*(Opsional)* Jika menggunakan MySQL / MariaDB, sesuaikan nilai berikut pada file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sipus_db
DB_USERNAME=root
DB_PASSWORD=
```

### Langkah 4: Migrasi & Database Seeder
Jalankan migrasi database beserta data awal (sample akun dan buku):
```bash
php artisan migrate:fresh --seed
```

### Langkah 5: Menjalankan Server Lokal
```bash
php artisan serve
```
Aplikasi akan aktif di `http://127.0.0.1:8000`.

---

## 3. Akun Bawaan (Default Credentials)

Setelah menjalankan seeder, gunakan kredensial berikut untuk login:

| Role | Nomor Identitas (NIS/ID) | Email | Password | Keterangan |
|---|---|---|---|---|
| **Staff (Petugas)** | `STF-001` | `staff@example.com` | `password` | Akses penuh dashboard, buku, sirkulasi, dan walk-in |
| **Student (Siswa)** | `202410001` | `student@example.com` | `password` | Booking daring (durasi s/d 3 hari), riwayat saya |
| **Student 2 (Siswa)** | `202410002` | `student2@example.com` | `password` | Akun siswa kedua untuk uji coba isolasi data |

> [!NOTE]
> **Kebijakan Guru di SMKS TI Annisa 2**: Guru tidak memerlukan akun login mandiri karena telah difasilitasi buku paket pegangan guru dari sekolah. Guru tetap dapat mengecek ketersediaan buku melalui **Katalog Publik (OPAC)** tanpa login. Apabila guru ingin meminjam buku referensi tambahan, dilayani melalui metode **Walk-in di Meja Sirkulasi** oleh Petugas.

> [!WARNING]
> Kredensial di atas hanya untuk keperluan pengembangan (*development*) dan pengujian. Jangan gunakan kredensial ini di lingkungan produksi!

---

## 4. Alur Bisnis Peminjaman & Manajemen Stok

Sistem mendukung **2 metode peminjaman**:
1. **Peminjaman Daring (Booking / Reservasi via App Siswa)**: Siswa membuat pengajuan terlebih dahulu (`PENDING`), disetujui petugas (`APPROVED`), lalu saat buku fisik diserahkan status diubah ke `BORROWED`.
2. **Peminjaman Langsung di Tempat (Walk-in oleh Petugas)**: Guru, Siswa, maupun Tamu umum datang membawa buku ke meja sirkulasi, petugas mencatatkan peminjaman langsung (`POST /api/v1/staff/borrowings/direct`) sehingga status langsung menjadi `BORROWED` dan stok langsung berkurang saat itu juga.

### State Machine Status
```text
[Alur 1: Pengajuan Online Siswa]
PENDING ──► REJECTED (Ditolak petugas)
   │
   ▼
APPROVED (Disetujui petugas, buku disiapkan)
   │
   ▼
BORROWED (Petugas serahkan buku, STOK BERKURANG, DUE DATE DIHITUNG)
   │
   ▼
RETURNED (Buku dikembalikan, STOK BERTAMBAH KEMBALI)

[Alur 2: Datang Langsung / Walk-in Guru, Siswa, Tamu]
Petugas Input Langsung ──► BORROWED (STOK BERKURANG, DUE DATE DIHITUNG) ──► RETURNED
```

### Aturan Batas Maksimal Pengembalian:
* **Siswa & Tamu**: Batas waktu maksimal pengembalian **3 hari** terhitung sejak buku dipinjam (`due_date = borrowed_at + 3 hari`).
* **Guru (Walk-in)**: Batas waktu maksimal pengembalian hingga **7 hari** terhitung sejak buku dipinjam (`due_date = borrowed_at + 7 hari`) untuk menunjang penyusunan modul ajar.
* Jika peminjaman melewati batas waktu (`now() > due_date`), sistem secara otomatis menandai transaksi dengan atribut boolean `is_overdue = true`.
* Validasi input menolak setiap penetapan tanggal pengembalian yang melebihi batas tersebut (**HTTP 422 Unprocessable Entity**).

### Integritas Stok & Keamanan:
* Operasi pengurangan stok pada tahap `BORROWED` dan pengembalian pada `RETURNED` dibungkus dalam transaksi database (`DB::transaction`).
* Pengurangan stok menggunakan penguncian baris (`lockForUpdate`) untuk mencegah *race condition* dan memastikan stok tidak pernah bernilai negatif.
* Penghapusan buku menerapkan **Soft Deletes** (`deleted_at`), sehingga integritas riwayat transaksi masa lalu tetap terjaga tanpa merusak foreign key `borrowing_items`.

---

## 5. Dokumentasi API (Swagger / OpenAPI UI)

Dokumentasi OpenAPI interaktif tersedia dan dapat langsung digunakan untuk mencoba seluruh endpoint dengan Bearer Token:

* **URL Swagger UI**: `http://127.0.0.1:8000/api/documentation`
* **Spesifikasi JSON**: `http://127.0.0.1:8000/docs/api-docs.json`

### Regenerasi Swagger Docs:
Jika ada perubahan skema anotasi OpenAPI:
```bash
php artisan l5-swagger:generate
```

### Cara Autentikasi di Swagger:
1. Buka `http://127.0.0.1:8000/api/documentation`.
2. Lakukan request ke `POST /api/v1/auth/login` dengan akun student atau staff.
3. Salin token dari respons (`data.token`).
4. Klik tombol **Authorize** (ikon gembok) di kanan atas Swagger UI.
5. Tempel token ke kolom input dan klik **Authorize**.

---

## 6. Daftar Endpoint REST API (`/api/v1`)

Format standar respons:
* **Berhasil**: `{"success": true, "message": "...", "data": ...}`
* **Gagal**: `{"success": false, "message": "...", "errors": ...}`

### A. Autentikasi
| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Public | Autentikasi user & mengembalikan Sanctum Bearer token |
| `POST` | `/api/v1/auth/logout` | Authenticated | Menghapus token akses yang sedang aktif |
| `GET` | `/api/v1/auth/me` | Authenticated | Mengambil profil user yang sedang login |

### B. Katalog Buku (Publik / OPAC)
| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/books` | Public | Katalog buku publik/OPAC (pagination, search `?search=`, filter `?available_only=1`) |
| `GET` | `/api/v1/books/{id}` | Public | Detail buku & informasi ketersediaan stok publik |

### C. Peminjaman Buku (Siswa & Guru / Member)
| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/borrowings` | Siswa / Guru (`role:student,teacher`) | Mengajukan booking peminjaman buku (status awal `PENDING`) |
| `GET` | `/api/v1/borrowings/me` | Siswa / Guru (`role:student,teacher`) | Riwayat peminjaman saya (filter `?status=`, pagination) |
| `GET` | `/api/v1/borrowings/{id}` | Siswa/Guru (Pemilik) / Petugas | Detail peminjaman buku (diotorisasi via Policy) |

### D. Dashboard & Manajemen Petugas (`role:staff`)
| Method | Endpoint | Akses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/staff/dashboard` | Petugas | Statistik ringkasan (total buku, pending, aktif, selesai) |
| `GET` | `/api/v1/staff/books` | Petugas | Daftar inventaris buku dengan pencarian & pagination |
| `POST` | `/api/v1/staff/books` | Petugas | Menambah buku baru |
| `GET` | `/api/v1/staff/books/{id}` | Petugas | Detail buku |
| `PUT` | `/api/v1/staff/books/{id}` | Petugas | Memperbarui data / stok buku |
| `DELETE` | `/api/v1/staff/books/{id}` | Petugas | Menghapus buku secara *soft delete* |
| `GET` | `/api/v1/staff/borrowings` | Petugas | Daftar seluruh pengajuan peminjaman (filter `?status=`) |
| `POST` | `/api/v1/staff/borrowings/direct` | Petugas | Peminjaman langsung di tempat (Walk-in Siswa, Guru, atau Tamu tanpa akun) |
| `POST` | `/api/v1/staff/borrowings/{id}/approve` | Petugas | Menyetujui pengajuan (`PENDING -> APPROVED`) |
| `POST` | `/api/v1/staff/borrowings/{id}/reject` | Petugas | Menolak pengajuan (`PENDING -> REJECTED`) |
| `POST` | `/api/v1/staff/borrowings/{id}/borrow` | Petugas | Konfirmasi penyerahan buku (`APPROVED -> BORROWED`, stok berkurang) |
| `POST` | `/api/v1/staff/borrowings/{id}/return` | Petugas | Konfirmasi pengembalian (`BORROWED -> RETURNED`, stok bertambah) |
| `GET` | `/api/v1/staff/borrowings/history` | Petugas | Riwayat seluruh transaksi peminjaman |

---

## 7. Pengujian Otomatis (Automated Testing)

Project telah dilengkapi Feature Tests menyeluruh mencakup **37 skenario pengujian** (positif dan negatif) dengan total **148 assertions**:

### Skenario Pengujian:
1. `test_student_login_berhasil`
2. `test_login_dengan_credential_salah_gagal` (Negative)
3. `test_authenticated_user_can_access_me_and_logout`
4. `test_guest_atau_student_dapat_melihat_katalog_tanpa_autentikasi`
5. `test_guest_dapat_mencari_buku_berdasarkan_judul_atau_penulis`
6. `test_guest_dapat_melihat_detail_buku`
7. `test_melihat_detail_buku_yang_tidak_ada_mengembalikan_404` (Negative)
8. `test_student_dapat_membuat_borrowing_pending`
9. `test_staff_dapat_melihat_borrowing`
10. `test_staff_dapat_approve_pending_dan_approved_tidak_langsung_menjadi_borrowed`
11. `test_staff_dapat_confirm_borrowing_approved_menjadi_borrowed_dan_stock_berkurang`
12. `test_staff_dapat_return_borrowing_dan_stock_bertambah_kembali`
13. `test_invalid_status_transitions_ditolak` (Negative)
14. `test_stock_tidak_boleh_negatif_dan_konfirmasi_peminjaman_gagal_jika_stok_kurang` (Negative)
15. `test_pengajuan_peminjaman_ditolak_jika_stok_buku_tidak_mencukupi_pada_input` (Negative)
16. `test_staff_dapat_mencatat_peminjaman_langsung_walk_in_dan_stok_langsung_berkurang`
17. `test_peminjaman_ditolak_jika_due_date_lebih_dari_3_hari` (Negative)
18. `test_staff_dapat_mencatat_peminjaman_langsung_untuk_tamu_tanpa_akun`
19. `test_peminjaman_langsung_ditolak_jika_user_id_dan_nama_peminjam_kosong` (Negative)
20. `test_staff_dapat_mencatat_peminjaman_langsung_untuk_guru_atau_staff_sekolah`
21. `test_transaksi_terdeteksi_overdue_jika_melewati_due_date`
22. `test_guru_dapat_mengajukan_booking_peminjaman_secara_mandiri`
23. `test_guru_dapat_memilih_due_date_hingga_7_hari_dan_ditolak_jika_lebih_7_hari` (Negative)
24. `test_guru_dapat_melihat_riwayat_peminjaman_miliknya_sendiri`
25. `test_student_tidak_dapat_mengakses_endpoint_staff_dashboard` (Negative)
26. `test_student_tidak_dapat_mengakses_endpoint_staff_books_create_update_delete` (Negative)
27. `test_student_tidak_dapat_mengakses_endpoint_staff_borrowings` (Negative)
28. `test_student_tidak_dapat_melihat_borrowing_milik_student_lain` (Negative)
29. `test_student_dapat_melihat_borrowing_miliknya_sendiri`
30. `test_staff_dapat_melihat_borrowing_milik_siapapun`
31. `test_unauthenticated_request_mengembalikan_401` (Negative)
32. `test_login_validasi_gagal_jika_field_kosong_atau_format_email_salah` (Negative)
33. `test_staff_tambah_buku_gagal_jika_data_tidak_lengkap_atau_stok_negatif` (Negative)
34. `test_staff_update_buku_gagal_jika_stok_negatif` (Negative)
35. `test_pengajuan_peminjaman_gagal_jika_items_kosong_quantity_nol_atau_buku_tidak_ada` (Negative)
36. `test_staff_tidak_dapat_mengajukan_peminjaman_karena_khusus_siswa` (Negative)
37. `test_mengakses_transaksi_peminjaman_yang_tidak_ada_mengembalikan_404` (Negative)

### Menjalankan Test Suite:
```bash
php artisan test
```
Atau dengan tampilan testdox:
```bash
php artisan test --testdox
```

### Code Formatting (Pint):
```bash
vendor/bin/pint --format agent
```
# sipus_api
