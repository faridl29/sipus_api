# 📚 Panduan Integrasi REST API - Frontend Siswa / Publik (SIPUS)

Dokumentasi ini ditujukan bagi pengembang Frontend Siswa / Web Publik (**SIPUS**) untuk mengintegrasikan antarmuka katalog buku dan portal peminjaman mandiri siswa dengan REST API backend.

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

## 📖 Alur Kerja Peminjaman Siswa (User Journey)

1. **Eksplorasi Katalog (Publik / OPAC):**
   - Siswa atau pengunjung umum dapat melihat katalog buku dan melakukan pencarian tanpa perlu login.
2. **Login Siswa:**
   - Saat ingin meminjam buku atau melihat riwayat peminjaman, siswa login menggunakan NIS (`identity_number`) dan password.
3. **Pengajuan Peminjaman (`POST /api/v1/borrowings`):**
   - Siswa memilih satu atau beberapa buku (maksimal durasi pinjam 3 hari).
   - Status peminjaman pertama kali adalah **`PENDING`**.
4. **Persetujuan & Pengambilan:**
   - Petugas perpustakaan akan memvalidasi pengajuan (`APPROVED`) atau menolak (`REJECTED`).
   - Siswa datang ke perpustakaan untuk mengambil buku fisik, petugas menyerahkan dan mengubah status menjadi **`BORROWED`**.
5. **Pengembalian:**
   - Siswa mengembalikan buku sebelum atau tepat pada `due_date`, petugas menandai status **`RETURNED`**.

---

## 🔐 1. Modul Autentikasi Siswa (`/auth`)

### 1.1 Login Siswa
- **Endpoint:** `POST /api/v1/auth/login`
- **Akses:** Publik (tanpa token)
- **Header:**
  ```http
  Content-Type: application/json
  Accept: application/json
  ```
- **Request Body:**
  ```json
  {
    "identity_number": "202410001",
    "password": "password"
  }
  ```
  > **Catatan:** Login siswa menggunakan Nomor Induk Siswa (**NIS** / `identity_number`). Akun default seeder: `202410001` / `password` atau `202410002` / `password`.

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Login successful",
    "data": {
      "user": {
        "id": 2,
        "name": "Siswa Perpustakaan",
        "email": "student@example.com",
        "identity_number": "202410001",
        "role": "student",
        "created_at": "2026-10-21T07:00:00.000000Z",
        "updated_at": "2026-10-21T07:00:00.000000Z"
      },
      "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    }
  }
  ```
  *Simpan token ini di `localStorage` / cookie untuk disertakan pada setiap request berikutnya.*

---

### 1.2 Profil Siswa (Check Session)
Digunakan saat inisialisasi aplikasi untuk mengecek apakah token masih aktif dan mendapatkan profil siswa yang sedang login.

- **Endpoint:** `GET /api/v1/auth/me`
- **Akses:** Memerlukan Token Siswa
- **Header:**
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "User profile retrieved successfully",
    "data": {
      "id": 2,
      "name": "Siswa Perpustakaan",
      "email": "student@example.com",
      "identity_number": "202410001",
      "role": "student",
      "created_at": "2026-10-21T07:00:00.000000Z",
      "updated_at": "2026-10-21T07:00:00.000000Z"
    }
  }
  ```

---

### 1.3 Logout
- **Endpoint:** `POST /api/v1/auth/logout`
- **Akses:** Memerlukan Token Siswa
- **Header:**
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```
- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Logout successful"
  }
  ```

---

## 📚 2. Modul Katalog Buku (OPAC - Online Public Access Catalog)

Semua endpoint katalog buku dapat diakses **tanpa login**.

### 2.1 Menampilkan Daftar Katalog Buku
- **Endpoint:** `GET /api/v1/books`
- **Akses:** Publik (tanpa login)
- **Query Parameters:**
  | Parameter | Tipe | Default | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `search` | String | - | Pencarian berdasarkan judul, penulis, penerbit, atau ISBN |
  | `available_only` | Boolean | `false` | `true` atau `1` untuk hanya menampilkan buku yang stoknya `> 0` |
  | `per_page` | Integer | `10` | Jumlah data per halaman (min 1, max 100) |
  | `page` | Integer | `1` | Halaman yang ingin dibuka |

