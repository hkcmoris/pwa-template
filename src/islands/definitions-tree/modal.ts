import type { HTMX, ModalManager } from './types';
import { escapeHtml } from './utils';

export const createModalManager = (
    modalRoot: HTMLElement,
    htmx: HTMX | null
): ModalManager => {
    let escHandler: ((event: KeyboardEvent) => void) | null = null;

    const close = () => {
        modalRoot.classList.add('hidden');
        modalRoot.setAttribute('aria-hidden', 'true');
        modalRoot.innerHTML = '';
        if (escHandler) {
            document.removeEventListener('keydown', escHandler);
            escHandler = null;
        }
    };

    const open = (title: string, body: HTMLElement) => {
        modalRoot.innerHTML = `
            <div class="definitions-modal-overlay" data-modal-close></div>
            <div class="definitions-modal-panel" role="dialog" aria-modal="true">
              <header>
                <h3>${escapeHtml(title)}</h3>
                <button type="button" class="definition-action" data-modal-close aria-label="Zavřít">×</button>
              </header>
              <div class="definitions-modal-body"></div>
            </div>
        `;

        const bodyContainer = modalRoot.querySelector(
            '.definitions-modal-body'
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
        modalRoot.querySelectorAll('[data-modal-close]').forEach((btn) =>
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                close();
            })
        );

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
