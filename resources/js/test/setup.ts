import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';
import '@/lib/i18n';

afterEach(() => {
    cleanup();
});

vi.stubGlobal('matchMedia', (query: string): MediaQueryList => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
}));

class PengamatUkuran {
    observe = vi.fn();
    unobserve = vi.fn();
    disconnect = vi.fn();
}

vi.stubGlobal('IntersectionObserver', PengamatUkuran);
vi.stubGlobal('ResizeObserver', PengamatUkuran);
