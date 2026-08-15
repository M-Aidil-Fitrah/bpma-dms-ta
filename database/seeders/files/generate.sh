#!/usr/bin/env bash
#
# Membangkitkan berkas contoh untuk seed dokumen.
#
# Dijalankan SEKALI saat penyusunan, dan hasilnya ikut di-commit. Anggota tim
# tidak perlu menjalankan skrip ini — cukup `git pull`. Aplikasinya sendiri
# tidak bertambah satu pun dependensi; perkakas di bawah hanya dibutuhkan mesin
# yang membangkitkan berkasnya.
#
# Berkas dibangkitkan, bukan diunduh dari internet: tautan bisa mati,
# lisensinya tidak jelas, dan isinya tidak terkendali. Dengan dibangkitkan
# sendiri, isi dokumen dapat dikarang sesuai konteks BPMA dan — yang terpenting
# untuk pengujian — hasil OCR-nya dapat diprediksi.
#
# Perkakas yang dibutuhkan:
#   libreoffice  DOCX dan PDF berlapis teks
#   ghostscript  merasterisasi PDF menjadi PDF tanpa lapisan teks
#   imagemagick  gambar bernaskah untuk OCR
#   ffmpeg       video contoh
#   zip          arsip contoh
#   tesseract    memverifikasi hasil OCR (opsional, hanya untuk pemeriksaan)
#
# Pemakaian:  bash database/seeders/files/generate.sh

set -euo pipefail
cd "$(dirname "$0")"

SUMBER="sumber"
BATAS_MB=3

info() { printf '\033[0;36m→\033[0m %s\n' "$1"; }
sukses() { printf '\033[0;32m✓\033[0m %s\n' "$1"; }
gagal() { printf '\033[0;31m✗\033[0m %s\n' "$1" >&2; exit 1; }

for alat in soffice gs magick ffmpeg zip fc-match; do
    command -v "$alat" >/dev/null 2>&1 || gagal "Perkakas '$alat' tidak ditemukan."
done

# Jalur font diselesaikan lewat fontconfig, bukan ditulis mati.
# Nama font seperti "DejaVu-Sans" tidak tersedia di semua distribusi; menuliskan
# namanya langsung membuat ImageMagick diam-diam memakai font cadangan dan
# hasilnya berbeda antar mesin — hal yang justru fatal untuk berkas uji OCR.
FONT_MONO=$(fc-match -f '%{file}' 'monospace')
FONT_SANS=$(fc-match -f '%{file}' 'sans-serif')
FONT_SANS_BOLD=$(fc-match -f '%{file}' 'sans-serif:bold')
[[ -f $FONT_MONO && -f $FONT_SANS && -f $FONT_SANS_BOLD ]] \
    || gagal 'Font sistem tidak ditemukan. Pasang paket font dasar terlebih dulu.'

info 'Membersihkan hasil sebelumnya'
rm -f -- *.pdf *.docx *.txt *.jpg *.png *.mp4 *.zip

# --- 1, 2: PDF berlapis teks -------------------------------------------------
# Isi dapat dibaca `smalot/pdfparser`, sehingga extraction_status menjadi
# `completed` dengan teks terisi.
info 'PDF berlapis teks'
for nama in sop-pengendalian-dokumen laporan-realisasi-anggaran; do
    soffice --headless --convert-to pdf --outdir . "$SUMBER/$nama.html" >/dev/null 2>&1
done

# --- 3: PDF hasil pindaian ---------------------------------------------------
# Dirasterisasi lebih dulu sehingga lapisan teksnya hilang sama sekali. Ini yang
# membuktikan FR-32c: pdfparser mengembalikan string kosong tanpa melempar
# galat, sehingga statusnya `completed` dengan teks kosong — bukan `failed`,
# karena memang tidak ada yang gagal, hanya tidak ada teks untuk diambil.
info 'PDF hasil pindaian (tanpa lapisan teks)'
soffice --headless --convert-to pdf --outdir . "$SUMBER/nota-dinas-sumber.html" >/dev/null 2>&1
gs -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -r120 \
   -sOutputFile=halaman-%d.jpg nota-dinas-sumber.pdf
magick halaman-*.jpg -quality 70 nota-dinas-hasil-pindai.pdf
rm -f halaman-*.jpg nota-dinas-sumber.pdf

# --- 4, 5: DOCX --------------------------------------------------------------
# Dibaca `phpoffice/phpword`; juga menjadi bahan uji pratinjau lewat
# extracted_text, karena DOCX tidak dirender ulang dalam format aslinya.
info 'DOCX'
# Filter ditulis eksplisit: LibreOffice memperlakukan HTML sebagai dokumen
# Writer/Web, dan untuk jenis itu tidak ada filter ekspor `docx` bawaan —
# `--convert-to docx` saja akan berhenti dengan "no export filter".
for nama in notulen-rapat-koordinasi rencana-kerja-anggaran; do
    soffice --headless --convert-to 'docx:MS Word 2007 XML' --outdir . \
        "$SUMBER/$nama.html" >/dev/null 2>&1
done

# --- 6: TXT ------------------------------------------------------------------
info 'TXT'
cat > daftar-inventaris-aset.txt <<'TXT'
DAFTAR INVENTARIS ASET
Divisi Pengelolaan Aset dan Rantai Suplai
Badan Pengelola Migas Aceh
Per 31 Desember 2026

