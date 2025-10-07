import { setFormError, setupComponentForm } from './form';
import type { HTMX } from './types';
import { escapeHtml } from './utils';

export type ComponentModalManager = {
    open: (title: string, body: HTMLElement) => void;
    close: () => void;
};

export const createComponentModalManager = (
    modalRoot: HTMLElement,
    htmx: HTMX | null,
    errorBox: HTMLElement | null
): ComponentModalManager => {
    let escHandler: ((event: KeyboardEvent) => void) | null = null;
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
            errorAnchor = document.createComment('component-form-errors-anchor');
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

    const close = () => {
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

    const open = (title: string, body: HTMLElement) => {
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
                close();
            });
        });

        escHandler = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            }
        };
        document.addEventListener('keydown', escHandler);
    };

    return { open, close };
};
