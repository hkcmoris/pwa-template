import { enhanceSelects, setSelectValue } from '../select';
import type {
    MediaMode,
    PriceHistoryItem,
    SelectChangeDetail,
} from './types';

export const COMPONENT_FORM_ERROR_ID = 'component-form-errors';

export const setFormError = (message: string | null) => {
    const box = document.getElementById(COMPONENT_FORM_ERROR_ID);
    if (!(box instanceof HTMLElement)) {
        return;
    }
    if (message && message.trim()) {
        box.textContent = message;
        box.classList.remove('hidden');
        box.classList.add('form-feedback--error');
        box.setAttribute('role', 'alert');
        box.dataset.clientError = 'true';
    } else if (box.dataset.clientError === 'true') {
        box.textContent = '';
        box.classList.add('hidden');
        box.removeAttribute('role');
        delete box.dataset.clientError;
    }
};

const toNumericPrice = (value: string | undefined | null): number | null => {
    if (typeof value !== 'string') {
        return null;
    }
    const trimmed = value.trim();
    if (!trimmed) {
        return null;
    }
    const compact = trimmed.replace(/\s+/g, '').replace(',', '.');
    if (!/^\d+(?:\.\d+)?$/u.test(compact)) {
        return null;
    }
    const numeric = Number.parseFloat(compact);
    return Number.isFinite(numeric) ? numeric : null;
};

export const formatPriceForInput = (
    value: string | undefined | null
): string => {
    const numeric = toNumericPrice(value);
    if (numeric === null) {
        return '';
    }
    return numeric.toFixed(2).replace('.', ',');
};

const normalisePriceInput = (value: string): string => {
    const trimmed = value.trim();
    if (!trimmed) {
        return '';
    }
    const compact = trimmed.replace(/\s+/g, '');
    const normalised = compact.replace(',', '.');
    if (!/^\d+(?:\.\d{0,2})?$/u.test(normalised)) {
        return trimmed;
    }
    const numeric = Number.parseFloat(normalised);
    if (!Number.isFinite(numeric)) {
        return trimmed;
    }
    return numeric.toFixed(2).replace('.', ',');
};

export const parsePriceHistoryDataset = (
    raw: string | undefined
): PriceHistoryItem[] | undefined => {
    if (!raw) {
        return undefined;
    }
    try {
        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) {
            return undefined;
        }
        return parsed.filter(
            (item): item is PriceHistoryItem =>
                item !== null && typeof item === 'object'
        );
    } catch {
        return undefined;
    }
};

