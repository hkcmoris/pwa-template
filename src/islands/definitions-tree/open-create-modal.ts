import { enhanceSelects, setSelectValue } from '../select';
import type { ModalManager, OpenCreateOptions } from './types';

const focusTitleInput = (form: HTMLFormElement) => {
    form.querySelector<HTMLInputElement>('input[name="title"]')?.focus();
};

type FactoryOptions = {
    modal: ModalManager;
    createTemplate: HTMLTemplateElement | null;
    getParentSelectCache: () => HTMLElement | null;
};

export const createOpenCreateModal = ({
    modal,
    createTemplate,
    getParentSelectCache,
}: FactoryOptions) => {
    const openCreateModal = (options: OpenCreateOptions = {}) => {
        const parentSelectCache = getParentSelectCache();

        if (!createTemplate || !parentSelectCache) return;

        const fragment = createTemplate.content.cloneNode(
            true
        ) as DocumentFragment;

        const form = fragment.querySelector('form');

        if (!form) return;

        const { parentId: rawParentId, parentTitle, childCount } = options;

        const desiredParentId =
            rawParentId === undefined || rawParentId === null
                ? ''
                : rawParentId;

        const normalizedParentTitle =
            typeof parentTitle === 'string' ? parentTitle.trim() : '';

        const slot = form.querySelector('[data-definition-select-slot]');

        let hiddenInput: HTMLInputElement | null = null;

        if (slot) {
            const clone = parentSelectCache.cloneNode(true) as HTMLElement;
            clone.id = 'definition-parent-select-modal';
            clone.removeAttribute('hx-swap-oob');
            clone.removeAttribute('aria-hidden');
            clone.removeAttribute('style');
            clone.removeAttribute('data-island');
            clone.removeAttribute('hx-on');

            hiddenInput = clone.querySelector<HTMLInputElement>(
                'input[name="parent_id"]'
            );

            if (hiddenInput) {
                hiddenInput.id = 'definition-parent-value-modal';
                hiddenInput.value = desiredParentId;
            }

            const button = clone.querySelector<HTMLButtonElement>(
                '#definition-parent-button'
            );

            if (button) {
                button.id = 'definition-parent-button-modal';
                button.setAttribute(
                    'aria-labelledby',
                    'definition-parent-label definition-parent-button-modal'
                );
            }

            slot.replaceWith(clone);
            enhanceSelects(clone);
            const selectElement = clone.querySelector<HTMLElement>('.select');

            if (selectElement) {
                setSelectValue(selectElement, desiredParentId);
            }

            clone.addEventListener('select:change', (event) => {
                const detail = (event as CustomEvent).detail as
                    | { value?: string }
                    | undefined;

                if (hiddenInput) hiddenInput.value = detail?.value ?? '';
            });
        }

        const legend = form.querySelector('legend');

        if (legend) {
            legend.textContent = normalizedParentTitle
                ? `Přidat poduzel k ${normalizedParentTitle}`
                : 'Přidat novou definici';
        }

        const positionInput = form.querySelector<HTMLInputElement>(
            'input[name="position"]'
        );

        if (positionInput) {
            if (typeof childCount === 'number' && Number.isFinite(childCount)) {
                positionInput.value = String(childCount);
            } else {
                positionInput.value = '';
            }
        }

        const title = normalizedParentTitle
            ? 'Přidat poduzel'
            : 'Přidat definici';

        modal.open(title, form);

        focusTitleInput(form);

        form.addEventListener('htmx:afterRequest', (event) => {
            const detail = (event as CustomEvent).detail as
                | { xhr?: XMLHttpRequest }
                | undefined;

            const status = detail?.xhr?.status ?? 0;

            if (status && status < 400) {
                modal.close();
            }
        });
    };

    return openCreateModal;
};