- **Contoh Request:**
  `GET /api/v1/books?search=laskar&available_only=true&per_page=10&page=1`

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Books retrieved successfully",
    "data": [
      {
        "id": 1,
        "title": "Laskar Pelangi",
        "author": "Andrea Hirata",
        "publisher": "Bentang Pustaka",
        "publication_year": 2005,
        "isbn": "978-979-3062-79-2",
        "stock": 10,
        "is_available": true,
        "description": "Kisah perjuangan sepuluh anak di Belitung dalam menempuh pendidikan dasar.",
        "cover": "https://images.unsplash.com/photo-1544947950-fa07a98d237f",
        "created_at": "2026-10-21T07:00:00.000000Z",
        "updated_at": "2026-10-21T07:00:00.000000Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "per_page": 10,
      "to": 1,
      "total": 1
    },
    "links": {
      "first": "http://43.173.6.21:8080/api/v1/books?page=1",
      "last": "http://43.173.6.21:8080/api/v1/books?page=1",
      "prev": null,
      "next": null
    }
  }
  ```

---

### 2.2 Detail Buku
- **Endpoint:** `GET /api/v1/books/{id}`
- **Akses:** Publik (tanpa login)
- **Path Parameter:**
  - `id`: ID buku (Integer, misal: `1`)

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Book retrieved successfully",
    "data": {
      "id": 1,
      "title": "Laskar Pelangi",
      "author": "Andrea Hirata",
      "publisher": "Bentang Pustaka",
      "publication_year": 2005,
      "isbn": "978-979-3062-79-2",
      "stock": 10,
      "is_available": true,
      "description": "Kisah perjuangan sepuluh anak di Belitung dalam menempuh pendidikan dasar.",
      "cover": "https://images.unsplash.com/photo-1544947950-fa07a98d237f",
      "created_at": "2026-10-21T07:00:00.000000Z",
      "updated_at": "2026-10-21T07:00:00.000000Z"
    }
  }
  ```

- **Response Buku Tidak Ditemukan (`404 Not Found`):**
  ```json
  {
    "success": false,
    "message": "Resource not found."
  }
  ```

---

## 📋 3. Modul Peminjaman Siswa (`/borrowings`)

Semua endpoint di bawah ini **wajib menyertakan Token Siswa** (`role: student`).

### 3.1 Ajukan Peminjaman Buku Mandiri
Siswa dapat mengajukan pinjaman satu atau beberapa judul buku sekaligus dalam 1 transaksi.

- **Endpoint:** `POST /api/v1/borrowings`
- **Akses:** Wajib Login (Role Siswa)
- **Header:**
  ```http
  Authorization: Bearer <token>
  Content-Type: application/json
  Accept: application/json
  ```
- **Ketentuan Aturan Pinjam:**
  - `items`: Minimal 1 buku, maksimal **2 buku** per orang, array objek berisi `book_id` dan `quantity`. Buku yang sama tidak boleh diduplikasi dalam request.
  - Kuota Peminjaman: Siswa maksimal memiliki total **2 buku aktif** (status `PENDING`, `APPROVED`, atau `BORROWED`). Pengajuan akan ditolak jika akumulasi buku aktif dan buku yang baru diajukan melebihi 2 buku.
  - Bebas Tunggakan: Siswa tidak boleh memiliki peminjaman aktif yang sudah melewati batas waktu (*overdue*).
  - `due_date`: Opsional. Format tanggal `YYYY-MM-DD`. Maksimal **3 hari** dari hari ini. Jika tidak diisi, backend otomatis menyetel 3 hari ke depan.
  - `notes`: Opsional (catatan alasan pinjam, maksimal 1000 karakter).
  - Status peminjaman otomatis disetel ke **`PENDING`**.

- **Request Body:**
  ```json
  {
    "due_date": "2026-10-25",
    "notes": "Untuk referensi tugas makalah bahasa Indonesia",
    "items": [
      {
        "book_id": 1,
        "quantity": 1
      },
      {
        "book_id": 3,
        "quantity": 1
      }
    ]
  }
  ```

