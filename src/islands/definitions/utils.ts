import type { HTMX } from './types';

export const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

export const getBasePath = (root: HTMLElement): string => {
    const raw = root.dataset.base || '';
    if (!raw) return '';
    return raw === '/' ? '' : raw;
};

export const getHtmx = (): HTMX | null => {
    const win = window as unknown as { htmx?: HTMX };
    return win.htmx ?? null;
};

export const isDescendantPath = (
    ancestorPath: string,
    childPath: string
): boolean => {
    if (!ancestorPath) return false;

    return (
        childPath === ancestorPath || childPath.startsWith(`${ancestorPath}/`)
    );
};
