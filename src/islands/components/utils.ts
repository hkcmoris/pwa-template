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

export const focusFirstField = (container: HTMLElement) => {
    const field = container.querySelector<HTMLElement>(
        'button.select-button, input:not([type="hidden"]), textarea, select'
    );
    field?.focus();
};

export const parseImagesDataset = (
    raw: string | undefined,
    fallback?: string
): string[] => {
    if (!raw) {
        return buildImageList([], fallback);
    }

    try {
        const parsed = JSON.parse(raw) as unknown;
        if (Array.isArray(parsed)) {
            return buildImageList(
                parsed.filter(
                    (item): item is string => typeof item === 'string'
                ),
                fallback
            );
        }
    } catch {
        const trimmed = trimImageValue(raw);
        if (trimmed) {
            return buildImageList([trimmed], fallback);
        }
    }

    return buildImageList([], fallback);
};

export const buildImageList = (
    values: readonly string[] | undefined,
    fallback?: string
): string[] => {
    const unique: string[] = [];

    if (Array.isArray(values)) {
        values.forEach((item) => {
            const trimmed = trimImageValue(item);
            if (trimmed && !unique.includes(trimmed)) {
                unique.push(trimmed);
            }
        });
    }

    if (fallback) {
        const trimmedFallback = trimImageValue(fallback);
        if (trimmedFallback && !unique.includes(trimmedFallback)) {
            unique.push(trimmedFallback);
        }
    }

    return unique;
};

export const trimImageValue = (value: string): string => value.trim();
