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

type DependencyOperator = 'and' | 'or';

type DependencyRuleGroup = {
    operator: DependencyOperator;
    rules: DependencyRule[];
};

type DependencyTreePayload = {
    operator: DependencyOperator;
    rules: DependencyRuleGroup[];
    forbidden_component_ids: number[];
};

const parseDependencyRules = (raw: string): DependencyTreePayload => {
    if (!raw.trim()) {
        return { operator: 'and', rules: [], forbidden_component_ids: [] };
    }

    const toComponentId = (value: unknown): number | null => {
        const numeric =
            typeof value === 'number'
                ? value
                : typeof value === 'string'
                  ? Number.parseInt(value, 10)
                  : Number.NaN;

        if (!Number.isFinite(numeric) || numeric <= 0) {
            return null;
        }

        return Math.trunc(numeric);
    };

    const parseRules = (decoded: unknown): DependencyRule[] => {
        if (!Array.isArray(decoded)) {
            return [];
        }

        const rules: DependencyRule[] = [];
        decoded.forEach((entry) => {
            if (!entry || typeof entry !== 'object') {
                return;
            }
            const numeric = toComponentId(
                (entry as { component_id?: unknown }).component_id
            );
            if (numeric === null) {
                return;
            }
            rules.push({ component_id: numeric });
        });

        return rules;
    };

    const parseForbidden = (decoded: unknown): number[] => {
        if (!Array.isArray(decoded)) {
            return [];
        }

        const forbidden: number[] = [];
        decoded.forEach((entry) => {
            const id = toComponentId(entry);
            if (id === null || forbidden.includes(id)) {
                return;
            }
            forbidden.push(id);
        });

        return forbidden;
    };

    const parseGroups = (
        operator: DependencyOperator,
        decoded: unknown
    ): DependencyRuleGroup[] => {
        if (!Array.isArray(decoded)) {
            return [];
        }

        const groups: DependencyRuleGroup[] = [];
        let hasLegacyRules = false;

        decoded.forEach((entry) => {
            if (!entry || typeof entry !== 'object') {
                return;
            }

            const nestedRules = parseRules((entry as { rules?: unknown }).rules);
            if (nestedRules.length > 0) {
                const nestedOperator: DependencyOperator =
                    (entry as { operator?: unknown }).operator === 'or'
                        ? 'or'
                        : 'and';
                groups.push({ operator: nestedOperator, rules: nestedRules });
                return;
            }

            const legacyComponentId = toComponentId(
                (entry as { component_id?: unknown }).component_id
            );
            if (legacyComponentId !== null) {
                hasLegacyRules = true;
            }
        });

        if (groups.length > 0) {
            return groups;
        }

        if (!hasLegacyRules) {
            return [];
        }

        return [{ operator, rules: parseRules(decoded) }];
    };

    try {
        const decoded = JSON.parse(raw) as unknown;

        if (Array.isArray(decoded)) {
            return {
                operator: 'and',
                rules: [{ operator: 'and', rules: parseRules(decoded) }],
                forbidden_component_ids: [],
            };
        }

        if (!decoded || typeof decoded !== 'object') {
            return { operator: 'and', rules: [], forbidden_component_ids: [] };
        }

        const operatorRaw = (decoded as { operator?: unknown }).operator;
        const operator: DependencyOperator =
            operatorRaw === 'or' ? 'or' : 'and';
        const rules = parseGroups(
            operator,
            (decoded as { rules?: unknown }).rules
        );
        const forbidden = parseForbidden(
            (decoded as { forbidden_component_ids?: unknown })
                .forbidden_component_ids
        );

        return { operator, rules, forbidden_component_ids: forbidden };
    } catch {
        return { operator: 'and', rules: [], forbidden_component_ids: [] };
    }
};

