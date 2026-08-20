import { cn } from '@/lib/cn';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FileText,
    FolderTree,
    History,
    LayoutDashboard,
    Settings,
    Users,
    type LucideIcon,
} from 'lucide-react';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Awalan alamat yang membuat butir ini ditandai aktif. */
    match: string;
    superadminOnly?: boolean;
}

interface NavGroup {
    label: string | null;
    items: NavItem[];
}

/**
 * Struktur navigasi mengikuti lingkup yang benar-benar dibangun
 * (`Tentang_Project.md` §3.1). Mockup UI memuat menu Alur Persetujuan dan Pesan
 * Masuk, keduanya berada di luar lingkup prototype (`PRD.md` §12) sehingga
 * sengaja tidak ditampilkan.
 */
const NAV: NavGroup[] = [
    {
        label: 'Menu Utama',
        items: [
            { label: 'Beranda', href: '/dashboard', icon: LayoutDashboard, match: '/dashboard' },
            { label: 'Semua Dokumen', href: '/documents', icon: FileText, match: '/documents' },
            { label: 'Riwayat Aktivitas', href: '/activity-log', icon: History, match: '/activity-log' },
        ],
    },
    {
        label: 'Pengelolaan',
        items: [
            { label: 'Pengguna', href: '/admin/users', icon: Users, match: '/admin/users', superadminOnly: true },
            { label: 'Unit Kerja', href: '/admin/units', icon: Building2, match: '/admin/units', superadminOnly: true },
            { label: 'Jabatan', href: '/admin/jabatans', icon: FolderTree, match: '/admin/jabatans', superadminOnly: true },
            { label: 'Kategori', href: '/admin/categories', icon: FolderTree, match: '/admin/categories', superadminOnly: true },
            { label: 'Pengaturan', href: '/admin/settings', icon: Settings, match: '/admin/settings', superadminOnly: true },
        ],
    },
];

export interface SidebarNavProps {
    /** Menutup laci navigasi setelah tautan dipilih di layar kecil. */
    onNavigate?: () => void;
}

export function SidebarNav({ onNavigate }: SidebarNavProps) {
    const { props: { auth }, url } = usePage();

    const currentPath = url;
    const isSuperadmin = auth.user?.is_superadmin ?? false;

    return (
        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Navigasi utama">
            {NAV.map((group) => {
                const items = group.items.filter(
                    (item) => !item.superadminOnly || isSuperadmin,
                );

                if (items.length === 0) return null;

                return (
                    <div key={group.label ?? 'root'}>
                        {group.label && (
                            <p className="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-ink-subtle">
                                {group.label}
                            </p>
                        )}

                        <ul className="space-y-1">
                            {items.map((item) => {
                                const active = currentPath.startsWith(item.match);

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
                                            <span className="truncate">{item.label}</span>
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
