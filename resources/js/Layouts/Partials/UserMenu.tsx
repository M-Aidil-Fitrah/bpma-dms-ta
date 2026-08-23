import { Avatar } from '@/Components/ui/Avatar';
import { Dropdown, DropdownItem } from '@/Components/ui/Dropdown';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { ChevronDown, LogOut, UserCog } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface UserMenuProps {
    user: App.Data.AuthUserData;
}

export function UserMenu({ user }: UserMenuProps) {
    const { t } = useTranslation('nav');

    return (
        <Dropdown
            trigger={
                <button
                    type="button"
                className={cn(
                    'flex min-h-touch items-center gap-2 rounded-lg border border-line bg-surface px-2 py-1.5 text-left transition-colors',
                    'hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
                )}
                >
                    <Avatar initials={user.initials} size="sm" />

                    {/* Bilah atas baru menampilkan nama saat ruangnya memang ada. */}
                    <span className="hidden min-w-0 flex-1 md:block">
                        <span className="block truncate text-sm font-medium text-ink">
                            {user.name}
                        </span>
                        <span className="block truncate text-xs text-ink-muted">
                            {user.is_superadmin ? t('penggunaMenu.superadmin') : (user.jabatan ?? t('penggunaMenu.pengguna'))}
                        </span>
                    </span>

                    <ChevronDown className="hidden size-4 shrink-0 text-ink-subtle sm:block" aria-hidden />
                </button>
            }
            panelClassName="mt-2 w-60"
        >
                <div className="border-b border-line px-3 py-2">
                    <p className="truncate text-sm font-medium text-ink">{user.name}</p>
                    <p className="truncate text-xs text-ink-muted">{user.email}</p>
                    {user.unit && (
                        <p className="mt-1 truncate text-xs text-ink-subtle">{user.unit}</p>
                    )}
                </div>

                <DropdownItem>
                    <Link
                        href="/profile"
                        className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink"
                    >
                        <UserCog className="size-4" aria-hidden />
                        {t('penggunaMenu.profilSaya')}
                    </Link>
                </DropdownItem>

                <DropdownItem>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger data-[focus]:bg-danger-soft"
                    >
                        <LogOut className="size-4" aria-hidden />
                        {t('penggunaMenu.keluar')}
                    </Link>
                </DropdownItem>
        </Dropdown>
    );
}
