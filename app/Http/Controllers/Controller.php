<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /*
     * Sejak Laravel 11, controller dasar tidak lagi menyertakan trait ini.
     * Ditambahkan di sini, bukan di tiap controller, karena otorisasi lewat
     * `$this->authorize()` wajib di setiap aksi yang menyentuh dokumen (FR-43)
     * — menyertakannya satu per satu berarti cepat atau lambat ada yang lupa,
     * dan controller yang lupa memanggil otorisasi tidak menampakkan gejala
     * apa pun sampai ada yang mencoba membuka data orang lain.
     */
    use AuthorizesRequests;
}