- **Response Berhasil (`201 Created`):**
  ```json
  {
    "success": true,
    "message": "Borrowing request created successfully",
    "data": {
      "id": 15,
      "transaction_code": "BRW-20261022-0015",
      "user_id": 2,
      "borrower_name": "Siswa Perpustakaan",
      "borrower_phone": null,
      "borrower_identity_number": "202410001",
      "borrower_type": "student",
      "status": "PENDING",
      "borrowed_at": null,
      "due_date": "2026-10-25T00:00:00.000000Z",
      "returned_at": null,
      "is_overdue": false,
      "notes": "Untuk referensi tugas makalah bahasa Indonesia",
      "items": [
        {
          "id": 21,
          "book_id": 1,
          "quantity": 1,
          "book": {
            "id": 1,
            "title": "Laskar Pelangi",
            "author": "Andrea Hirata",
            "publisher": "Bentang Pustaka",
            "publication_year": 2005,
            "isbn": "978-979-3062-79-2",
            "stock": 10,
            "is_available": true,
            "description": "Kisah perjuangan sepuluh anak di Belitung dalam menempuh pendidikan dasar.",
            "cover": "https://images.unsplash.com/photo-1544947950-fa07a98d237f"
          }
        },
        {
          "id": 22,
          "book_id": 3,
          "quantity": 1,
          "book": {
            "id": 3,
            "title": "Negeri 5 Menara",
            "author": "Ahmad Fuadi",
            "publisher": "Gramedia Pustaka Utama",
            "publication_year": 2009,
            "isbn": "978-979-22-4861-6",
            "stock": 8,
            "is_available": true,
            "description": "Kisah persahabatan santri di Pondok Madani dengan filosofi Man Jadda Wajada.",
            "cover": "https://images.unsplash.com/photo-1532012164546-f432f2e3777f"
          }
        }
      ],
      "created_at": "2026-10-22T22:15:00.000000Z",
      "updated_at": "2026-10-22T22:15:00.000000Z"
    }
  }
  ```

---

### 3.2 Riwayat & Daftar Peminjaman Saya
Menampilkan seluruh riwayat transaksi peminjaman milik siswa yang sedang login.

- **Endpoint:** `GET /api/v1/borrowings/me`
- **Akses:** Wajib Login (Role Siswa)
- **Header:**
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```
- **Query Parameters:**
  | Parameter | Tipe | Nilai yang Didukung | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `status` | String | `PENDING`, `APPROVED`, `REJECTED`, `BORROWED`, `RETURNED` | Filter berdasarkan status |
  | `per_page` | Integer | Default `10` (max `100`) | Jumlah data per halaman |
  | `page` | Integer | Default `1` | Nomor halaman |

- **Contoh Request:**
  `GET /api/v1/borrowings/me?status=BORROWED&per_page=5&page=1`

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Borrowings retrieved successfully",
    "data": [
      {
        "id": 15,
        "transaction_code": "BRW-20261022-0015",
        "user_id": 2,
        "borrower_name": "Siswa Perpustakaan",
        "borrower_phone": null,
        "borrower_identity_number": "202410001",
        "borrower_type": "student",
        "status": "BORROWED",
        "borrowed_at": "2026-10-22T08:30:00.000000Z",
        "due_date": "2026-10-25T00:00:00.000000Z",
        "returned_at": null,
        "is_overdue": false,
        "notes": "Untuk referensi tugas makalah bahasa Indonesia",
        "items": [
          {
            "id": 21,
            "book_id": 1,
            "quantity": 1,
            "book": {
              "id": 1,
              "title": "Laskar Pelangi",
              "author": "Andrea Hirata",
              "cover": "https://images.unsplash.com/photo-1544947950-fa07a98d237f"
            }
          }
        ],
        "created_at": "2026-10-22T22:15:00.000000Z",
        "updated_at": "2026-10-22T08:30:00.000000Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "per_page": 5,
      "to": 1,
      "total": 1
    },
    "links": {
      "first": "http://43.173.6.21:8080/api/v1/borrowings/me?page=1",
      "last": "http://43.173.6.21:8080/api/v1/borrowings/me?page=1",
      "prev": null,
      "next": null
    }
  }
  ```

---

### 3.3 Detail Transaksi Peminjaman
Melihat rincian lengkap dari satu transaksi peminjaman.

- **Endpoint:** `GET /api/v1/borrowings/{id}`
- **Akses:** Wajib Login (Siswa pemilik transaksi atau Petugas)
- **Path Parameter:**
  - `id`: ID transaksi peminjaman (Integer, misal: `15`)
- **Header:**
  ```http
  Authorization: Bearer <token>
  Accept: application/json
  ```

