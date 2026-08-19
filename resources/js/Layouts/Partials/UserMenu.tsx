import { Avatar } from '@/Components/ui/Avatar';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { ChevronDown, LogOut, UserCog } from 'lucide-react';

export interface UserMenuProps {
    user: App.Data.AuthUserData;
}

export function UserMenu({ user }: UserMenuProps) {
    return (
        <Menu as="div" className="relative">
            <MenuButton
                className={cn(
                    'flex min-h-touch items-center gap-2 rounded-lg border border-line bg-white px-2 py-1.5 text-left transition-colors',
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
                        {user.is_superadmin ? 'Superadmin' : (user.jabatan ?? 'Pengguna')}
                    </span>
                </span>

                <ChevronDown className="size-4 shrink-0 text-ink-subtle" aria-hidden />
            </MenuButton>

            <MenuItems
                anchor="bottom end"
                className="z-50 mt-2 w-60 rounded-card border border-line bg-white p-1 shadow-pop focus:outline-none"
            >
                <div className="border-b border-line px-3 py-2">
                    <p className="truncate text-sm font-medium text-ink">{user.name}</p>
                    <p className="truncate text-xs text-ink-muted">{user.email}</p>
                    {user.unit && (
                        <p className="mt-1 truncate text-xs text-ink-subtle">{user.unit}</p>
                    )}
                </div>

                <MenuItem>
                    <Link
                        href="/profile"
                        className="flex min-h-touch items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-muted data-[focus]:bg-surface-sunken data-[focus]:text-ink"
                    >
                        <UserCog className="size-4" aria-hidden />
                        Profil Saya
                    </Link>
                </MenuItem>

                <MenuItem>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex min-h-touch w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger data-[focus]:bg-danger-soft"
                    >
                        <LogOut className="size-4" aria-hidden />
                        Keluar
                    </Link>
                </MenuItem>
            </MenuItems>
        </Menu>
    );
}
