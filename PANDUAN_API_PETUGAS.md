# 📚 Panduan Integrasi REST API - Frontend Petugas (SIPUS)

Dokumentasi ini ditujukan bagi pengembang Frontend Petugas/Staff Perpustakaan (**SIPUS**) untuk mengintegrasikan antarmuka web dengan REST API backend.

---

## 🌐 Informasi Server & Lingkungan

- **Base URL API:** `http://43.173.6.21:8080/api/v1`
- **Swagger UI (Dokumentasi Interaktif):** `http://43.173.6.21:8080/api/documentation`
- **Header Wajib (Semua Request Setelah Login):**
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

---

## 🔐 1. Modul Autentikasi (`/auth`)

### 1.1 Login Petugas
- **Endpoint:** `POST /api/v1/auth/login`
- **Header:** `Content-Type: application/json`
- **Request Body:**
  ```json
  {
    "identity_number": "STF-001",
    "password": "password"
  }
  ```
  > **Catatan:** Login menggunakan nomor identitas petugas (`identity_number`), bukan email. Akun default petugas seeder: `STF-001` / `password`.

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Login successful",
    "data": {
      "user": {
        "id": 1,
        "name": "Petugas Perpustakaan",
        "identity_number": "STF-001",
        "role": "staff"
      },
      "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    }
  }
  ```
  Simpan token ini di `localStorage` / cookie untuk dikirim di header `Authorization: Bearer <token>`.

### 1.2 Profil Petugas (Check Session)
- **Endpoint:** `GET /api/v1/auth/me`
- **Headers:** `Authorization: Bearer <token>`
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "User profile retrieved successfully",
    "data": {
      "id": 1,
      "name": "Petugas Perpustakaan",
      "identity_number": "STF-001",
      "role": "staff"
    }
  }
  ```

### 1.3 Logout
- **Endpoint:** `POST /api/v1/auth/logout`
- **Headers:** `Authorization: Bearer <token>`
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Logout successful"
  }
  ```

---

## 📊 2. Modul Dashboard (`/staff/dashboard`)

Menampilkan data statistik ringkas untuk halaman awal dashboard petugas.

- **Endpoint:** `GET /api/v1/staff/dashboard`
- **Headers:** `Authorization: Bearer <token>`
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Dashboard statistics retrieved successfully",
    "data": {
      "total_books": 45,
      "pending_borrowings": 3,
      "active_borrowings": 12,
      "completed_borrowings": 28
    }
  }
  ```
- **Arti Key Statistik:**
  - `total_books`: Total seluruh judul buku di perpustakaan.
  - `pending_borrowings`: Pengajuan pinjam online dari siswa yang belum disetujui (`PENDING`).
  - `active_borrowings`: Buku yang sedang aktif dipinjam dan dibawa peminjam (`BORROWED`).
  - `completed_borrowings`: Transaksi peminjaman yang sudah selesai dikembalikan (`RETURNED`).

---

## 📖 3. Modul Manajemen Buku (`/staff/books`)

