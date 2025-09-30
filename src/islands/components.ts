import { enhanceSelects, setSelectValue } from './select';

const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

type HTMX = {
    process?: (elt: Element) => void;
    ajax?: (
        method: string,
        url: string,
        options: {
            source?: Element | string;
            values?: Record<string, unknown>;
            target?: Element | string;
            swap?: string;
            select?: string;
        }
    ) => XMLHttpRequest;
};

type AfterRequestDetail = {
    xhr?: XMLHttpRequest;
};

type SelectChangeDetail = {
    value?: string;
};

const COMPONENT_FORM_ERROR_ID = 'component-form-errors';

type ComponentModalMode = 'create' | 'edit';

type ComponentModalOptions = {
    mode?: ComponentModalMode;
    componentId?: string;
    definitionId?: string;
    parentId?: string;
    parentTitle?: string;
    childCount?: number;
    alternateTitle?: string;
    description?: string;
    image?: string;
    color?: string;
    mediaType?: 'image' | 'color';
    position?: number;
    displayName?: string;
};

const setFormError = (message: string | null) => {
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

const setupComponentForm = (form: HTMLFormElement) => {
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

    type MediaMode = 'image' | 'color';

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

const getHtmx = (): HTMX | null => {
    const win = window as unknown as { htmx?: HTMX };
    return win.htmx ?? null;
};

const focusFirstField = (container: HTMLElement) => {
    const field = container.querySelector<HTMLElement>(
        'button.select-button, input:not([type="hidden"]), textarea, select'
    );
    field?.focus();
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
    const createUrl = `${basePath}/editor/components-create`;
    const updateUrl = `${basePath}/editor/components-update`;
    const deleteUrl = `${basePath}/editor/components-delete`;
    const listTarget = '#components-list';

    if (!modalRoot || !createTemplate) {
        return;
    }

    const errorBox = document.getElementById(
        COMPONENT_FORM_ERROR_ID
    ) as HTMLElement | null;
    let errorAnchor: Comment | null = null;

    if (errorBox && errorBox.parentNode) {
        const previousSibling = errorBox.previousSibling;
        if (
            previousSibling &&
            previousSibling.nodeType === Node.COMMENT_NODE &&
            (previousSibling as Comment).data === 'component-form-errors-anchor'
        ) {
            errorAnchor = previousSibling as Comment;
        } else {
            errorAnchor = document.createComment(
                'component-form-errors-anchor'
            );
            errorBox.parentNode.insertBefore(errorAnchor, errorBox);
        }
    }

    const restoreErrors = () => {
        if (!errorBox || !errorAnchor || !errorAnchor.parentNode) {
            return;
        }
        errorAnchor.parentNode.insertBefore(errorBox, errorAnchor.nextSibling);
    };

    const moveErrorsIntoModal = (bodyContainer: HTMLElement | null) => {
        if (!errorBox || !bodyContainer || !modalRoot) {
            return;
        }
        const panel = bodyContainer.closest(
            '.components-modal-panel'
        ) as HTMLElement | null;
        if (!panel || panel.contains(errorBox)) {
            return;
        }
        panel.insertBefore(errorBox, bodyContainer);
    };

    let escHandler: ((event: KeyboardEvent) => void) | null = null;

    const closeModal = () => {
        restoreErrors();
        modalRoot.classList.add('hidden');
        modalRoot.setAttribute('aria-hidden', 'true');
        modalRoot.innerHTML = '';
        setFormError(null);
        if (escHandler) {
            document.removeEventListener('keydown', escHandler);
            escHandler = null;
        }
    };

    const openModal = (title: string, body: HTMLElement) => {
        modalRoot.innerHTML = `
            <div class="components-modal-overlay" data-modal-close></div>
            <div class="components-modal-panel" role="dialog" aria-modal="true">
              <header>
                <h3>${escapeHtml(title)}</h3>
                <button type="button" class="component-action" data-modal-close aria-label="Zavřít">×</button>
              </header>
              <div class="components-modal-body"></div>
            </div>
        `;
        const bodyContainer = modalRoot.querySelector(
            '.components-modal-body'
        ) as HTMLElement | null;
        if (!bodyContainer) {
            return;
        }
        bodyContainer.appendChild(body);
        moveErrorsIntoModal(bodyContainer);
        if (htmx && typeof htmx.process === 'function') {
            htmx.process(body);
        }
        if (body instanceof HTMLFormElement) {
            setupComponentForm(body);
        }
        modalRoot.classList.remove('hidden');
        modalRoot.setAttribute('aria-hidden', 'false');
        modalRoot.querySelectorAll('[data-modal-close]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        });

        escHandler = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
            }
        };
        document.addEventListener('keydown', escHandler);
    };

    const sendDeleteRequest = (componentId: string) => {
        if (!componentId) {
            return;
        }

        if (htmx && typeof htmx.ajax === 'function') {
            htmx.ajax('POST', deleteUrl, {
                source: listTarget,
                target: listTarget,
                select: listTarget,
                swap: 'outerHTML',
                values: { component_id: componentId },
            });
            return;
        }

        const bodyEl = document.body;
        if (!bodyEl) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'post';
        form.action = deleteUrl;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'component_id';
        input.value = componentId;
        form.appendChild(input);

        bodyEl.appendChild(form);
        form.submit();
    };

    const openComponentModal = (options?: ComponentModalOptions) => {
        const mode = options?.mode ?? 'create';
        const isEdit = mode === 'edit';
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
        const colorField = form.querySelector<HTMLInputElement>(
            '#component-modal-color'
        );
        const colorPicker = form.querySelector<HTMLInputElement>(
            '#component-modal-color-swatch'
        );
        const positionInput = form.querySelector<HTMLInputElement>(
            '#component-modal-position'
        );
        const legend = form.querySelector('legend');
        const submitButton = form.querySelector<HTMLButtonElement>(
            'button[type="submit"]'
        );

        const definitionValue = options?.definitionId ?? '';
        if (definitionHidden) {
            definitionHidden.value = definitionValue;
        }
        const parentValue = options?.parentId ?? '';
        if (parentHidden) {
            parentHidden.value = parentValue;
        }
        if (componentIdInput) {
            componentIdInput.value = isEdit ? (options?.componentId ?? '') : '';
        }
        const imageValue = options?.image ?? '';
        const rawColorValue = options?.color ?? '';
        const normalizedColor = (() => {
            const trimmed = rawColorValue.trim();
            if (!trimmed) {
                return '';
            }
            const prefixed = trimmed.startsWith('#') ? trimmed : `#${trimmed}`;
            return prefixed.toUpperCase();
        })();
        const mediaMode =
            options?.mediaType ?? (normalizedColor ? 'color' : 'image');
        if (alternateInput) {
            alternateInput.value = options?.alternateTitle ?? '';
        }
        if (descriptionField) {
            descriptionField.value = options?.description ?? '';
        }
        if (imageField) {
            imageField.value = mediaMode === 'image' ? imageValue : '';
        }
        if (colorField) {
            colorField.value = normalizedColor;
        }
        if (colorPicker) {
            const pickerColor = /^#[0-9A-F]{6}$/u.test(normalizedColor)
                ? normalizedColor
                : /^#[0-9A-F]{3}$/u.test(normalizedColor)
                  ? `#${normalizedColor[1]}${normalizedColor[1]}${normalizedColor[2]}${normalizedColor[2]}${normalizedColor[3]}${normalizedColor[3]}`.toUpperCase()
                  : '#ffffff';
            colorPicker.value = pickerColor;
        }
        form.dataset.mediaMode = mediaMode;
        if (positionInput) {
            if (
                isEdit &&
                options?.position !== undefined &&
                options.position !== null
            ) {
                positionInput.value = String(options.position);
            } else if (
                !isEdit &&
                typeof options?.childCount === 'number' &&
                Number.isFinite(options.childCount)
            ) {
                positionInput.value = String(options.childCount);
            } else {
                positionInput.value = '';
            }
        }

        const definitionSelect = definitionHidden
            ?.closest('[data-select-wrapper]')
            ?.querySelector<HTMLElement>('.select[data-select]');
        const parentSelect = parentHidden
            ?.closest('[data-select-wrapper]')
            ?.querySelector<HTMLElement>('.select[data-select]');
        if (definitionSelect) {
            definitionSelect.setAttribute('data-value', definitionValue);
        }
        if (parentSelect) {
            parentSelect.setAttribute('data-value', parentValue);
        }

        if (legend) {
            if (isEdit) {
                legend.textContent = 'Upravit komponentu';
            } else {
                const parentTitle = options?.parentTitle?.trim() ?? '';
                legend.textContent = parentTitle
                    ? `Přidat podkomponentu k ${parentTitle}`
                    : 'Přidat novou komponentu';
            }
        }
        if (submitButton) {
            submitButton.textContent = isEdit ? 'Uložit změny' : 'Uložit';
        }

        const modalTitle = isEdit
            ? options?.displayName?.trim()
                ? `Upravit ${options.displayName.trim()}`
                : 'Upravit komponentu'
            : options?.parentTitle?.trim()
              ? 'Přidat podkomponentu'
              : 'Přidat komponentu';

        if (isEdit) {
            form.setAttribute('action', updateUrl);
            form.setAttribute('hx-post', updateUrl);
        } else {
            form.setAttribute('action', createUrl);
            form.setAttribute('hx-post', createUrl);
        }

        openModal(modalTitle, form as HTMLElement);
        focusFirstField(form as HTMLElement);

        if (definitionSelect) {
            setSelectValue(definitionSelect, definitionHidden?.value ?? '');
        }
        if (parentSelect) {
            setSelectValue(parentSelect, parentHidden?.value ?? '');
        }

        form.addEventListener('htmx:afterRequest', (event) => {
            const detail = (event as CustomEvent<AfterRequestDetail>).detail;
            const status = detail?.xhr?.status ?? 0;
            if (status && status < 400) {
                closeModal();
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
                    ? `Tato akce odstrani take vsechny jeji podkomponenty (vcetne ${childCount === 1 ? '1 primo podrizene' : `${childCount} primo podrizenych`} komponent).`
                    : 'Tato akce nelze vratit.';
            container.innerHTML = `
                <p>Opravdu chcete smazat komponentu <strong>${escapeHtml(displayName)}</strong>?</p>
                <p>${escapeHtml(detailText)}</p>
                <div class="components-modal-actions">
                  <button type="button" class="component-action" data-modal-close>Storno</button>
                  <button type="button" class="component-action component-action--danger" data-modal-confirm>Smazat</button>
                </div>
            `;

            openModal('Smazat komponentu', container);

            container
                .querySelector<HTMLButtonElement>('[data-modal-confirm]')
                ?.addEventListener('click', () => {
                    sendDeleteRequest(componentId);
                    closeModal();
                });
        }
    });
}
