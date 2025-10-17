import { setSelectValue } from '../shared/select';
import { createComponentApiClient } from './api-client';
import {
    COMPONENT_FORM_ERROR_ID,
    formatPriceForInput,
    parsePriceHistoryDataset,
    renderPriceHistory,
} from './form';
import { createComponentModalManager } from './modal';
import { openImagePickerModal } from './image-picker';
import type {
    AfterRequestDetail,
    ComponentModalOptions,
    PriceHistoryItem,
} from './types';
import { escapeHtml, focusFirstField, getHtmx } from './utils';

const listTarget = '#components-list';
const listWrapperTarget = '#components-list-wrapper';

type PageResponse = {
    html: string;
    nextOffset: number;
    hasMore: boolean;
};

const parseNumber = (value: string | undefined): number => {
    if (!value) {
        return 0;
    }

    const parsed = Number.parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
};

const setupInfiniteScroll = (root: HTMLElement, basePath: string) => {
    const sentinel = root.querySelector<HTMLElement>('[data-component-sentinel]');
    const list = root.querySelector<HTMLElement>(listTarget);

    if (!sentinel || !list) {
        return;
    }

    const sentinelElement = sentinel;
    const listElement = list;

    let nextOffset = parseNumber(sentinelElement.dataset.nextOffset);
    let loading = false;
    const total = parseNumber(sentinelElement.dataset.total);

    const hasMore = () => sentinelElement.dataset.hasMore !== '0';

    if (sentinelElement.dataset.bound === '1') {
        return;
    }

    sentinelElement.dataset.bound = '1';

    if (!hasMore()) {
        sentinelElement.remove();
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        if (!hasMore()) {
            observer.disconnect();
            return;
        }

        if (entries.some((entry) => entry.isIntersecting)) {
            void fetchNext();
        }
    }, { rootMargin: '200px' });

    observer.observe(sentinelElement);

    async function fetchNext() {
        if (loading || !hasMore()) {
            return;
        }

        loading = true;
        sentinelElement.dataset.loading = '1';

        try {
            const url = `${basePath}/editor/components/page?offset=${nextOffset}`;
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'HX-Request': 'true',
                },
            });

            if (!response.ok) {
                observer.disconnect();
                return;
            }

            const payload = (await response.json()) as PageResponse;
            const markup = (payload.html ?? '').trim();

            if (markup) {
                const fragment = document
                    .createRange()
                    .createContextualFragment(markup);
                listElement.appendChild(fragment);
            }

            nextOffset = payload.nextOffset ?? nextOffset;
            sentinelElement.dataset.nextOffset = String(nextOffset);
            sentinelElement.dataset.hasMore = payload.hasMore ? '1' : '0';

            if (!payload.hasMore || nextOffset >= total) {
                observer.disconnect();
                sentinelElement.remove();
            }
        } catch (error) {
            console.error('Failed to fetch component page', error);
            observer.disconnect();
        } finally {
            loading = false;
            delete sentinelElement.dataset.loading;
        }
    }
};

const normaliseColorValue = (raw: string | undefined): string => {
    const value = raw?.trim() ?? '';
    if (!value) {
        return '';
    }
    const prefixed = value.startsWith('#') ? value : `#${value}`;
    return prefixed.toUpperCase();
};

const computeModalTitle = (options: ComponentModalOptions): string => {
    if (options.mode === 'edit') {
        const trimmed = options.displayName?.trim();
        return trimmed ? `Upravit ${trimmed}` : 'Upravit komponentu';
    }
    return options.parentTitle?.trim() ? 'Přidat podkomponentu' : 'Přidat komponentu';
};

const applySelectValue = (
    hiddenInput: HTMLInputElement | null,
    value: string
) => {
    if (!hiddenInput) {
        return;
    }
    hiddenInput.value = value;
    const select = hiddenInput
        .closest('[data-select-wrapper]')
        ?.querySelector<HTMLElement>('.select[data-select]');
    if (select) {
        select.setAttribute('data-value', value);
        setSelectValue(select, value);
    }
};

