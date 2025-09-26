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
};

type AfterRequestDetail = {
    xhr?: XMLHttpRequest;
};

type SelectChangeDetail = {
    value?: string;
};

const COMPONENT_FORM_ERROR_ID = 'component-form-errors';

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
        const selectEl = wrapper.querySelector<HTMLElement>('.select[data-select]');
        const hiddenInput = wrapper.querySelector<HTMLInputElement>(
            'input[type="hidden"]'
        );
        if (!selectEl || !hiddenInput) {
            return;
        }

        const button = selectEl.querySelector<HTMLButtonElement>('.select__button');
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

    form.addEventListener('submit', (event) => {
        let firstInvalidButton: HTMLButtonElement | null = null;
        form.querySelectorAll<HTMLElement>(
            '.select[data-select][data-required="true"]'
        ).forEach((selectEl) => {
            const wrapper = selectEl.closest('[data-select-wrapper]') as
                | HTMLElement
                | null;
            const hiddenInput = wrapper?.querySelector<HTMLInputElement>(
                'input[type="hidden"]'
            );
            const button = selectEl.querySelector<HTMLButtonElement>(
                '.select__button'
            );
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
            firstInvalidButton.focus();
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
        'button.select__button, input:not([type="hidden"]), textarea, select'
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

    if (!modalRoot || !createTemplate) {
        return;
    }

    let escHandler: ((event: KeyboardEvent) => void) | null = null;

    const closeModal = () => {
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
            <div class="components-modal__overlay" data-modal-close></div>
            <div class="components-modal__panel" role="dialog" aria-modal="true">
              <header>
                <h3>${escapeHtml(title)}</h3>
                <button type="button" class="component-action" data-modal-close aria-label="Zavřít">×</button>
              </header>
              <div class="components-modal__body"></div>
            </div>
        `;
        const bodyContainer = modalRoot.querySelector(
            '.components-modal__body'
        ) as HTMLElement | null;
        if (!bodyContainer) {
            return;
        }
        bodyContainer.appendChild(body);
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

    const openCreateModal = () => {
        const fragment = createTemplate.content.cloneNode(
            true
        ) as DocumentFragment;
        const form = fragment.querySelector('form');
        if (!form) {
            return;
        }
        openModal('Přidat komponentu', form as HTMLElement);
        focusFirstField(form as HTMLElement);
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
        openCreateModal();
    });
}




