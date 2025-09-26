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

const getHtmx = (): HTMX | null => {
    const win = window as unknown as { htmx?: HTMX };
    return win.htmx ?? null;
};

const focusFirstField = (container: HTMLElement) => {
    const field = container.querySelector<HTMLElement>(
        'input, select, textarea'
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