- **Response Berhasil (`200 OK`):**
  ```json
  {
    "success": true,
    "message": "Borrowing retrieved successfully",
    "data": {
      "id": 15,
      "transaction_code": "BRW-20261022-0015",
      "user_id": 2,
      "borrower_name": "Siswa Perpustakaan",
      "borrower_phone": null,
      "borrower_identity_number": "202410001",
      "borrower_type": "student",
      "status": "BORROWED",
      "borrowed_at": "2026-10-22T08:30:00.000000Z",
      "due_date": "2026-10-25T00:00:00.000000Z",
      "returned_at": null,
      "is_overdue": false,
      "notes": "Untuk referensi tugas makalah bahasa Indonesia",
      "user": {
        "id": 2,
        "name": "Siswa Perpustakaan",
        "email": "student@example.com",
        "identity_number": "202410001",
        "role": "student"
      },
      "items": [
        {
          "id": 21,
          "book_id": 1,
          "quantity": 1,
          "book": {
            "id": 1,
            "title": "Laskar Pelangi",
            "author": "Andrea Hirata",
            "publisher": "Bentang Pustaka",
            "publication_year": 2005,
            "isbn": "978-979-3062-79-2",
            "stock": 9,
            "is_available": true,
            "cover": "https://images.unsplash.com/photo-1544947950-fa07a98d237f"
          }
        }
      ],
      "created_at": "2026-10-22T22:15:00.000000Z",
      "updated_at": "2026-10-22T08:30:00.000000Z"
    }
  }
  ```

- **Response Jika Bukan Pemilik (`403 Forbidden`):**
  ```json
  {
    "success": false,
    "message": "This action is unauthorized."
  }
  ```

---

## 🔄 4. Status Transaksi Peminjaman

| Status | Deskripsi | Aksi Siswa di Frontend |
| :--- | :--- | :--- |
| **`PENDING`** | Pengajuan baru dikirim oleh siswa, menunggu verifikasi petugas. | Tampilkan badge kuning/oranye *"Menunggu Persetujuan"*. Siswa masih menunggu konfirmasi. |
| **`APPROVED`** | Pengajuan telah disetujui oleh petugas. | Tampilkan badge hijau/biru *"Disetujui"*. Instruksikan siswa untuk segera ke perpustakaan mengambil buku fisik. |
| **`REJECTED`** | Pengajuan ditolak oleh petugas (misal buku sedang dalam perbaikan / batas kuota pinjam). | Tampilkan badge merah *"Ditolak"*. |
| **`BORROWED`** | Buku fisik sudah diserahkan ke siswa. Waktu peminjaman aktif berjalan. | Tampilkan badge biru *"Sedang Dipinjam"*. Perhatikan field `due_date` dan `is_overdue`. |
| **`RETURNED`** | Buku sudah dikembalikan ke perpustakaan secara lengkap. | Tampilkan badge hijau tua/abu-abu *"Selesai / Dikembalikan"*. |

> **Catatan Pengingat Terlambat (`is_overdue`):**
> Jika `is_overdue: true`, buku telah melewati tanggal `due_date` dan siswa belum mengembalikannya. Frontend disarankan menampilkan notifikasi/badge peringatan berwarna merah.

---

## ⚠️ 5. Penanganan Error (Error Handling)

### A. Validasi Gagal (`422 Unprocessable Content`)
Contoh: Batas pengembalian melebihi 3 hari atau stok buku tidak mencukupi:
```json
{
  "message": "Batas maksimal pengembalian buku adalah 3 hari dari tanggal pengajuan.",
  "errors": {
    "due_date": [
      "Batas maksimal pengembalian buku adalah 3 hari dari tanggal pengajuan."
    ]
  }
}
```

Contoh stok buku habis saat checkout:
```json
{
  "message": "Stok buku 'Buku Langka Habis Stok' tidak mencukupi (tersedia: 0).",
  "errors": {
    "items.0.quantity": [
      "Stok buku 'Buku Langka Habis Stok' tidak mencukupi (tersedia: 0)."
    ]
  }
}
```

### B. Akun Belum Login atau Token Expired (`401 Unauthorized`)
```json
{
  "message": "Unauthenticated."
}
```
*Frontend harus membersihkan token tersimpan dan mengarahkan siswa ke halaman login.*

### C. Hak Akses Ditolak (`403 Forbidden`)
Contoh: Siswa mencoba melihat peminjaman milik siswa lain, atau akun dengan role bukan student mencoba mengajukan pinjaman:
```json
{
  "success": false,
  "message": "Forbidden. You do not have permission to access this resource."
}
```

### D. Data Tidak Ditemukan (`404 Not Found`)
```json
{
  "success": false,
  "message": "Resource not found."
}
```
