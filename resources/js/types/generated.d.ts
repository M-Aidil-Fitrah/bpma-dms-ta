declare namespace App {
namespace Data {
export type AuthUserData = {
id: number,
name: string,
email: string,
jabatan: string | null,
tingkat_akses: number | null,
unit: string | null,
is_superadmin: boolean,
initials: string,
};
export type DashboardData = {
total: number,
berlaku: number,
kadaluarsa: number,
jumlah_mendekati_evaluasi: number,
per_kategori: App.Data.KategoriRingkasData[],
terbaru: App.Data.DocumentListData[],
mendekati_evaluasi: App.Data.DocumentListData[],
rentang_evaluasi: number,
rentang_pilihan: number[],
};
export type DocumentDetailData = {
id: number,
nomor: string,
judul: string,
deskripsi: string | null,
kategori: string | null,
unit_asal: string | null,
tanggal: string,
masa_berlaku: string | null,
status: App.Enums.DocumentStatus,
nama_berkas: string,
tipe_berkas: string,
ukuran_berkas: number,
extraction_status: App.Enums.ExtractionStatus,
isi_teks: string | null,
pengunggah: string | null,
jabatan_pengunggah: string | null,
unit_pengunggah: string | null,
inisial_pengunggah: string,
diunggah_pada: string,
diperbarui_pada: string,
ringkasan_akses: string[],
dibagikan_ke_semua: boolean,
min_tingkat_akses: number | null,
unit_tujuan: string[],
orang_tertentu: string[],
edit_scope: App.Enums.DocumentEditScope,
label_edit_scope: string,
aktif: boolean,
boleh_ubah: boolean,
boleh_nonaktifkan: boolean,
boleh_aktifkan: boolean,
};
export type DocumentEditData = {
id: number,
nomor: string,
judul: string,
deskripsi: string | null,
category_id: number | null,
origin_unit_id: number | null,
tanggal: string,
masa_berlaku: string | null,
is_shared_to_all: boolean,
min_tingkat_akses: number | null,
unit_ids: number[],
orang_tertentu: {
id: number,
nama: string,
jabatan: string | null,
unit: string | null,
}[],
edit_scope: App.Enums.DocumentEditScope,
nama_berkas: string,
tipe_berkas: string,
ukuran_berkas: number,
};
export type DocumentListData = {
id: number,
nomor: string,
judul: string,
kategori: string | null,
unit_asal: string | null,
tanggal: string,
masa_berlaku: string | null,
status: App.Enums.DocumentStatus,
extraction_status: App.Enums.ExtractionStatus,
tipe_berkas: string,
ukuran_berkas: number,
pengunggah: string | null,
jabatan_pengunggah: string | null,
inisial_pengunggah: string,
ringkasan_akses: string[] | null,
alasan_terlihat: string | null,
};
export type KategoriRingkasData = {
id: number,
nama: string,
jumlah: number,
};
export type PengaturanFormData = {
unggah_batas_kb: number,
unggah_batas_kb_bawaan: number,
unggah_batas_efektif_kb: number | null,
unggah_dibatasi_php: boolean,
dokumen_per_halaman: number,
dokumen_per_halaman_bawaan: number,
dokumen_rentang_evaluasi_awal: number,
dokumen_rentang_evaluasi_awal_bawaan: number,
rentang_evaluasi_pilihan: number[],
};
export type ReferensiEditData = {
id: number,
nama: string,
is_active: boolean,
tingkat_akses: number | null,
parent_id: number | null,
tipe: string | null,
deskripsi: string | null,
};
export type ReferensiListData = {
id: number,
nama: string,
jenis: string,
keterangan: string | null,
is_active: boolean,
kedalaman: number,
dampak_nonaktif: string[],
};
export type UserEditData = {
id: number,
name: string,
email: string,
jabatan_id: number | null,
unit_id: number | null,
is_active: boolean,
};
export type UserListData = {
id: number,
name: string,
email: string,
jabatan: string | null,
unit: string | null,
is_active: boolean,
inisial: string,
};
}
namespace Enums {
export type DocumentEditScope = 'owner_only' | 'match_visibility';
export type DocumentStatus = 'berlaku' | 'kadaluarsa';
export type ExtractionStatus = 'not_applicable' | 'pending' | 'completed' | 'failed';
}
}
