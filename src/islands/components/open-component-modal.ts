import { setSelectValue } from '../shared/select';
import { formatPriceForInput, renderPriceHistory } from './form';
import { openImagePickerModal } from './image-picker';
import { ComponentModalManager } from './modal';
import {
    AfterRequestDetail,
    ComponentModalOptions,
    PriceHistoryItem,
    SelectedImageEntry,
} from './types';
import { buildImageList, focusFirstField, trimImageValue } from './utils';

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
    return options.parentTitle?.trim()
        ? 'Přidat podkomponentu'
        : 'Přidat komponentu';
};

const updatePositionField = (
    input: HTMLInputElement | null,
    options: ComponentModalOptions
) => {
    if (!input) {
        return;
    }
    const isEdit = options.mode === 'edit';
    if (isEdit && options.position !== undefined && options.position !== null) {
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

type FactoryOptions = {
    modal: ComponentModalManager;
    createTemplate: HTMLTemplateElement | null;
    base: string;
};

const setupHelpTooltipClamp = (form: HTMLElement) => {
    const wrappers = Array.from(
        form.querySelectorAll<HTMLElement>('.info-wrapper')
    );

    if (wrappers.length === 0) {
        return;
    }

    const clampTooltip = (wrapper: HTMLElement) => {
        const tooltip = wrapper.querySelector<HTMLElement>('.component-help');
        if (!tooltip) {
            return;
        }

        const previousVisibility = tooltip.style.visibility;
        const previousOpacity = tooltip.style.opacity;
        tooltip.style.visibility = 'visible';
        tooltip.style.opacity = '0';
        tooltip.style.setProperty('--tooltip-shift', '0px');

        const rect = tooltip.getBoundingClientRect();
        const viewportPadding = 8;
        let shift = 0;

        if (rect.left < viewportPadding) {
            shift = viewportPadding - rect.left;
        } else if (rect.right > window.innerWidth - viewportPadding) {
            shift = window.innerWidth - viewportPadding - rect.right;
        }

        tooltip.style.setProperty('--tooltip-shift', `${shift}px`);
        tooltip.style.visibility = previousVisibility;
        tooltip.style.opacity = previousOpacity;
    };

    wrappers.forEach((wrapper) => {
        const update = () => clampTooltip(wrapper);
        wrapper.addEventListener('mouseenter', update);
        wrapper.addEventListener('focusin', update);
    });

    window.addEventListener('resize', () => {
        wrappers.forEach((wrapper) => {
            if (wrapper.matches(':hover') || wrapper.matches(':focus-within')) {
                clampTooltip(wrapper);
            }
        });
    });
};

export const createOpenComponentModal = ({
    modal,
    createTemplate,
    base,
}: FactoryOptions) => {
    const openComponentModal = (options: ComponentModalOptions = {}) => {
        if (!createTemplate) return;

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
        const imagesField = form.querySelector<HTMLInputElement>(
            '[data-images-input]'
        );
        const imagePlaceholder = form.querySelector<HTMLElement>(
            '[data-image-placeholder]'
        );
        const imageList =
            form.querySelector<HTMLUListElement>('[data-image-list]');
        const imageOpenButton = form.querySelector<HTMLButtonElement>(
            '[data-image-select-open]'
        );
        const imageClearButton =
            form.querySelector<HTMLButtonElement>('[data-image-clear]');
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
                mode === 'edit' ? (options.componentId ?? '') : '';
        }

        const normalisedColor = normaliseColorValue(options.color);
        const initialImages = buildImageList(options.images, options.image);
        let mediaMode =
            options.mediaType ??
            (normalisedColor
                ? 'color'
                : initialImages.length > 0
                  ? 'image'
                  : 'image');

        if (alternateInput) {
            alternateInput.value = options.alternateTitle ?? '';
        }
        if (descriptionField) {
            descriptionField.value = options.description ?? '';
        }
        let selectedImages: SelectedImageEntry[] =
            mediaMode === 'image'
                ? initialImages.map((value) => ({ value, label: value }))
                : [];

        const syncSelectedImages = () => {
            const values = selectedImages.map((item) => item.value);
            if (imagesField) {
                imagesField.value = JSON.stringify(values);
            }
            if (imagePlaceholder) {
                imagePlaceholder.classList.toggle('hidden', values.length > 0);
            }
            if (imageClearButton) {
                if (values.length > 0) {
                    imageClearButton.removeAttribute('disabled');
                } else {
                    imageClearButton.setAttribute('disabled', 'true');
                }
            }
            if (imageList) {
                imageList.innerHTML = '';
                selectedImages.forEach((item, index) => {
                    const entry = document.createElement('li');
                    entry.className = 'component-image-entry';
                    const thumb = document.createElement('figure');
                    thumb.className = 'component-image-thumb';
                    const media = document.createElement('div');
                    media.className =
                        'component-image-thumb-media component-image-thumb-media--tiny';
                    const img = document.createElement(
                        'img'
                    ) as HTMLImageElement;
                    img.src =
                        item.value ?? '/public/assets/images/missing-image.svg';
                    img.alt =
                        item.label ?? 'Thumbnail image preview of component.';
                    img.width = 20;
                    img.height = 20;
                    img.loading = 'lazy';
                    img.decoding = 'async';
                    img.onerror = () => {
                        img.onerror = null; // prevent infinite loop
                        img.src = '/public/assets/images/missing-image.svg';
                    };
                    media.appendChild(img);
                    thumb.appendChild(media);
                    entry.appendChild(thumb);
                    const label = document.createElement('span');
                    label.className = 'component-image-path';
                    label.textContent = item.label;
                    entry.appendChild(label);
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className =
                        'component-action component-action--danger component-image-remove';
                    removeButton.dataset.imageRemove = 'true';
                    removeButton.dataset.index = String(index);
                    // removeButton.textContent = 'Odebrat';
                    removeButton.innerHTML = `
                    <svg fill="currentColor" width="16px" height="16px" display="block" style="display: block;" aria-hidden="true">
                        <use href="#icon-trash"></use>
                    </svg>
                    `;
                    entry.appendChild(removeButton);
                    imageList.appendChild(entry);
                });
            }
        };

        const setSelectedImages = (entries: SelectedImageEntry[]) => {
            selectedImages = entries.filter((entry) => {
                const value = trimImageValue(entry.value);
                return value !== '';
            });
            selectedImages = selectedImages.map((entry) => ({
                value: trimImageValue(entry.value),
                label: entry.label?.trim() || trimImageValue(entry.value),
            }));
            const unique: SelectedImageEntry[] = [];
            selectedImages.forEach((entry) => {
                if (!unique.some((item) => item.value === entry.value)) {
                    unique.push(entry);
                }
            });
            selectedImages = unique;
            syncSelectedImages();
        };

        const addImageEntry = (entry: SelectedImageEntry) => {
            const value = trimImageValue(entry.value);
            if (!value) {
                return;
            }
            const label = entry.label?.trim() || value;
            const existingIndex = selectedImages.findIndex(
                (item) => item.value === value
            );
            if (existingIndex >= 0) {
                const next = [...selectedImages];
                next[existingIndex] = { value, label };
                setSelectedImages(next);
            } else {
                setSelectedImages([...selectedImages, { value, label }]);
            }
        };

        const removeImageAt = (index: number) => {
            if (index < 0 || index >= selectedImages.length) {
                return;
            }
            const next = selectedImages.slice();
            next.splice(index, 1);
            setSelectedImages(next);
        };

        if (mediaMode === 'color') {
            setSelectedImages([]);
        } else {
            syncSelectedImages();
        }
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
        applyPriceHistory(
            priceHistoryList,
            options.priceHistory,
            priceCurrency
        );

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
            submitButton.textContent =
                mode === 'edit' ? 'Uložit změny' : 'Uložit';
        }

        const modalTitle = computeModalTitle({ ...options, mode });

        if (mode === 'edit') {
            form.setAttribute('action', `${base}/editor/components/update`);
            form.setAttribute('hx-post', `${base}/editor/components/update`);
        } else {
            form.setAttribute('action', `${base}/editor/components/create`);
            form.setAttribute('hx-post', `${base}/editor/components/create`);
        }

        modal.open(modalTitle, form as HTMLElement);
        setupHelpTooltipClamp(form as HTMLElement);
        focusFirstField(form as HTMLElement);

        imageOpenButton?.addEventListener('click', async (event) => {
            event.preventDefault();
            try {
                const currentValue =
                    selectedImages[selectedImages.length - 1]?.value ?? '';
                const selection = await openImagePickerModal({
                    basePath: base,
                    currentValue,
                });
                if (selection) {
                    addImageEntry({
                        value: selection.value,
                        label: selection.name ?? selection.value,
                    });
                }
            } catch (error) {
                console.error('Image picker failed', error);
            }
        });

        imageClearButton?.addEventListener('click', (event) => {
            event.preventDefault();
            setSelectedImages([]);
        });

        imageList?.addEventListener('click', (event) => {
            const button = (
                event.target as HTMLElement
            ).closest<HTMLButtonElement>('[data-image-remove]');
            if (!button) {
                return;
            }
            event.preventDefault();
            const indexRaw = button.dataset.index ?? '';
            const index = Number.parseInt(indexRaw, 10);
            if (!Number.isNaN(index)) {
                removeImageAt(index);
            }
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

    return openComponentModal;
};
