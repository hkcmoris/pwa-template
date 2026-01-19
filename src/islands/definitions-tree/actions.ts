import type {
    DefinitionsApiClient,
    ModalManager,
    OpenCreateOptions,
} from './types';
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
            listSelectors.action
        );

        if (!button) return;
        const action = button.dataset.action;

        if (!action) return;

        const item = button.closest<HTMLElement>(listSelectors.item);
        if (!item) return;

        const id = item.dataset.id;
        if (!id) return;

        const title = item.dataset.title ?? '';

        if (action === 'create-child') {
            const parentId = id;
            const parentTitle = title;
            const childCount = item.querySelectorAll(
                ':scope > ul > .definition-item'
            ).length;

            openCreateModal({
                parentId,
                parentTitle,
                childCount,
            });

            return;
        }

        if (action === 'configure-range') {
            const hasRange = item.dataset.hasRange === 'true';
            const currentMin = item.dataset.valueMin ?? '';
            const currentMax = item.dataset.valueMax ?? '';
            const form = document.createElement('form');
            form.className = 'definition-form definition-form--modal';
            const safeTitle = escapeHtml(title);
            const rangeTitle = hasRange
                ? `Upravit rozsah hodnot` + (title ? ` — ${safeTitle}` : '')
                : `Nastavit rozsah hodnot` + (title ? ` — ${safeTitle}` : '');
            form.innerHTML = `
                <fieldset>
                  <legend>${rangeTitle}</legend>
                  <p class="definition-help">Zadejte minimální a/nebo maximální hodnotu. Prázdné pole ponechá hranici neomezenou.</p>
                  <div class="definition-field">
                    <label for="definition-range-min">Minimální hodnota</label>
                    <input type="number" id="definition-range-min" name="value_min" step="1" inputmode="numeric" value="${escapeHtml(
                        currentMin
                    )}" placeholder="neomezeno">
                  </div>
                  <div class="definition-field">
                    <label for="definition-range-max">Maximální hodnota</label>
                    <input type="number" id="definition-range-max" name="value_max" step="1" inputmode="numeric" value="${escapeHtml(
                        currentMax
                    )}" placeholder="neomezeno">
                  </div>
                </fieldset>
                <div class="definition-modal-actions">
                  <button type="button" class="definition-action" data-modal-close>Storno</button>
                  ${
                      hasRange
                          ? '<button type="button" class="definition-action definition-action--danger" data-range-clear>Odstranit rozsah</button>'
                          : ''
                  }
                  <button type="submit" class="definition-primary">Uložit</button>
                </div>
            `;

            const modalTitleBase = hasRange
                ? 'Upravit rozsah'
                : 'Nastavit rozsah';
            const modalTitle = title
                ? `${modalTitleBase} – ${title}`
                : modalTitleBase;

            modal.open(modalTitle, form);

            const minInput = form.querySelector<HTMLInputElement>(
                'input[name="value_min"]'
            );
            const maxInput = form.querySelector<HTMLInputElement>(
                'input[name="value_max"]'
            );

            minInput?.focus();

            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                const minValue = minInput?.value.trim() ?? '';
                const maxValue = maxInput?.value.trim() ?? '';

                api.updateRange(id, {
                    mode: 'set',
                    min: minValue === '' ? null : minValue,
                    max: maxValue === '' ? null : maxValue,
                });

                modal.close();
            });

            form.querySelector('[data-modal-close]')?.addEventListener(
                'click',
                (ev) => {
                    ev.preventDefault();
                    modal.close();
                }
            );

            form.querySelector('[data-range-clear]')?.addEventListener(
                'click',
                (ev) => {
                    ev.preventDefault();
                    api.updateRange(id, { mode: 'clear' });
                    modal.close();
                }
            );

            return;
        }

        if (action === 'rename') {
            const form = document.createElement('form');
            form.className = 'definition-form definition-form--modal';
            form.innerHTML = `
                <div class="definition-field">
                  <label>Nový název
                    <input type="text" name="title" value="${escapeHtml(
                        title
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

                if (!value || value === title) {
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
            item?.classList.toggle('definition-item--collapsed');
        }
    });
};
