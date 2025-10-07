import type { HTMX } from './types';

export const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

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
