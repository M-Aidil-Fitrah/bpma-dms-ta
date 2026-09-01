import { cn } from '@/lib/cn';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Building2,
    Clock3,
    FileText,
    FolderOpen,
    FolderTree,
    History,
    LayoutDashboard,
    Settings,
    Share2,
    Star,
    Trash2,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface NavItem {
    labelKey: string;
    href: string;
    icon: LucideIcon;
    /** Awalan alamat yang membuat butir ini ditandai aktif. */
    match: string;
    exact?: boolean;
    superadminOnly?: boolean;
}

interface NavGroup {
    labelKey: string | null;
    items: NavItem[];
}

/**
 * Struktur navigasi mengikuti lingkup yang benar-benar dibangun
 * (`Tentang_Project.md` §3.1). Mockup UI memuat menu Alur Persetujuan dan Pesan
 * Masuk, keduanya berada di luar lingkup prototype (`PRD.md` §12) sehingga
 * sengaja tidak ditampilkan.
 *
 * Labelnya berupa kunci terjemahan (`nav.item.*`), bukan teks langsung —
 * struktur ini didefinisikan sekali di luar komponen, jadi tidak bisa
 * memanggil `useTranslation()` di sini. Penerjemahannya terjadi saat dirender.
 */
const NAV: NavGroup[] = [
    {
        labelKey: 'grup.menuUtama',
        items: [
            { labelKey: 'item.beranda', href: '/dashboard', icon: LayoutDashboard, match: '/dashboard' },
            { labelKey: 'item.dokumenSaya', href: '/documents/mine', icon: FolderOpen, match: '/documents/mine' },
            { labelKey: 'item.dibagikanKeSaya', href: '/documents/shared', icon: Share2, match: '/documents/shared' },
            { labelKey: 'item.jelajahiDokumen', href: '/documents', icon: FileText, match: '/documents', exact: true },
        ],
    },
    {
        labelKey: null,
        items: [
            { labelKey: 'item.terbaru', href: '/documents/recent', icon: Clock3, match: '/documents/recent' },
            { labelKey: 'item.berbintang', href: '/documents/starred', icon: Star, match: '/documents/starred' },
            { labelKey: 'item.sampah', href: '/trash', icon: Trash2, match: '/trash' },
            { labelKey: 'item.riwayatAktivitas', href: '/activity-log', icon: History, match: '/activity-log' },
        ],
    },
    {
        labelKey: 'grup.pengelolaan',
        items: [
            { labelKey: 'item.logAktivitas', href: '/admin/activity-log', icon: Activity, match: '/admin/activity-log', superadminOnly: true },
            { labelKey: 'item.pengguna', href: '/admin/users', icon: Users, match: '/admin/users', superadminOnly: true },
            { labelKey: 'item.unitKerja', href: '/admin/units', icon: Building2, match: '/admin/units', superadminOnly: true },
            { labelKey: 'item.jabatan', href: '/admin/jabatans', icon: FolderTree, match: '/admin/jabatans', superadminOnly: true },
            { labelKey: 'item.kategori', href: '/admin/categories', icon: FolderTree, match: '/admin/categories', superadminOnly: true },
            { labelKey: 'item.pengaturan', href: '/admin/settings', icon: Settings, match: '/admin/settings', superadminOnly: true },
        ],
    },
];

export interface SidebarNavProps {
    /** Menutup laci navigasi setelah tautan dipilih di layar kecil. */
    onNavigate?: () => void;
}

export function SidebarNav({ onNavigate }: SidebarNavProps) {
    const { t } = useTranslation('nav');
    const { props: { auth }, url } = usePage();

    const currentPath = url;
    const isSuperadmin = auth.user?.is_superadmin ?? false;

    return (
        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label={t('ariaNavigasiUtama')}>
            {NAV.map((group) => {
                const items = group.items.filter(
                    (item) => !item.superadminOnly || isSuperadmin,
                );

                if (items.length === 0) return null;

                return (
                    <div key={group.labelKey ?? 'root'}>
                        {group.labelKey && (
                            <p className="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                                {t(group.labelKey)}
                            </p>
                        )}

                        <ul className="space-y-1">
                            {items.map((item) => {
                                const active = item.exact
                                    ? currentPath === item.match
                                    : currentPath.startsWith(item.match);

                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            onClick={onNavigate}
                                            aria-current={active ? 'page' : undefined}
                                            className={cn(
                                                'flex min-h-touch items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
                                                active
                                                    ? 'bg-brand-50 text-brand-700'
                                                    : 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
                                            )}
                                        >
                                            {/* Penanda aktif berupa batang kiri, mengikuti mockup. */}
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    '-ml-3 h-5 w-1 rounded-r',
                                                    active ? 'bg-brand-700' : 'bg-transparent',
                                                )}
                                            />
                                            <item.icon className="size-[18px] shrink-0" aria-hidden />
                                            <span className="truncate">{t(item.labelKey)}</span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                );
            })}
        </nav>
    );
}
