import { getCsrfToken } from '../utils/api';

type ManagerAction = 'rename' | 'delete';

const escapeHtml = (value: string) =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const parseAndSwapOob = (html: string) => {
    const template = document.createElement('template');
    template.innerHTML = html;

    template.content
        .querySelectorAll<HTMLElement>('[hx-swap-oob]')
        .forEach((incoming) => {
            const targetId = incoming.id;
            if (!targetId) {
                return;
            }
            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }
            target.replaceWith(incoming);
        });
};

const postDraftAction = async (
    action: ManagerAction,
    draftId: string,
    title?: string
) => {
    const rootBase = document.documentElement.dataset.base ?? '';
    const path =
        action === 'rename'
            ? '/configurator/wizard/rename'
            : '/configurator/wizard/delete';
    const params = new URLSearchParams();

    params.set('draft_id', draftId);
    if (title !== undefined) {
        params.set('title', title);
    }

    const headers = new Headers({
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'HX-Request': 'true',
    });
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
    }

    const response = await fetch(`${rootBase}${path}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: params.toString(),
    });

    const responseHtml = await response.text();
    parseAndSwapOob(responseHtml);
};

const createModal = (modalRoot: HTMLElement) => {
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
            <div class="konfigurator-manager-modal-overlay" data-modal-close></div>
            <div class="konfigurator-manager-modal-panel" role="dialog" aria-modal="true" aria-labelledby="konfigurator-manager-modal-title">
              <header>
                <h3 id="konfigurator-manager-modal-title">${escapeHtml(title)}</h3>
                <button type="button" class="konfigurator-manager-modal-close" data-modal-close aria-label="Zavřít">×</button>
              </header>
              <div class="konfigurator-manager-modal-body"></div>
            </div>
        `;

        const bodyContainer = modalRoot.querySelector(
            '.konfigurator-manager-modal-body'
        ) as HTMLElement | null;

        if (!bodyContainer) {
            return;
        }

        bodyContainer.appendChild(body);
        modalRoot.classList.remove('hidden');
        modalRoot.setAttribute('aria-hidden', 'false');

        modalRoot.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
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

const openRenameModal = (
    modal: ReturnType<typeof createModal>,
    draftId: string,
    draftTitle: string
) => {
    const form = document.createElement('form');
    form.className = 'konfigurator-manager-form';
    form.innerHTML = `
        <div class="konfigurator-manager-field">
          <label for="konfigurator-manager-rename-title">Nový název</label>
          <input
            id="konfigurator-manager-rename-title"
            type="text"
            name="title"
            maxlength="191"
            value="${escapeHtml(draftTitle)}"
            required
          >
        </div>
        <div class="konfigurator-manager-modal-actions">
          <button type="button" class="configuration-entry-action" data-modal-close>Storno</button>
          <button type="submit" class="configuration-entry-action configuration-entry-action--solid">Uložit</button>
        </div>
    `;

    modal.open('Přejmenovat návrh', form);

    const input = form.querySelector<HTMLInputElement>('input[name="title"]');
    input?.focus();
    input?.select();

    form.querySelector('[data-modal-close]')?.addEventListener('click', (event) => {
        event.preventDefault();
        modal.close();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const title = input?.value.trim() ?? '';
        if (!title || title === draftTitle.trim()) {
            modal.close();
            return;
        }

        await postDraftAction('rename', draftId, title);
        modal.close();
    });
};

const openDeleteModal = (
    modal: ReturnType<typeof createModal>,
    draftId: string,
    draftTitle: string
) => {
    const body = document.createElement('div');
    body.className = 'konfigurator-manager-modal-copy';
    body.innerHTML = `
      <p>Opravdu chcete smazat návrh <strong>${escapeHtml(draftTitle)}</strong>?</p>
      <div class="konfigurator-manager-modal-actions">
        <button type="button" class="configuration-entry-action" data-modal-close>Storno</button>
        <button type="button" class="configuration-entry-action configuration-entry-action--danger configuration-entry-action--solid" data-modal-confirm>Smazat</button>
      </div>
    `;

    modal.open('Smazat návrh', body);

    body.querySelector('[data-modal-close]')?.addEventListener('click', (event) => {
        event.preventDefault();
        modal.close();
    });

    body.querySelector('[data-modal-confirm]')?.addEventListener('click', async () => {
        await postDraftAction('delete', draftId);
        modal.close();
    });
};

export default (root: HTMLElement) => {
    const modalRoot = root.querySelector<HTMLElement>('#konfigurator-manager-modal');
    if (!modalRoot) {
        return;
    }

    const modal = createModal(modalRoot);

    root.addEventListener('click', (event) => {
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>(
            '[data-manager-action]'
        );

        if (!button) {
            return;
        }

        const action = button.dataset.managerAction as ManagerAction | undefined;
        const draftId = button.dataset.draftId ?? '';
        const draftTitle = button.dataset.draftTitle ?? '';

        if (!action || !draftId) {
            return;
        }

        event.preventDefault();

        if (action === 'rename') {
            openRenameModal(modal, draftId, draftTitle);
            return;
        }

        openDeleteModal(modal, draftId, draftTitle);
    });
};
