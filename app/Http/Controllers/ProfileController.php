<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /*
     * Aksi `destroy` bawaan Breeze sengaja dihapus.
     *
     * Pengguna tidak dapat menghapus akunnya sendiri: penonaktifan akun adalah
     * wewenang Superadmin (FR-27), dan akun tidak pernah dihapus permanen
     * supaya riwayat aktivitas serta dokumen yang pernah diunggahnya tetap
     * utuh. Foreign key `documents.uploaded_by` memakai RESTRICT, sehingga
     * penghapusan akun juga akan ditolak di tingkat basis data.
     */
}
