import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Design token BPMA DMS.
 *
 * Diturunkan dari mockup UI resmi (lihat `scripts/BPMA DMS UI/`). Seluruh warna
 * dan ukuran didefinisikan SEKALI di berkas ini — komponen dilarang menulis
 * nilai warna mentah. Aturan lengkap di `scripts/Arsitektur-Frontend.md` §2.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                // Biru tua BPMA — dipakai untuk aksi utama, banner, dan avatar.
                brand: {
                    50: '#eef2fb',
                    100: '#d9e2f7',
                    200: '#b3c4ef',
                    300: '#80a0e3',
                    400: '#4f74d1',
                    500: '#2f52b8',
                    600: '#23409b',
                    700: '#1d3c8f',
                    800: '#182f6f',
                    900: '#152858',
                },

                // Hijau logo BPMA — aksen identitas, bukan warna status.
                leaf: {
                    50: '#eef9ef',
                    100: '#d6f0d9',
                    500: '#4caf50',
                    600: '#3d9142',
                    700: '#2f7434',
                },

                // Warna semantik. Dipakai lewat maknanya (`text-success`), bukan
                // lewat rupanya (`text-green-600`) — supaya pemetaan status ke
                // warna hanya hidup di satu tempat.
                success: {
                    soft: '#dcfce7',
                    DEFAULT: '#15803d',
                    strong: '#166534',
                },
                warning: {
                    soft: '#fef3c7',
                    DEFAULT: '#b45309',
                    strong: '#92400e',
                },
                danger: {
                    soft: '#fee2e2',
                    DEFAULT: '#dc2626',
                    strong: '#b91c1c',
                },
                info: {
                    soft: '#dbeafe',
                    DEFAULT: '#1d4ed8',
                    strong: '#1e40af',
                },

                // Permukaan & garis. `surface.sunken` adalah latar area konten,
                // `surface.DEFAULT` adalah latar kartu dan bilah sisi.
                surface: {
                    DEFAULT: '#ffffff',
                    sunken: '#f6f7f9',
                    raised: '#fbfcfd',
                },
                line: {
                    DEFAULT: '#e8eaee',
                    strong: '#d5d9e0',
                },
                ink: {
                    DEFAULT: '#111827',
                    muted: '#6b7280',
                    subtle: '#9ca3af',
                },
            },

            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Nomor dokumen, tanggal, dan ukuran berkas memakai monospace
                // supaya angka sejajar antar baris tabel.
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },

            // Target sentuh minimum di layar kecil — `Tentang_Project.md` §5c.
            minHeight: { touch: '44px' },
            minWidth: { touch: '44px' },

            // Lebar bilah sisi, dipakai layout dan tombol ciutkan.
            spacing: {
                sidebar: '17rem',
                'sidebar-collapsed': '5rem',
            },

            borderRadius: {
                card: '0.875rem',
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(17 24 39 / 0.04), 0 1px 3px 0 rgb(17 24 39 / 0.03)',
                pop: '0 10px 30px -12px rgb(17 24 39 / 0.18)',
            },
        },
    },

    plugins: [forms],
};
