import { cn } from '@/lib/cn';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Fragment, type ReactNode } from 'react';

export interface DropdownProps {
    trigger: ReactNode;
    children: ReactNode;
    className?: string;
    panelClassName?: string;
}

/** Kerangka menu aksi Headless UI dengan panel dan fokus yang seragam. */
export function Dropdown({ trigger, children, className, panelClassName }: DropdownProps) {
    return (
        <Menu as="div" className={cn('relative', className)}>
            <MenuButton as={Fragment}>{trigger}</MenuButton>
            <MenuItems
                anchor="bottom end"
                className={cn(
                    'z-50 mt-1 rounded-card border border-line bg-surface p-1 shadow-pop focus:outline-none',
                    panelClassName,
                )}
            >
                {children}
            </MenuItems>
        </Menu>
    );
}

export function DropdownItem({ children }: { children: ReactNode }) {
    return <MenuItem>{children}</MenuItem>;
}