KODE       NAMA ASET                          JUMLAH  KONDISI
AST-0101   Perangkat komputer kerja           42      Baik
AST-0102   Perangkat komputer jinjing         18      Baik
AST-0103   Pencetak dokumen                   9       Baik
AST-0104   Pemindai dokumen                   6       Baik
AST-0201   Kendaraan operasional roda empat   7       Baik
AST-0202   Kendaraan operasional roda dua     4       Perlu servis
AST-0301   Perangkat radio komunikasi         12      Baik
AST-0302   Perangkat ukur tekanan lapangan    8       Kalibrasi
AST-0401   Meja kerja                         55      Baik
AST-0402   Kursi kerja                        60      Baik
AST-0403   Lemari arsip                       23      Baik

Catatan:
- Aset berkondisi "Perlu servis" dijadwalkan pada triwulan berikutnya.
- Perangkat ukur lapangan wajib dikalibrasi ulang setiap dua belas bulan.

Dokumen contoh - data dummy untuk prototype DMS BPMA.
TXT

# --- 7, 8, 9: Gambar ---------------------------------------------------------
# Nomor 7 dan 8 bernaskah, dipakai membuktikan OCR Tesseract bekerja untuk
# bahasa Indonesia. Nomor 9 nyaris tanpa teks: hasil OCR-nya minim, dan itu
# bukan kegagalan — statusnya tetap `completed`.
info 'Gambar bernaskah untuk OCR'
magick -size 1240x1754 xc:white \
    -font "$FONT_MONO" -pointsize 30 -fill '#111111' \
    -annotate +90+130 "$(cat "$SUMBER/naskah-nota-dinas.txt")" \
    -quality 82 nota-dinas-foto.jpg

magick -size 1000x1400 xc:'#fefefe' \
    -font "$FONT_SANS_BOLD" -pointsize 30 -fill '#0b3d2e' \
    -annotate +70+120 "$(cat "$SUMBER/naskah-papan-informasi.txt")" \
    papan-informasi-lapangan.png

info 'Gambar nyaris tanpa teks'
magick -size 1200x800 gradient:'#4a6d8c'-'#c9b79c' \
    -font "$FONT_SANS" -pointsize 26 -fill white \
    -annotate +40+760 'Blok A' \
    -quality 78 foto-fasilitas-produksi.jpg

# --- 10: Video ---------------------------------------------------------------
# Tipe tak didukung: extraction_status langsung `not_applicable` dan job tidak
# pernah dibuat (Kriteria Penerimaan #14).
info 'Video contoh'
# Kartu judul dibuat ImageMagick lebih dulu, lalu ffmpeg tinggal menahannya
# beberapa detik. Filter `drawtext` sengaja dihindari: jalur font pada sebagian
# distribusi memuat tanda kurung siku (mis. `NotoSans[wght].ttf`) yang punya
# arti khusus di sintaks filtergraph, sehingga perintahnya gagal terurai.
magick -size 640x360 xc:'#1d3c8f' \
    -font "$FONT_SANS_BOLD" -pointsize 26 -fill white \
    -gravity center -annotate +0+0 'Rekaman Rapat Koordinasi' \
    kartu-judul.png

ffmpeg -y -loglevel error -loop 1 -i kartu-judul.png -t 4 -r 12 \
    -pix_fmt yuv420p -movflags +faststart rekaman-rapat.mp4
rm -f kartu-judul.png

# --- 11: Arsip ---------------------------------------------------------------
info 'Arsip contoh'
zip -q arsip-lampiran-pendukung.zip daftar-inventaris-aset.txt

# --- Pemeriksaan hasil -------------------------------------------------------
rm -f -- *_html.pdf 2>/dev/null || true

echo
info 'Memverifikasi hasil OCR (cuplikan)'
if command -v tesseract >/dev/null 2>&1; then
    tesseract nota-dinas-foto.jpg - -l ind+eng 2>/dev/null | grep -v '^$' | head -4
else
    echo '  (tesseract tidak terpasang, verifikasi dilewati)'
fi

echo
info 'Berkas yang dihasilkan'
ls -lh --time-style=+ | awk 'NR>1 && $NF!="sumber" && $NF!="generate.sh" {printf "  %-38s %s\n", $NF, $5}'

UKURAN_KB=$(du -sk --exclude=sumber . | cut -f1)
UKURAN_MB=$(awk "BEGIN{printf \"%.2f\", $UKURAN_KB/1024}")
echo
if (( UKURAN_KB > BATAS_MB * 1024 )); then
    gagal "Total ${UKURAN_MB} MB melebihi batas ${BATAS_MB} MB. Perkecil berkas sebelum commit."
fi
sukses "Total ${UKURAN_MB} MB (batas ${BATAS_MB} MB)"

echo
echo 'Catatan: berkas HEIC tidak dapat dibangkitkan di sini — ImageMagick'
echo 'memerlukan delegate libheif. Ambil satu foto dari iPhone dengan setelan'
echo 'kamera bawaan, lalu salin ke folder ini sebagai foto-kamera-iphone.heic.'