### 3.1 Daftar Katalog Buku
- **Endpoint:** `GET /api/v1/staff/books`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters (Opsional):**
  - `search` *(string)*: Pencarian judul, penulis, atau ISBN (contoh: `?search=bumi`)
  - `available` *(boolean)*: Filter buku yang memiliki stok (`?available=1` atau `?available=0`)
  - `page` *(integer)*: Halaman aktif (contoh: `?page=1`)
  - `per_page` *(integer)*: Jumlah item per halaman (default 10, max 100)
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Books retrieved successfully",
    "data": [
      {
        "id": 1,
        "title": "Bumi Manusia",
        "author": "Pramoedya Ananta Toer",
        "publisher": "Hasta Mitra",
        "publication_year": 1980,
        "isbn": "9789799731234",
        "stock": 5,
        "is_available": true,
        "description": "Novel sejarah Indonesia...",
        "cover": "http://43.173.6.21:8080/storage/covers/sample.jpg"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 10,
      "total": 25
    }
  }
  ```

### 3.2 Tambah Buku Baru
- **Endpoint:** `POST /api/v1/staff/books`
- **Headers:** `Content-Type: multipart/form-data`, `Authorization: Bearer <token>`
- **Form Data:**
  - `title` *(string, required)*: Judul buku
  - `author` *(string, required)*: Penulis
  - `publisher` *(string, required)*: Penerbit
  - `publication_year` *(integer, required)*: Tahun terbit (contoh: `2023`)
  - `isbn` *(string, required, unik)*: Nomor ISBN
  - `stock` *(integer, required)*: Jumlah stok fisik (minimal 0)
  - `description` *(string, optional)*: Sinopsis buku
  - `cover` *(file image / string URL, optional)*: Gambar sampul buku (JPG, PNG, WEBP, maks 2MB)

### 3.3 Detail Buku
- **Endpoint:** `GET /api/v1/staff/books/{id}`

### 3.4 Edit Buku
> ⚠️ **PENTING UNTUK EDIT DENGAN FILE UPLOAD:**  
> Browser web tidak mendukung pengiriman file `multipart/form-data` dengan method HTTP `PUT` murni.  
> Gunakan method **`POST`** dengan field `_method=PUT`:

- **Endpoint:** `POST /api/v1/staff/books/{id}`
- **Headers:** `Content-Type: multipart/form-data`, `Authorization: Bearer <token>`
- **Form Data:**
  - `_method`: `PUT` *(Wajib disertakan)*
  - `title`: Judul buku
  - `author`: Penulis
  - `publisher`: Penerbit
  - `publication_year`: Tahun terbit
  - `isbn`: Nomor ISBN
  - `stock`: Jumlah stok
  - `description`: Sinopsis
  - `cover`: *(Kirim file baru jika ingin mengganti cover, atau kosongkan field ini jika tidak mengubah cover)*

### 3.5 Hapus Buku (Soft Delete)
- **Endpoint:** `DELETE /api/v1/staff/books/{id}`
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Book deleted successfully"
  }
  ```

---

## 🔄 4. Modul Peminjaman & Sirkulasi (`/staff/borrowings`)

Alur transaksi sirkulasi perpustakaan dibagi menjadi dua skenario:

---

### Skenario A: Peminjaman Online (Diajukan oleh Siswa)

Alur lengkap terdiri dari tahapan berikut:

```
[1. PENDING] ➔ [2. APPROVED] ➔ [3. BORROWED] ➔ [4. RETURNED]
  Pengajuan      Disetujui     Buku Diserahkan   Dikembalikan
```

#### Step 1: Petugas Membuka Daftar Pengajuan
- **Endpoint:** `GET /api/v1/staff/borrowings?status=PENDING`
- Petugas memeriksa buku yang diminta oleh siswa.

#### Step 2: Petugas Menyetujui atau Menolak Pengajuan
- **Jika Disetujui:**
  - **Endpoint:** `POST /api/v1/staff/borrowings/{id}/approve`
  - Transisi: `PENDING` $\rightarrow$ `APPROVED`.
  - *Keterangan:* Menandakan petugas telah menyiapkan buku fisik. Stok belum berkurang dan masa peminjaman belum berjalan.
- **Jika Ditolak:**
  - **Endpoint:** `POST /api/v1/staff/borrowings/{id}/reject`
  - **Body JSON:** `{"notes": "Stok fisik buku sedang dalam perbaikan"}`
  - Transisi: `PENDING` $\rightarrow$ `REJECTED`. Transaksi selesai.

#### Step 3: Penyerahan Buku ke Siswa (Serah Terima Fisik)
Ketika siswa datang ke meja perpustakaan untuk mengambil buku:
- **Endpoint:** `POST /api/v1/staff/borrowings/{id}/borrow`
- Transisi: `APPROVED` $\rightarrow$ `BORROWED`.
- **Efek Otomatis di Backend:**
  1. Stok buku otomatis berkurang di database.
  2. Waktu penyerahan (`borrowed_at`) dicatat waktu saat ini.
  3. Batas akhir pengembalian (`due_date`) dihitung otomatis (3 hari untuk siswa).

#### Step 4: Siswa Mengembalikan Buku
Ketika siswa mengembalikan buku ke perpustakaan:
- **Endpoint:** `POST /api/v1/staff/borrowings/{id}/return`
- Transisi: `BORROWED` $\rightarrow$ `RETURNED`.
- **Efek Otomatis di Backend:**
  1. Stok buku otomatis bertambah kembali ke katalog.
  2. Waktu pengembalian (`returned_at`) dicatat. Transaksi selesai.

---

### Skenario B: Peminjaman Langsung di Tempat (Walk-in)