const updatePositionField = (
    input: HTMLInputElement | null,
    options: ComponentModalOptions
) => {
    if (!input) {
        return;
    }
    const isEdit = options.mode === 'edit';
    if (
        isEdit &&
        options.position !== undefined &&
        options.position !== null
    ) {
        input.value = String(options.position);
    } else if (
        !isEdit &&
        typeof options.childCount === 'number' &&
        Number.isFinite(options.childCount)
    ) {
        input.value = String(options.childCount);
    } else {
        input.value = '';
    }
};

const applyPriceHistory = (
    list: HTMLElement | null,
    history: PriceHistoryItem[] | undefined,
    currency: string
) => {
    if (!list) {
        return;
    }
    const data = Array.isArray(history) ? history : [];
    renderPriceHistory(list, data, currency);
};

export default function init(root: HTMLElement) {
    const modalRoot = document.getElementById(
        'components-modal'
    ) as HTMLElement | null;
    const createTemplate = document.getElementById(
        'component-create-template'
    ) as HTMLTemplateElement | null;
    const openButton = root.querySelector<HTMLButtonElement>(
        '#component-open-create'
    );
    const htmx = getHtmx();
    const basePath = root.dataset.base || '';
    const createUrl = `${basePath}/editor/components/create`;
    const updateUrl = `${basePath}/editor/components/update`;
    const deleteUrl = `${basePath}/editor/components/delete`;

    if (!modalRoot || !createTemplate) {
        return;
    }

    const errorBox = document.getElementById(
        COMPONENT_FORM_ERROR_ID
    ) as HTMLElement | null;

    const modal = createComponentModalManager(modalRoot, htmx, errorBox);
    const api = createComponentApiClient({
        deleteUrl,
        listTarget: listWrapperTarget,
        htmx,
    });

    setupInfiniteScroll(root, basePath);

    root.addEventListener('htmx:afterSwap', (event) => {
        const detail = (event as CustomEvent<{ target: HTMLElement | null }>).detail;
        const target = detail?.target;

        if (target && (target.matches(listTarget) || target.matches(listWrapperTarget))) {
            setupInfiniteScroll(root, basePath);
        }
    });

    const openComponentModal = (options: ComponentModalOptions = {}) => {
        const mode = options.mode ?? 'create';
        const fragment = createTemplate.content.cloneNode(
            true
        ) as DocumentFragment;
        const form = fragment.querySelector('form');
        if (!form) {
            return;
        }

        const definitionHidden = form.querySelector<HTMLInputElement>(
            '#component-modal-definition'
        );
        const parentHidden = form.querySelector<HTMLInputElement>(
            '#component-modal-parent'
        );
        const componentIdInput = form.querySelector<HTMLInputElement>(
            '#component-modal-id'
        );
        const alternateInput = form.querySelector<HTMLInputElement>(
            '#component-modal-title'
        );
        const descriptionField = form.querySelector<HTMLTextAreaElement>(
            '#component-modal-description'
        );
        const imageField = form.querySelector<HTMLInputElement>(
            '#component-modal-image'
        );
        const imagePlaceholder = form.querySelector<HTMLElement>(
            '[data-image-placeholder]'
        );
        const imagePathLabel = form.querySelector<HTMLElement>(
            '[data-image-path]'
        );
        const imageOpenButton = form.querySelector<HTMLButtonElement>(
            '[data-image-select-open]'
        );
        const imageClearButton = form.querySelector<HTMLButtonElement>(
            '[data-image-clear]'
        );
        const colorField = form.querySelector<HTMLInputElement>(
            '#component-modal-color'
        );
        const colorPicker = form.querySelector<HTMLInputElement>(
            '#component-modal-color-swatch'
        );
        const positionInput = form.querySelector<HTMLInputElement>(
            '#component-modal-position'
        );
        const priceField = form.querySelector<HTMLInputElement>(
            '#component-modal-price'
        );
        const priceHistoryList = form.querySelector<HTMLElement>(
            '[data-price-history-list]'
        );
        const priceCurrencyLabel = form.querySelector<HTMLElement>(
            '[data-price-currency]'
        );
        const legend = form.querySelector('legend');
        const submitButton = form.querySelector<HTMLButtonElement>(
            'button[type="submit"]'
        );

        const definitionValue = options.definitionId ?? '';
        applySelectValue(definitionHidden, definitionValue);
        const parentValue = options.parentId ?? '';
        applySelectValue(parentHidden, parentValue);
        if (componentIdInput) {
            componentIdInput.value =
                mode === 'edit' ? options.componentId ?? '' : '';
        }

        const imageValue = options.image ?? '';
        const normalisedColor = normaliseColorValue(options.color);
        const mediaMode = options.mediaType ?? (normalisedColor ? 'color' : 'image');

        if (alternateInput) {
            alternateInput.value = options.alternateTitle ?? '';
        }
        if (descriptionField) {
            descriptionField.value = options.description ?? '';
        }
        const setImageValue = (value: string) => {
            const trimmed = value.trim();
            if (imageField) {
                imageField.value = trimmed;
            }
            if (imagePathLabel) {
                imagePathLabel.textContent = trimmed;
            }
            if (imagePlaceholder) {
                imagePlaceholder.classList.toggle('hidden', trimmed !== '');
            }
            if (imageClearButton) {
                if (trimmed) {
                    imageClearButton.removeAttribute('disabled');
                } else {
                    imageClearButton.setAttribute('disabled', 'true');
                }
            }
        };

        setImageValue(mediaMode === 'image' ? imageValue : '');
        if (colorField) {
            colorField.value = normalisedColor;
        }
        if (colorPicker) {
            const pickerColor = /^#[0-9A-F]{6}$/u.test(normalisedColor)
                ? normalisedColor
                : /^#[0-9A-F]{3}$/u.test(normalisedColor)
                  ? `#${normalisedColor[1]}${normalisedColor[1]}${normalisedColor[2]}${normalisedColor[2]}${normalisedColor[3]}${normalisedColor[3]}`.toUpperCase()
                  : '#ffffff';
            colorPicker.value = pickerColor;
        }
        form.dataset.mediaMode = mediaMode;

        const priceCurrency = (options.priceCurrency ?? 'CZK').toUpperCase();
        if (priceCurrencyLabel) {
            priceCurrencyLabel.textContent = priceCurrency;
        }
        if (priceField) {
            priceField.value = formatPriceForInput(options.priceAmount);
            priceField.dataset.currency = priceCurrency;
        }
        applyPriceHistory(priceHistoryList, options.priceHistory, priceCurrency);

        updatePositionField(positionInput, { ...options, mode });

        if (legend) {
            if (mode === 'edit') {
                legend.textContent = 'Upravit komponentu';
            } else {
                const parentTitle = options.parentTitle?.trim() ?? '';
                legend.textContent = parentTitle
                    ? `Přidat podkomponentu k ${parentTitle}`
                    : 'Přidat novou komponentu';
            }
        }
        if (submitButton) {
            submitButton.textContent = mode === 'edit' ? 'Uložit změny' : 'Uložit';
        }

        const modalTitle = computeModalTitle({ ...options, mode });

        if (mode === 'edit') {
            form.setAttribute('action', updateUrl);
            form.setAttribute('hx-post', updateUrl);
        } else {
            form.setAttribute('action', createUrl);
            form.setAttribute('hx-post', createUrl);
        }

        modal.open(modalTitle, form as HTMLElement);
        focusFirstField(form as HTMLElement);

        imageOpenButton?.addEventListener('click', async (event) => {
            event.preventDefault();
            try {
                const selection = await openImagePickerModal({
                    basePath,
                    currentValue: imageField?.value ?? '',
                });
                if (selection) {
                    setImageValue(selection.value);
                }
            } catch (error) {
                console.error('Image picker failed', error);
            }
        });

        imageClearButton?.addEventListener('click', (event) => {
            event.preventDefault();
            setImageValue('');
        });

        const definitionSelect = definitionHidden
            ?.closest('[data-select-wrapper]')
            ?.querySelector<HTMLElement>('.select[data-select]');
        if (definitionSelect) {
            setSelectValue(definitionSelect, definitionHidden?.value ?? '');
        }
        const parentSelect = parentHidden
            ?.closest('[data-select-wrapper]')
            ?.querySelector<HTMLElement>('.select[data-select]');
        if (parentSelect) {
            setSelectValue(parentSelect, parentHidden?.value ?? '');
        }

        form.addEventListener('htmx:afterRequest', (event) => {
            const detail = (event as CustomEvent<AfterRequestDetail>).detail;
            const status = detail?.xhr?.status ?? 0;
            if (status && status < 400) {
                modal.close();
            }
        });
    };

    openButton?.addEventListener('click', (event) => {
        event.preventDefault();
        openComponentModal();
    });

    root.addEventListener('click', (event) => {
        const target = event.target as HTMLElement;
        const button = target.closest<HTMLButtonElement>(
            '.component-actions .component-action[data-action]'
        );
        if (!button) {
            return;
        }
        event.preventDefault();
        const action = button.dataset.action;
        const item = button.closest<HTMLElement>('.component-item');

        if (action === 'create-child') {
            const parentId = button.dataset.parentId ?? '';
            const parentTitle = button.dataset.parentTitle ?? '';
            const raw = button.dataset.parentChildren;
            const parsed = raw !== undefined ? Number.parseInt(raw, 10) : NaN;
            const childCount = Number.isNaN(parsed) ? undefined : parsed;
            openComponentModal({
                parentId,
                parentTitle,
                childCount,
            });
        } else if (action === 'edit') {
            const parentId = item?.dataset.parent ?? '';
            const positionRaw = button.dataset.position;
            const parsedPosition =
                positionRaw !== undefined
                    ? Number.parseInt(positionRaw, 10)
                    : NaN;
            const priceAmount = button.dataset.priceAmount ?? '';
            const priceCurrency = button.dataset.priceCurrency ?? 'CZK';
            const priceHistory = parsePriceHistoryDataset(
                button.dataset.priceHistory
            );
            openComponentModal({
                mode: 'edit',
                componentId:
                    button.dataset.componentId ?? item?.dataset.id ?? '',
                definitionId: button.dataset.definitionId ?? '',
                alternateTitle: button.dataset.alternateTitle ?? '',
                description: button.dataset.description ?? '',
                image: button.dataset.image ?? '',
                color: button.dataset.color ?? '',
                mediaType:
                    (button.dataset.mediaType as
                        | 'image'
                        | 'color'
                        | undefined) ?? undefined,
                parentId,
                position: Number.isNaN(parsedPosition)
                    ? undefined
                    : parsedPosition,
                displayName: button.dataset.title ?? '',
                priceAmount,
                priceCurrency,
                priceHistory,
            });
        } else if (action === 'delete') {
            const componentId = button.dataset.id ?? item?.dataset.id ?? '';
            if (!componentId) {
                return;
            }

            const rawTitle = button.dataset.title ?? '';
            const displayName = rawTitle.trim() || `ID ${componentId}`;
            let childCount = 0;
            if (item?.dataset.childrenCount) {
                const parsedChildren = Number.parseInt(
                    item.dataset.childrenCount,
                    10
                );
                if (!Number.isNaN(parsedChildren) && parsedChildren > 0) {
                    childCount = parsedChildren;
                }
            }

            const container = document.createElement('div');
            container.className = 'component-modal-body';
            const detailText =
                childCount > 0
                    ? `Tato akce odstrani take vsechny jeji podkomponenty (vcetne ${
                          childCount === 1
                              ? '1 primo podrizene'
                              : `${childCount} primo podrizenych`
                      } komponent).`
                    : 'Tato akce nelze vratit.';
            container.innerHTML = `
                <p>Opravdu chcete smazat komponentu <strong>${escapeHtml(
                    displayName
                )}</strong>?</p>
                <p>${escapeHtml(detailText)}</p>
                <div class="components-modal-actions">
                  <button type="button" class="component-action" data-modal-close>Storno</button>
                  <button type="button" class="component-action component-action--danger" data-modal-confirm>Smazat</button>
                </div>
            `;

            modal.open('Smazat komponentu', container);

            container
                .querySelector<HTMLButtonElement>('[data-modal-confirm]')
                ?.addEventListener('click', () => {
                    api.deleteComponent(componentId);
                    modal.close();
                });
        }
    });
}
