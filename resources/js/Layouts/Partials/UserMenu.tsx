import { Avatar } from '@/Components/ui/Avatar';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { ChevronDown, LogOut, UserCog } from 'lucide-react';

export interface UserMenuProps {
    user: App.Data.AuthUserData;
    /** Tampilan ringkas untuk bilah atas; tampilan penuh untuk kaki bilah sisi. */
    variant?: 'compact' | 'full';
}

export function UserMenu({ user, variant = 'compact' }: UserMenuProps) {
    return (
        <Menu as="div" className="relative">
            <MenuButton
                className={cn(
                    'flex min-h-touch items-center gap-2 rounded-lg border border-line bg-white px-2 py-1.5 text-left transition-colors',
                    'hover:bg-surface-sunken focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
                    variant === 'full' && 'w-full border-0 px-3',
                )}
            >
                <Avatar initials={user.initials} size={variant === 'full' ? 'md' : 'sm'} />

                {/* Pada varian penuh nama selalu tampil: komponennya berada di
                    dalam bilah sisi atau laci yang lebarnya sudah pasti cukup,
                    berapa pun lebar layarnya. Varian ringkas di bilah atas baru
                    menampilkan nama saat ruangnya memang ada. */}
                <span
                    className={cn(
                        'min-w-0 flex-1',
                        variant === 'full' ? 'block' : 'hidden sm:block',
                    )}
                >
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
                anchor={variant === 'full' ? 'top start' : 'bottom end'}
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