export const renderPriceHistory = (
    list: HTMLElement,
    history: PriceHistoryItem[],
    fallbackCurrency: string
) => {
    list.innerHTML = '';
    if (!history.length) {
        const emptyItem = document.createElement('li');
        emptyItem.className = 'component-price-history-empty';
        emptyItem.textContent = 'Žádné záznamy.';
        list.appendChild(emptyItem);
        return;
    }

    history.forEach((entry) => {
        const item = document.createElement('li');
        item.className = 'component-price-history-item';

        const amountSpan = document.createElement('span');
        amountSpan.className = 'component-price-history-amount';
        const currency = (
            entry.currency ??
            fallbackCurrency ??
            'CZK'
        ).toUpperCase();
        const numericAmount = toNumericPrice(entry.amount ?? '');
        if (numericAmount !== null) {
            try {
                const formatter = new Intl.NumberFormat('cs-CZ', {
                    style: 'currency',
                    currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                amountSpan.textContent = formatter.format(numericAmount);
            } catch {
                amountSpan.textContent = `${numericAmount.toFixed(2)} ${currency}`;
            }
        } else if (entry.amount) {
            amountSpan.textContent = `${entry.amount} ${currency}`;
        } else {
            amountSpan.textContent = `— ${currency}`;
        }

        const time = document.createElement('time');
        const rawDate = entry.created_at ?? '';
        if (rawDate) {
            const parsedDate = new Date(rawDate);
            if (!Number.isNaN(parsedDate.getTime())) {
                time.dateTime = parsedDate.toISOString();
                time.textContent = new Intl.DateTimeFormat('cs-CZ', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(parsedDate);
            } else {
                time.textContent = rawDate;
            }
        } else {
            time.textContent = '—';
        }

        item.appendChild(amountSpan);
        item.appendChild(time);
        list.appendChild(item);
    });
};

export const setupComponentForm = (form: HTMLFormElement) => {
    const wrappers = Array.from(
        form.querySelectorAll<HTMLElement>('[data-select-wrapper]')
    );
    if (!wrappers.length) {
        return;
    }

    enhanceSelects(form);

    wrappers.forEach((wrapper) => {
        const selectEl = wrapper.querySelector<HTMLElement>(
            '.select[data-select]'
        );
        const hiddenInput = wrapper.querySelector<HTMLInputElement>(
            'input[type="hidden"]'
        );
        if (!selectEl || !hiddenInput) {
            return;
        }

        const button =
            selectEl.querySelector<HTMLButtonElement>('.select-button');
        const required = selectEl.dataset.required === 'true';

        const updateValidity = (value: string) => {
            if (!required) {
                return;
            }
            if (value) {
                button?.removeAttribute('aria-invalid');
                wrapper.removeAttribute('data-select-invalid');
                setFormError(null);
            } else {
                button?.setAttribute('aria-invalid', 'true');
                wrapper.setAttribute('data-select-invalid', 'true');
            }
        };

        const initialValue =
            hiddenInput.value || selectEl.getAttribute('data-value') || '';
        setSelectValue(selectEl, initialValue);
        updateValidity(initialValue);

        selectEl.addEventListener('select:change', (event) => {
            const detail = (event as CustomEvent<SelectChangeDetail>).detail;
            const value = detail?.value ?? '';
            hiddenInput.value = value;
            updateValidity(value);
        });
    });

    const mediaRadios = Array.from(
        form.querySelectorAll<HTMLInputElement>(
            'input[name="media_type"][data-media-choice]'
        )
    );
    const mediaPanels = Array.from(
        form.querySelectorAll<HTMLElement>('[data-media-panel]')
    );
    const colorText = form.querySelector<HTMLInputElement>('[data-color-text]');
    const colorPicker = form.querySelector<HTMLInputElement>(
        '[data-color-picker]'
    );

    const togglePanel = (panel: HTMLElement, active: boolean) => {
        panel.classList.toggle('hidden', !active);
        panel
            .querySelectorAll<HTMLInputElement>('input, select, textarea')
            .forEach((input) => {
                if (active) {
                    input.removeAttribute('disabled');
                } else {
                    input.setAttribute('disabled', 'true');
                }
            });
    };

    const normaliseHex = (value: string): string => {
        const raw = value.trim();
        if (!raw) {
            return '';
        }
        const prefixed = raw.startsWith('#') ? raw : `#${raw}`;
        return prefixed.toUpperCase();
    };

    const applyColorToPicker = (hex: string) => {
        if (!colorPicker) {
            return;
        }
        if (/^#[0-9A-F]{6}$/u.test(hex)) {
            colorPicker.value = hex;
            return;
        }
        if (/^#[0-9A-F]{3}$/u.test(hex)) {
            const expanded = `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
            colorPicker.value = expanded.toUpperCase();
        }
    };

    let mediaMode: MediaMode =
        (form.dataset.mediaMode as MediaMode | undefined) ??
        (colorText && colorText.value.trim() ? 'color' : 'image');
    delete form.dataset.mediaMode;

    const updateMediaMode = (mode: MediaMode) => {
        mediaMode = mode;
        mediaPanels.forEach((panel) => {
            const panelMode = panel.dataset.mediaPanel as MediaMode | undefined;
            togglePanel(panel, panelMode === mode);
        });
        mediaRadios.forEach((radio) => {
            radio.checked = radio.dataset.mediaChoice === mode;
        });
        if (mode === 'color') {
            if (colorText && colorText.value.trim()) {
                const hex = normaliseHex(colorText.value);
                colorText.value = hex;
                applyColorToPicker(hex);
            } else if (colorPicker) {
                if (colorText) {
                    colorText.value = colorPicker.value.toUpperCase();
                }
            }
        }
    };

    mediaRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (!radio.checked) {
                return;
            }
            const choice = (radio.dataset.mediaChoice as MediaMode) ?? 'image';
            updateMediaMode(choice);
        });
    });

    colorPicker?.addEventListener('input', () => {
        const value = colorPicker.value.toUpperCase();
        if (colorText) {
            colorText.value = value;
        }
    });

    colorText?.addEventListener('blur', () => {
        const value = normaliseHex(colorText.value);
        colorText.value = value;
        applyColorToPicker(value);
    });

    const priceInput =
        form.querySelector<HTMLInputElement>('[data-price-input]');
    priceInput?.addEventListener('blur', () => {
        priceInput.value = normalisePriceInput(priceInput.value);
    });

    updateMediaMode(mediaMode);

    form.addEventListener('submit', (event) => {
        let firstInvalidButton: HTMLButtonElement | null = null;
        form.querySelectorAll<HTMLElement>(
            '.select[data-select][data-required="true"]'
        ).forEach((selectEl) => {
            const wrapper = selectEl.closest(
                '[data-select-wrapper]'
            ) as HTMLElement | null;
            const hiddenInput = wrapper?.querySelector<HTMLInputElement>(
                'input[type="hidden"]'
            );
            const button =
                selectEl.querySelector<HTMLButtonElement>('.select-button');
            if (!hiddenInput || !button) {
                return;
            }
            if (!hiddenInput.value) {
                if (!firstInvalidButton) {
                    firstInvalidButton = button;
                }
                button.setAttribute('aria-invalid', 'true');
                wrapper?.setAttribute('data-select-invalid', 'true');
            }
        });

        if (firstInvalidButton) {
            event.preventDefault();
            (firstInvalidButton as HTMLButtonElement).focus();
            setFormError('Vyberte definici komponenty.');
        }
    });
};
