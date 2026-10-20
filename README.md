# Sistem Informasi Akademik (SIAKAD)

Dokumentasi ini merangkum Business Requirement Document (BRD) SIAKAD versi
1.0, tanggal 7 Oktober 2026, serta menjelaskan bagian yang sudah tersedia di
repository. Dokumen BRD lengkap tidak disertakan; README ini berisi ringkasan
kebutuhan dan rancangan agar konteks laporan dapat dibaca langsung di GitHub.

## Gambaran umum

SIAKAD dirancang untuk mengelola siklus administrasi akademik perguruan tinggi:
registrasi mahasiswa, kurikulum dan mata kuliah, Kartu Rencana Studi (KRS),
penjadwalan, presensi, penilaian, Kartu Hasil Studi (KHS), transkrip, dan
kelulusan.

Tujuan utamanya:

- Menjaga integritas, konsistensi, dan validitas data akademik.
- Memudahkan registrasi dan pengisian KRS secara mandiri tanpa bentrok jadwal.
- Menyediakan informasi akademik bagi mahasiswa, dosen, dan pimpinan.

## Pemangku kepentingan

| Pemangku kepentingan | Kebutuhan utama |
| --- | --- |
| Mahasiswa | Mengisi KRS, melihat jadwal, memantau presensi, dan mengakses KHS/transkrip. |
| Dosen pengampu | Mengelola jurnal perkuliahan, presensi, dan nilai komponen. |
| Dosen Pembimbing Akademik (DPA) | Meninjau serta menyetujui atau menolak KRS mahasiswa bimbingan. |
| Admin akademik / tata usaha | Mengelola data fakultas, program studi, mata kuliah, ruangan, dosen, mahasiswa, dan kurikulum. |
| Pimpinan universitas / kaprodi | Melihat statistik akademik, analisis kelulusan, dan evaluasi beban kerja dosen. |

## Kebutuhan fungsional dalam BRD

1. **FR-01 — Pengguna dan autentikasi:** hak akses berdasarkan peran mahasiswa,
   dosen, admin, dan kaprodi.
2. **FR-02 — Master data akademik:** pengelolaan data mahasiswa, dosen,
   fakultas, program studi, kurikulum, dan ruangan.
3. **FR-03 — Mata kuliah dan prasyarat:** pengaturan SKS, jenis mata kuliah,
   serta keterkaitan mata kuliah prasyarat.
4. **FR-04 — Jadwal dan ruang:** penjadwalan per semester dengan pencegahan
   bentrok kelas, ruang, maupun dosen.
5. **FR-05 — Pengisian dan perubahan KRS:** pemilihan kelas sesuai kuota SKS
   mahasiswa.
6. **FR-06 — Persetujuan KRS:** DPA memvalidasi dan menyetujui atau menolak
   pengajuan KRS.
7. **FR-07 — Presensi:** pencatatan kehadiran mahasiswa dan dosen selama 16
   pertemuan.
8. **FR-08 — Penilaian:** input nilai tugas, UTS, dan UAS serta perhitungan
   nilai akhir dan nilai huruf.
9. **FR-09 — Hasil studi:** perhitungan IPS/IPK dan penerbitan KHS atau
   transkrip.
10. **FR-10 — Audit nilai:** riwayat perubahan nilai yang mencatat waktu dan
    identitas dosen.

## Rancangan data

Entitas utama yang disebutkan di BRD:

- **Mahasiswa** — NIM, nama, email, alamat, tanggal lahir, program studi, dan
  DPA.
- **Dosen** — NID, nama, email, nomor telepon, dan program studi.
- **Mata kuliah** — kode, nama, SKS, semester tawaran, dan program studi.
- **Kelas perkuliahan** — mata kuliah, dosen pengampu, semester, jadwal, ruang,
  dan kuota.
- **KRS** — mahasiswa, semester, tanggal pengajuan, dan status persetujuan
  (Draft, Approved, atau Rejected).
- **Detail KRS / penilaian** — kelas yang diambil, nilai tugas, UTS, UAS, nilai
  angka akhir, dan nilai huruf.

Relasi intinya: program studi memiliki mahasiswa, dosen, dan mata kuliah;
mahasiswa memiliki KRS; KRS terdiri dari detail kelas; dan setiap detail KRS
terhubung dengan kelas perkuliahan. Rancangan tabel proyek tersedia di
[`database/schema.dbml`](database/schema.dbml).

BRD mengarahkan normalisasi sampai 3NF, penggunaan foreign key untuk menjaga
referensi data, serta validasi domain seperti nilai 0–100 dan nilai huruf A–E.

## Aturan bisnis

- Batas SKS KRS ditentukan berdasarkan hasil studi semester sebelumnya. BRD
  mencantumkan contoh IPK minimal 3,00 untuk maksimum 24 SKS dan IPS 2,50–2,99
  untuk maksimum 21 SKS; rentang lainnya perlu mengikuti kebijakan kampus.
- Mata kuliah hanya dapat diambil jika prasyaratnya sudah dipenuhi dengan nilai
  minimal D.
- Kelas ditutup saat jumlah peserta mencapai kuota.
- Mahasiswa harus memiliki presensi minimal 75% untuk memenuhi syarat mengikuti
  UAS.

## Target non-fungsional dalam BRD

Target berikut merupakan persyaratan BRD, bukan klaim bahwa semuanya telah
diuji atau dipenuhi oleh aplikasi:

- **Performa:** waktu respons query di bawah 2 detik pada beban puncak yang
  disebutkan sebesar 5.000 pengguna bersamaan saat pengisian KRS.
- **Keamanan:** kredensial di-hash dengan bcrypt/Argon2, koneksi menggunakan
  HTTPS/SSL, dan query mencegah SQL injection.
- **Ketersediaan:** uptime minimal 99,5% dan pencadangan basis data otomatis
  setiap hari.

## Status implementasi

Repository ini menggunakan Laravel dan MySQL. Layanan akademik dan rancangan
database untuk alur KRS, jadwal, penilaian, presensi, serta kelayakan UAS
tersedia pada proyek. Saat ini aturan akademik tersebut belum dihubungkan ke
endpoint HTTP atau antarmuka pengguna. Kebutuhan BRD seperti autentikasi peran,
KHS/transkrip PDF, audit log nilai, integrasi pembayaran, target performa, dan
operasional backup perlu diperlakukan sebagai pekerjaan lanjutan sampai
implementasinya diverifikasi.

Seeder laporan menghasilkan 2.000 mahasiswa dummy beserta data akademik
terkait—termasuk KRS, nilai, dan 16 catatan presensi per mata kuliah—dengan
total lebih dari 100.000 baris. Data ditujukan untuk pengujian dan demonstrasi,
bukan data mahasiswa sungguhan.

## Menjalankan proyek

1. Salin `.env.example` menjadi `.env`, lalu atur koneksi MySQL sesuai
   lingkungan lokal.
2. Buat database MySQL yang namanya sesuai dengan `DB_DATABASE` di `.env`.
3. Pasang dependency dan siapkan aplikasi:

   ```bash
   composer install
   php artisan key:generate
   php artisan migrate --seed
   ```

Seeder laporan dapat dijalankan ulang secara terpisah:

```bash
php artisan db:seed --class=LaporanDataSeeder
```

Jalankan pengujian dengan:

```bash
php artisan test
```
