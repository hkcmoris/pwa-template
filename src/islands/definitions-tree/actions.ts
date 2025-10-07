import type { DefinitionsApiClient, ModalManager, OpenCreateOptions } from './types';
import { escapeHtml } from './utils';
import { listSelectors } from './constants';

export const setupNodeActions = (
    root: HTMLElement,
    api: DefinitionsApiClient,
    modal: ModalManager,
    openCreateModal: (options?: OpenCreateOptions) => void
) => {
    root.addEventListener('click', (event) => {
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>(
            '.definition-action'
        );

        if (!button) return;
        const action = button.dataset.action;

        if (!action) return;

        if (action === 'create-child') {
            const parentId = button.dataset.parentId ?? '';
            const parentTitle = button.dataset.parentTitle ?? '';
            const childCountRaw = button.dataset.parentChildren;
            const childCount =
                childCountRaw !== undefined
                    ? Number.parseInt(childCountRaw, 10)
                    : undefined;

            openCreateModal({
                parentId,
                parentTitle,
                childCount: Number.isNaN(childCount) ? undefined : childCount,
            });

            return;
        }

        const id = button.dataset.id;

        if (!id) return;

        if (action === 'rename') {
            const currentTitle = button.dataset.title || '';
            const form = document.createElement('form');
            form.className = 'definition-form definition-form--modal';
            form.innerHTML = `
                <div class="definition-field">
                  <label>Nový název
                    <input type="text" name="title" value="${escapeHtml(
                        currentTitle
                    )}" maxlength="191" required>
                  </label>
                </div>
                <div class="definition-modal-actions">
                  <button type="button" class="definition-action" data-modal-close>Storno</button>
                  <button type="submit" class="definition-primary">Uložit</button>
                </div>
            `;

            modal.open('Přejmenovat definici', form);

            const input = form.querySelector<HTMLInputElement>(
                'input[name="title"]'
            );

            input?.focus();
            input?.select();

            form.addEventListener('submit', (ev) => {
                ev.preventDefault();

                const value = input?.value.trim() ?? '';

                if (!value || value === currentTitle) {
                    modal.close();
                    return;
                }

                api.rename(id, value);

                modal.close();
            });

            form.querySelector('[data-modal-close]')?.addEventListener(
                'click',
                (ev) => {
                    ev.preventDefault();
                    modal.close();
                }
            );

            return;
        }

        if (action === 'delete') {
            const title = button.dataset.title || '';
            const container = document.createElement('div');
            container.className = 'definition-modal-body';
            container.innerHTML = `
                <p>Opravdu chcete smazat definici <strong>${escapeHtml(
                    title
                )}</strong>? Tato akce odstraní také všechny její podřízené uzly.</p>
                <div class="definition-modal-actions">
                  <button type="button" class="definition-action" data-modal-close>Storno</button>
                  <button type="button" class="definition-action definition-action--danger" data-modal-confirm>Smazat</button>
                </div>
            `;

            modal.open('Smazat definici', container);

            container
                .querySelector('[data-modal-close]')
                ?.addEventListener('click', (ev) => {
                    ev.preventDefault();

                    modal.close();
                });

            container
                .querySelector('[data-modal-confirm]')
                ?.addEventListener('click', () => {
                    api.delete(id);

                    modal.close();
                });

            return;
        }

        if (action === 'toggle-children') {
            const item = button.closest<HTMLElement>(listSelectors.item);
            item?.classList.toggle('definition-item--collapsed');
        }
    });
};