Digunakan ketika peminjam (Siswa / Guru / Pegawai / Tamu) langsung datang ke perpustakaan tanpa memesan lewat aplikasi sebelumnya.

- **Endpoint:** `POST /api/v1/staff/borrowings/direct`
- **Headers:** `Content-Type: application/json`, `Authorization: Bearer <token>`
- **Request Body:**
  ```json
  {
    "borrower_type": "student",
    "user_id": 2,
    "borrower_name": "Budi Santoso",
    "borrower_identity_number": "NIS-202401",
    "borrower_phone": "081234567890",
    "due_date": "2026-10-15",
    "notes": "Peminjaman langsung di tempat",
    "items": [
      {
        "book_id": 1,
        "quantity": 1
      }
    ]
  }
  ```
- **Keterangan Field:**
  - `borrower_type`: `'student'`, `'teacher'`, `'staff'`, atau `'guest'`.
  - `user_id`: ID user siswa terdaftar *(opsional jika guru/tamu tanpa akun)*.
  - `borrower_name`: Nama peminjam *(wajib jika `user_id` kosong)*.
  - `borrower_identity_number`: NIS / NIP / KTP.
  - `due_date`: Tanggal batas kembali (maksimal 3 hari untuk siswa/tamu, 7 hari untuk guru/staff).
  - `items`: Array rincian buku yang dipinjam (`book_id` dan `quantity`). Maksimal total **2 buku** per orang, dan peminjam tidak boleh memiliki peminjaman aktif yang melebihi kuota 2 buku atau memiliki buku yang terlambat (*overdue*).
- **Efek Otomatis:** Status langsung menjadi `BORROWED` dan stok buku langsung dipotong.

---

### 4.3 Riwayat Peminjaman (History)

Untuk melihat daftar riwayat peminjaman yang sudah selesai atau ditolak:

- **Endpoint:** `GET /api/v1/staff/borrowings/history`
- **Query Parameters:**
  - `status` *(opsional)*: `RETURNED`, `REJECTED`, dll.
  - `page`: Nomor halaman (default 1)
  - `per_page`: Item per halaman (default 10)

---

## 📦 5. Format Standar Objek Data Transaksi Peminjaman

Struktur JSON transaksi peminjaman yang dikembalikan oleh API:

```json
{
  "id": 8,
  "transaction_code": "BOR-20261012-0008",
  "user_id": 2,
  "borrower_name": "Budi Santoso",
  "borrower_phone": "081234567890",
  "borrower_identity_number": "NIS-202401",
  "borrower_type": "student",
  "status": "BORROWED",
  "borrowed_at": "2026-10-12T10:00:00.000000Z",
  "due_date": "2026-10-15T10:00:00.000000Z",
  "returned_at": null,
  "notes": null,
  "user": {
    "id": 2,
    "name": "Budi Santoso",
    "identity_number": "NIS-202401",
    "role": "student"
  },
  "items": [
    {
      "id": 12,
      "book_id": 1,
      "quantity": 1,
      "book": {
        "id": 1,
        "title": "Bumi Manusia",
        "author": "Pramoedya Ananta Toer",
        "cover": "http://43.173.6.21:8080/storage/covers/sample.jpg"
      }
    }
  ]
}
```

---

## ⚠️ 6. Penanganan Error (Error Responses)

Backend selalu mengembalikan format error standar:

### A. Validasi Form Gagal (`422 Unprocessable Content`)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "isbn": [
      "The isbn has already been taken."
    ],
    "cover": [
      "The cover field must be an image."
    ]
  }
}
```

### B. Transisi Status Tidak Valid (`422 Unprocessable Content`)
Contoh: Memanggil konfirmasi `/borrow` sebelum status di-`/approve`:
```json
{
  "success": false,
  "message": "Perubahan status dari PENDING ke BORROWED tidak diizinkan."
}
```

### C. Stok Tidak Mencukupi (`422 Unprocessable Content`)
```json
{
  "success": false,
  "message": "Stok buku 'Bumi Manusia' tidak mencukupi (sisa: 0, diminta: 1)."
}
```

### D. Token Tidak Valid / Expired (`401 Unauthorized`)
```json
{
  "message": "Unauthenticated."
}
```
*Frontend harus mengarahkan pengguna kembali ke halaman Login saat menerima status 401.*
