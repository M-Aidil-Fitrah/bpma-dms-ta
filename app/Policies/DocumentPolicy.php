<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentEditScope;
use App\Models\Document;
use App\Models\User;

/**
 * Otorisasi dokumen, dipusatkan di satu tempat (`PRD.md` §2.6).
 *
 * Seluruh method mendelegasikan pemeriksaan "boleh melihat" ke scope
 * `Document::visibleTo()` — satu sumber kebenaran yang sama dengan yang dipakai
 * daftar, pencarian, dan dasbor. Menyalin aturannya ke sini akan membuat dua
 * salinan yang cepat atau lambat menyimpang.
 *
 * Policy ini wajib dipanggil di setiap aksi yang menyentuh dokumen lewat
 * `$this->authorize()`. Menyembunyikan tombol di antarmuka bukan proteksi —
 * alamatnya tetap dapat diketik langsung (FR-43).
 */
final class DocumentPolicy
{
    /**
     * Setiap pengguna yang sudah masuk boleh membuka halaman daftar; isinya
     * yang disaring, bukan aksesnya yang ditutup.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Setiap pengguna aktif boleh mengunggah dokumen (FR-06).
     *
     * Tidak ada pembatasan berdasarkan jabatan atau unit: siapa pun yang punya
     * akun berhak menerbitkan dokumen dari unitnya. Yang dibatasi adalah siapa
     * yang dapat MELIHATNYA — dan itu ditentukan mekanisme akses yang dipilih
     * pengunggah, bukan oleh siapa yang boleh mengunggah.
     *
     * Method ini wajib ada meski isinya sederhana. Policy tanpa method yang
     * dipanggil `authorize()` berarti penolakan — seluruh pengguna, termasuk
     * Superadmin, akan menerima 403 tanpa penjelasan.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Dokumen nonaktif hanya dapat dibuka Superadmin.
     *
     * Menyembunyikannya dari daftar saja tidak cukup: alamatnya tetap dapat
     * diketik langsung, dan tautan lama masih tersimpan di riwayat peramban
     * maupun di surel (FR-43). Superadmin tetap boleh membukanya karena dialah
     * satu-satunya yang dapat mengaktifkannya kembali — tanpa itu dokumen yang
     * keliru dinonaktifkan menjadi mustahil dipulihkan lewat antarmuka.
     */
    public function view(User $user, Document $document): bool
    {
        if (! $document->is_active && ! $user->isSuperadmin()) {
            return false;
        }

        return Document::query()
            ->visibleTo($user)
            ->whereKey($document->getKey())
            ->exists();
    }

    /**
     * Wewenang menyunting ditentukan `edit_scope`, terpisah dari hak melihat.
     *
     * Sebuah dokumen bisa terlihat banyak orang tapi hanya boleh disunting
     * pengunggahnya — dua hal yang sengaja dipisah (`Catatan_Audit.md` isu #1).
     */
    public function update(User $user, Document $document): bool
    {
        // Dokumen nonaktif dibekukan. Menyuntingnya berarti mengubah sesuatu
        // yang sudah dinyatakan tidak berlaku, dan perubahannya tidak akan
        // terlihat siapa pun — kecuali kelak dokumen itu diaktifkan kembali dan
        // ternyata isinya sudah berbeda dari yang dulu dinonaktifkan.
        if (! $document->is_active && ! $user->isSuperadmin()) {
            return false;
        }

        if ($user->bypassesDocumentAccess()) {
            return true;
        }

        return match ($document->edit_scope) {
            DocumentEditScope::OwnerOnly => $document->uploaded_by === $user->id,
            DocumentEditScope::MatchVisibility => $this->view($user, $document),
        };
    }

    /**
     * Menonaktifkan dokumen mengikuti aturan yang sama dengan menyunting.
     *
     * Keduanya sama-sama mengubah apa yang dilihat orang lain, jadi tidak ada
     * alasan wewenangnya dibedakan. Dokumen tidak pernah dihapus permanen —
     * hanya ditandai nonaktif, sehingga riwayatnya tetap utuh untuk audit
     * (FR-10).
     */
    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Mengaktifkan kembali dokumen yang dinonaktifkan.
     *
     * Sengaja dibatasi Superadmin: dokumen nonaktif tidak tampil di daftar mana
     * pun, sehingga pengguna biasa tidak punya jalan menemukannya — dan tidak
     * seharusnya punya jalan mengembalikannya.
     */
    public function restore(User $user, Document $document): bool
    {
        return $user->isSuperadmin();
    }
}
