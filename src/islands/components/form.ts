import { enhanceSelects, setSelectValue } from '../shared/select';
import type { MediaMode, PriceHistoryItem, SelectChangeDetail } from './types';

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

type DependencyRule = {
    component_id: number;
};

const parseDependencyRules = (raw: string): DependencyRule[] => {
    if (!raw.trim()) {
        return [];
    }

    try {
        const decoded = JSON.parse(raw) as unknown;
        if (!Array.isArray(decoded)) {
            return [];
        }
        const rules: DependencyRule[] = [];
        decoded.forEach((entry) => {
            if (!entry || typeof entry !== 'object') {
                return;
            }
            const value = (entry as { component_id?: unknown }).component_id;
            const numeric =
                typeof value === 'number'
                    ? value
                    : typeof value === 'string'
                      ? Number.parseInt(value, 10)
                      : Number.NaN;
            if (!Number.isFinite(numeric) || numeric <= 0) {
                return;
            }
            rules.push({ component_id: Math.trunc(numeric) });
        });
        return rules;
    } catch {
        return [];
    }
};

const setupDependencyEditor = (form: HTMLFormElement) => {
    const dependencyInput = form.querySelector<HTMLInputElement>(
        '[data-dependency-tree-input]'
    );
    const editor = form.querySelector<HTMLElement>('[data-dependency-editor]');
    const list = form.querySelector<HTMLElement>('[data-dependency-list]');
    const addButton = form.querySelector<HTMLButtonElement>('[data-dependency-add]');
    const rowTemplate = form.querySelector<HTMLTemplateElement>(
        '[data-dependency-row-template]'
    );

    if (!dependencyInput || !editor || !list || !addButton || !rowTemplate) {
        return;
    }

    const syncDependencyInput = () => {
        const rows = Array.from(
            list.querySelectorAll<HTMLElement>('[data-dependency-item]')
        );
        const payload: DependencyRule[] = [];
        rows.forEach((row) => {
            const hidden = row.querySelector<HTMLInputElement>(
                '[data-dependency-component-id]'
            );
            const raw = hidden?.value ?? '';
            const parsed = Number.parseInt(raw, 10);
            if (!Number.isNaN(parsed) && parsed > 0) {
                payload.push({ component_id: parsed });
            }
        });
        dependencyInput.value = JSON.stringify(payload);
    };

    const bindRow = (row: HTMLElement, initialValue = '') => {
        const wrapper = row.querySelector<HTMLElement>('[data-select-wrapper]');
        const hidden = row.querySelector<HTMLInputElement>(
            '[data-dependency-component-id]'
        );
        const select = row.querySelector<HTMLElement>('.select[data-select]');
        const removeButton = row.querySelector<HTMLButtonElement>(
            '[data-dependency-remove]'
        );

        if (!wrapper || !hidden || !select || !removeButton) {
            return;
        }

        hidden.value = initialValue;
        select.setAttribute('data-value', initialValue);
        setSelectValue(select, initialValue);

        select.addEventListener('select:change', (event) => {
            const detail = (event as CustomEvent<SelectChangeDetail>).detail;
            hidden.value = detail?.value ?? '';
            syncDependencyInput();
        });

        removeButton.addEventListener('click', (event) => {
            event.preventDefault();
            row.remove();
            syncDependencyInput();
        });
    };

    const addDependencyRow = (componentId = '') => {
        const fragment = rowTemplate.content.cloneNode(true) as DocumentFragment;
        const row = fragment.querySelector<HTMLElement>('[data-dependency-item]');
        if (!row) {
            return;
        }
        list.appendChild(row);
        enhanceSelects(row);
        bindRow(row, componentId);
        syncDependencyInput();
    };

    const existingRules = parseDependencyRules(dependencyInput.value);
    list.innerHTML = '';
    existingRules.forEach((rule) => {
        addDependencyRow(String(rule.component_id));
    });

    addButton.addEventListener('click', (event) => {
        event.preventDefault();
        addDependencyRow('');
    });

    syncDependencyInput();
};


export const setupComponentForm = (form: HTMLFormElement) => {
    const tabs = Array.from(
        form.querySelectorAll<HTMLButtonElement>('[data-component-tab]')
    );
    const panels = Array.from(
        form.querySelectorAll<HTMLElement>('[data-component-panel]')
    );

    const activateTab = (key: string, shouldFocus = false) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.componentTab === key;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                tab.removeAttribute('tabindex');
                if (shouldFocus) {
                    tab.focus();
                }
            } else {
                tab.setAttribute('tabindex', '-1');
            }
        });

        panels.forEach((panel) => {
            const active = panel.dataset.componentPanel === key;
            panel.classList.toggle('hidden', !active);
        });
    };

    if (tabs.length > 0 && panels.length > 0) {
        const initialTab =
            tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ??
            tabs[0];
        if (initialTab?.dataset.componentTab) {
            activateTab(initialTab.dataset.componentTab);
        }

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                const key = tab.dataset.componentTab;
                if (key) {
                    activateTab(key);
                }
            });

            tab.addEventListener('keydown', (event) => {
                if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                    return;
                }
                event.preventDefault();
                const delta = event.key === 'ArrowRight' ? 1 : -1;
                const nextIndex = (index + delta + tabs.length) % tabs.length;
                const nextTab = tabs[nextIndex];
                const key = nextTab?.dataset.componentTab;
                if (key) {
                    activateTab(key, true);
                }
            });
        });
    }

    setupDependencyEditor(form);

    const wrappers = Array.from(
        form.querySelectorAll<HTMLElement>('[data-select-wrapper]')
    );

    if (wrappers.length > 0) {
        enhanceSelects(form);
    }

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
