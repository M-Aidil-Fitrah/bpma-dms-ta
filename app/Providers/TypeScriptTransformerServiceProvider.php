<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Menggenerate tipe TypeScript dari DTO dan enum PHP.
 *
 * Tujuannya menjaga kontrak data backend–frontend tidak pernah menyimpang:
 * bentuk props Inertia ditetapkan sekali di PHP, lalu diturunkan otomatis ke
 * TypeScript. Perubahan bentuk data yang lupa diikuti frontend akan ketahuan
 * saat `npm run build`, bukan saat halaman dibuka.
 *
 * Jalankan `php artisan typescript:transform` setiap kali DTO atau enum
 * berubah, dan ikutkan berkas hasilnya dalam commit.
 *
 * Berkas keluaran `resources/js/types/generated.d.ts` DILARANG disunting tangan
 * — isinya akan ditimpa pada generasi berikutnya.
 */
final class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            // Dibatasi ke Data dan Enums saja, bukan seluruh app/, supaya
            // pemindaian tetap ringan dan tidak ada tipe internal yang bocor
            // ke frontend tanpa disengaja.
            ->transformDirectories(app_path('Data'), app_path('Enums'))
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'));
        // Formatter Prettier sengaja tidak dipakai — akan menggagalkan perintah
        // di laptop yang belum memasang Prettier, dan berkas hasil generate
        // memang tidak dibaca manusia.
    }
}