const setupDependencyEditor = (form: HTMLFormElement) => {
    const dependencyInput = form.querySelector<HTMLInputElement>(
        '[data-dependency-tree-input]'
    );
    const editor = form.querySelector<HTMLElement>('[data-dependency-editor]');
    const addButton = form.querySelector<HTMLButtonElement>(
        '[data-dependency-add]'
    );
    const operatorInput = form.querySelector<HTMLInputElement>(
        '[data-dependency-operator-input]'
    );
    const operatorSelect = form.querySelector<HTMLElement>(
        '[data-dependency-operator-select]'
    );
    const groupList = form.querySelector<HTMLElement>('[data-dependency-group-list]');
    const addGroupButton = form.querySelector<HTMLButtonElement>(
        '[data-dependency-group-add]'
    );
    const groupTemplate = form.querySelector<HTMLTemplateElement>(
        '[data-dependency-group-template]'
    );
    const rowTemplate = form.querySelector<HTMLTemplateElement>(
        '[data-dependency-row-template]'
    );
    const forbiddenList = form.querySelector<HTMLElement>('[data-forbidden-list]');
    const forbiddenAddButton = form.querySelector<HTMLButtonElement>(
        '[data-forbidden-add]'
    );
    const forbiddenRowTemplate = form.querySelector<HTMLTemplateElement>(
        '[data-forbidden-row-template]'
    );

    if (
        !dependencyInput ||
        !editor ||
        !addButton ||
        !groupList ||
        !addGroupButton ||
        !groupTemplate ||
        !rowTemplate ||
        !operatorInput ||
        !operatorSelect ||
        !forbiddenList ||
        !forbiddenAddButton ||
        !forbiddenRowTemplate
    ) {
        return;
    }

    const syncDependencyInput = () => {
        const groups = Array.from(
            groupList.querySelectorAll<HTMLElement>('[data-dependency-group]')
        );
        const payload: DependencyRuleGroup[] = [];
        groups.forEach((group) => {
            const groupOperatorInput = group.querySelector<HTMLInputElement>(
                '[data-dependency-group-operator-input]'
            );
            const groupOperator: DependencyOperator =
                groupOperatorInput?.value === 'or' ? 'or' : 'and';
            const groupRows = Array.from(
                group.querySelectorAll<HTMLElement>('[data-dependency-item]')
            );
            const groupRules: DependencyRule[] = [];
            groupRows.forEach((row) => {
                const hidden = row.querySelector<HTMLInputElement>(
                    '[data-dependency-component-id]'
                );
                const raw = hidden?.value ?? '';
                const parsed = Number.parseInt(raw, 10);
                if (!Number.isNaN(parsed) && parsed > 0) {
                    groupRules.push({ component_id: parsed });
                }
            });

            if (groupRules.length > 0) {
                payload.push({ operator: groupOperator, rules: groupRules });
            }
        });

        const forbiddenIds: number[] = [];
        const forbiddenRows = Array.from(
            forbiddenList.querySelectorAll<HTMLElement>('[data-forbidden-item]')
        );
        forbiddenRows.forEach((row) => {
            const hidden = row.querySelector<HTMLInputElement>(
                '[data-forbidden-component-id]'
            );
            const parsed = Number.parseInt(hidden?.value ?? '', 10);
            if (!Number.isNaN(parsed) && parsed > 0 && !forbiddenIds.includes(parsed)) {
                forbiddenIds.push(parsed);
            }
        });

        const operator = operatorInput.value === 'or' ? 'or' : 'and';
        dependencyInput.value = JSON.stringify({
            operator,
            rules: payload,
            forbidden_component_ids: forbiddenIds,
        });
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

    const addDependencyRow = (targetList: HTMLElement, componentId = '') => {
        const fragment = rowTemplate.content.cloneNode(
            true
        ) as DocumentFragment;
        const row = fragment.querySelector<HTMLElement>(
            '[data-dependency-item]'
        );
        if (!row) {
            return;
        }
        targetList.appendChild(row);
        enhanceSelects(row);
        bindRow(row, componentId);
        syncDependencyInput();
    };

    const addGroup = (operator: DependencyOperator = 'and', componentIds: string[] = []) => {
        const fragment = groupTemplate.content.cloneNode(true) as DocumentFragment;
        const group = fragment.querySelector<HTMLElement>('[data-dependency-group]');
        const groupOperatorInput = fragment.querySelector<HTMLInputElement>(
            '[data-dependency-group-operator-input]'
        );
        const groupOperatorSelect = fragment.querySelector<HTMLElement>(
            '[data-dependency-group-operator-select]'
        );
        const groupRulesList = fragment.querySelector<HTMLElement>(
            '[data-dependency-rules-list]'
        );
        const groupAddRule = fragment.querySelector<HTMLButtonElement>(
            '[data-dependency-rule-add]'
        );
        const groupRemove = fragment.querySelector<HTMLButtonElement>(
            '[data-dependency-group-remove]'
        );

        if (
            !group ||
            !groupOperatorInput ||
            !groupOperatorSelect ||
            !groupRulesList ||
            !groupAddRule ||
            !groupRemove
        ) {
            return;
        }

        groupList.appendChild(group);
        enhanceSelects(group);
        groupOperatorInput.value = operator;
        groupOperatorSelect.setAttribute('data-value', operator);
        setSelectValue(groupOperatorSelect, operator);

        groupOperatorSelect.addEventListener('select:change', (event) => {
            const detail = (event as CustomEvent<SelectChangeDetail>).detail;
            groupOperatorInput.value = detail?.value === 'or' ? 'or' : 'and';
            syncDependencyInput();
        });

        groupAddRule.addEventListener('click', (event) => {
            event.preventDefault();
            addDependencyRow(groupRulesList, '');
        });

        groupRemove.addEventListener('click', (event) => {
            event.preventDefault();
            group.remove();
            syncDependencyInput();
        });

        if (componentIds.length > 0) {
            componentIds.forEach((componentId) => {
                addDependencyRow(groupRulesList, componentId);
            });
        }

        syncDependencyInput();
    };

    const addForbiddenRow = (componentId = '') => {
        const fragment = forbiddenRowTemplate.content.cloneNode(
            true
        ) as DocumentFragment;
        const row = fragment.querySelector<HTMLElement>('[data-forbidden-item]');
        const hidden = fragment.querySelector<HTMLInputElement>(
            '[data-forbidden-component-id]'
        );
        const select = fragment.querySelector<HTMLElement>('.select[data-select]');
        const remove = fragment.querySelector<HTMLButtonElement>('[data-forbidden-remove]');

        if (!row || !hidden || !select || !remove) {
            return;
        }

        forbiddenList.appendChild(row);
        enhanceSelects(row);
        hidden.value = componentId;
        select.setAttribute('data-value', componentId);
        setSelectValue(select, componentId);

        select.addEventListener('select:change', (event) => {
            const detail = (event as CustomEvent<SelectChangeDetail>).detail;
            hidden.value = detail?.value ?? '';
            syncDependencyInput();
        });

        remove.addEventListener('click', (event) => {
            event.preventDefault();
            row.remove();
            syncDependencyInput();
        });

        syncDependencyInput();
    };

    const existingTree = parseDependencyRules(dependencyInput.value);
    operatorInput.value = existingTree.operator;
    operatorSelect.setAttribute('data-value', existingTree.operator);
    setSelectValue(operatorSelect, existingTree.operator);
    operatorSelect.addEventListener('select:change', (event) => {
        const detail = (event as CustomEvent<SelectChangeDetail>).detail;
        operatorInput.value = detail?.value === 'or' ? 'or' : 'and';
        syncDependencyInput();
    });

    groupList.innerHTML = '';
    existingTree.rules.forEach((group) => {
        addGroup(
            group.operator,
            group.rules.map((rule) => String(rule.component_id))
        );
    });
    if (existingTree.rules.length === 0) {
        addGroup('and');
    }

    addButton.addEventListener('click', (event) => {
        event.preventDefault();
        const firstGroup = groupList.querySelector<HTMLElement>('[data-dependency-group]');
        const firstGroupRules = firstGroup?.querySelector<HTMLElement>('[data-dependency-rules-list]');
        if (firstGroupRules) {
            addDependencyRow(firstGroupRules, '');
        }
    });

    addGroupButton.addEventListener('click', (event) => {
        event.preventDefault();
        addGroup('and');
    });

    forbiddenList.innerHTML = '';
    existingTree.forbidden_component_ids.forEach((id) => {
        addForbiddenRow(String(id));
    });
    forbiddenAddButton.addEventListener('click', (event) => {
        event.preventDefault();
        addForbiddenRow('');
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
