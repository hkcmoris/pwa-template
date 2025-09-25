import { enhanceSelects, setSelectValue } from './select';

const listSelectors = {
    node: '.definition-node',
    item: '.definition-item',
    actions: '.definition-actions',
};

type DropPosition = 'before' | 'after' | 'inside';

type HTMX = {
    ajax: (
        method: string,
        url: string,
        options: {
            values?: Record<string, unknown>;
            target?: Element;
            swap?: string;
            select?: string;
        }
    ) => XMLHttpRequest;
};

type DragContext = {
    item: HTMLElement;
    id: string;
    path: string;
};

type DropContext = {
    item: HTMLElement | null;
    position: DropPosition | null;
};

const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const getBasePath = (root: HTMLElement): string => {
    const raw = root.dataset.base || '';
    if (!raw) return '';
    return raw === '/' ? '' : raw;
};

const getHtmx = (): HTMX | null => {
    const win = window as unknown as { htmx?: HTMX };
    return win.htmx ?? null;
};

const sendRequest = (
    htmx: HTMX,
    url: string,
    values: Record<string, unknown>,
    target: HTMLElement
): XMLHttpRequest =>
    htmx.ajax('POST', url, {
        values,
        target,
        swap: 'outerHTML',
        select: '#definitions-list',
    });

const isDescendantPath = (ancestorPath: string, childPath: string): boolean => {
    if (!ancestorPath) return false;

    return (
        childPath === ancestorPath || childPath.startsWith(`${ancestorPath}/`)
    );
};

const clearDropClasses = (root: HTMLElement) => {
    root.querySelectorAll(
        '.definition-item--drop-before, .definition-item--drop-after, .definition-item--drop-inside'
    )

        .forEach((el) =>
            el.classList.remove(
                'definition-item--drop-before',
                'definition-item--drop-after',
                'definition-item--drop-inside'
            )
        );
};

type OpenCreateOptions = {
    parentId?: string | null;
    parentTitle?: string;
    childCount?: number;
};

const setupNodeActions = (
    root: HTMLElement,
    base: string,
    htmx: HTMX,
    target: HTMLElement,
    openModal: (title: string, body: HTMLElement) => void,
    closeModal: () => void,
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
                    <input type="text" name="title" value="${escapeHtml(currentTitle)}" maxlength="191" required>
                  </label>
                </div>
                <div class="definition-modal-actions">
                  <button type="button" class="definition-action" data-modal-close>Storno</button>
                  <button type="submit" class="definition-primary">Uložit</button>
                </div>
            `;

            openModal('Přejmenovat definici', form);

            const input = form.querySelector<HTMLInputElement>(
                'input[name="title"]'
            );

            input?.focus();

            input?.select();

            form.addEventListener('submit', (ev) => {
                ev.preventDefault();

                const value = input?.value.trim() ?? '';

                if (!value || value === currentTitle) {
                    closeModal();
                    return;
                }

                sendRequest(
                    htmx,
                    `${base}/editor/definitions-rename`,
                    { id, title: value },
                    target
                );

                closeModal();
            });

            form.querySelector('[data-modal-close]')?.addEventListener(
                'click',
                (ev) => {
                    ev.preventDefault();
                    closeModal();
                }
            );

            return;
        }

        if (action === 'delete') {
            const title = button.dataset.title || '';
            const container = document.createElement('div');
            container.className = 'definition-modal-body';
            container.innerHTML = `
                <p>Opravdu chcete smazat definici <strong>${escapeHtml(title)}</strong>? Tato akce odstraní také všechny její podřízené uzly.</p>
                <div class="definition-modal-actions">
                  <button type="button" class="definition-action" data-modal-close>Storno</button>
                  <button type="button" class="definition-action definition-action--danger" data-modal-confirm>Smazat</button>
                </div>
            `;

            openModal('Smazat definici', container);

            container
                .querySelector('[data-modal-close]')
                ?.addEventListener('click', (ev) => {
                    ev.preventDefault();

                    closeModal();
                });

            container
                .querySelector('[data-modal-confirm]')
                ?.addEventListener('click', () => {
                    sendRequest(
                        htmx,
                        `${base}/editor/definitions-delete`,
                        { id },
                        target
                    );

                    closeModal();
                });

            return;
        }
    });
};

const setupDragAndDrop = (
    root: HTMLElement,
    base: string,
    htmx: HTMX,
    target: HTMLElement
) => {
    let dragContext: DragContext | null = null;
    let dropContext: DropContext = { item: null, position: null };

    const updateDropHighlight = (
        item: HTMLElement | null,
        position: DropPosition | null
    ) => {
        clearDropClasses(root);

        dropContext = { item, position };

        if (!item || !position) {
            return;
        }

        item.classList.add(
            position === 'before'
                ? 'definition-item--drop-before'
                : position === 'after'
                  ? 'definition-item--drop-after'
                  : 'definition-item--drop-inside'
        );
    };

    root.addEventListener('dragstart', (event) => {
        const targetNode = (event.target as HTMLElement).closest<HTMLElement>(
            listSelectors.node
        );

        if (!targetNode) return;

        if ((event.target as HTMLElement).closest(listSelectors.actions)) {
            event.preventDefault();
            return;
        }

        const item = targetNode.closest<HTMLElement>(listSelectors.item);

        if (!item) return;

        dragContext = {
            item,
            id: item.dataset.id || '',
            path: item.dataset.path || '',
        };

        targetNode.classList.add('definition-node--dragging');
        item.classList.add('definition-item--dragging');
        event.dataTransfer?.setData('text/plain', dragContext.id);
        event.dataTransfer?.setDragImage(targetNode, 10, 10);
    });

    root.addEventListener('dragend', () => {
        if (dragContext) {
            dragContext.item.classList.remove('definition-item--dragging');
            dragContext.item
                .querySelector(listSelectors.node)
                ?.classList.remove('definition-node--dragging');
        }

        dragContext = null;
        dropContext = { item: null, position: null };
        clearDropClasses(root);
    });

    root.addEventListener('dragover', (event) => {
        if (!dragContext) return;

        const item = (event.target as HTMLElement).closest<HTMLElement>(
            listSelectors.item
        );

        if (!item || item === dragContext.item) return;

        const path = item.dataset.path || '';

        if (isDescendantPath(dragContext.path, path)) return;

        event.preventDefault();

        const rect = item.getBoundingClientRect();
        const offset = event.clientY - rect.top;
        const threshold = rect.height / 3;
        let position: DropPosition;

        if (offset < threshold) {
            position = 'before';
        } else if (offset > rect.height - threshold) {
            position = 'after';
        } else {
            position = 'inside';
        }

        updateDropHighlight(item, position);
    });

    root.addEventListener('dragleave', (event) => {
        const related = event.relatedTarget as HTMLElement | null;

        if (related && root.contains(related)) {
            return;
        }

        if (!dragContext) return;

        updateDropHighlight(null, null);
    });

    root.addEventListener('drop', (event) => {
        if (!dragContext || !dropContext.item || !dropContext.position) return;

        event.preventDefault();

        const targetItem = dropContext.item;
        const dropPosition = dropContext.position;
        const targetIdRaw = (targetItem.dataset.id ?? '').trim();

        if (!/^\d+$/.test(targetIdRaw)) {
            updateDropHighlight(null, null);
            return;
        }

        const parentAttr = targetItem.dataset.parent ?? '';
        const rawPosition = Number.parseInt(
            targetItem.dataset.position ?? '',
            10
        );
        const siblingCollection =
            targetItem.parentElement?.querySelectorAll(
                ':scope > .definition-item'
            );
        const siblings = siblingCollection
            ? Array.from(siblingCollection)
            : [];
        let targetPosition = Number.isNaN(rawPosition)
            ? siblings.indexOf(targetItem)
            : rawPosition;

        if (targetPosition < 0) {
            targetPosition = siblings.length ? siblings.length - 1 : 0;
        }

        const dragId = dragContext.id.trim();

        if (!/^\d+$/.test(dragId)) {
            updateDropHighlight(null, null);
            return;
        }

        let parentId: string | null =
            parentAttr === '' ? null : parentAttr.trim();
        let position = 0;

        if (dropPosition === 'inside') {
            parentId = targetIdRaw;
            const childItems = targetItem.querySelectorAll(
                ':scope > ul > .definition-item'
            );
            position = childItems.length;
        } else {
            position =
                dropPosition === 'before'
                    ? targetPosition
                    : targetPosition + 1;
        }

        if (parentId === dragId) {
            updateDropHighlight(null, null);
            return;
        }

        if (parentId && !/^\d+$/.test(parentId)) {
            parentId = null;
        }

        if (!Number.isFinite(position) || position < 0) {
            position = 0;
        }

        const currentParentRaw = dragContext.item.dataset.parent ?? '';
        const currentParentId = currentParentRaw === '' ? null : currentParentRaw.trim();
        const currentPosition = Number.parseInt(
            dragContext.item.dataset.position ?? '',
            10
        );
        const targetParentId = parentId ?? null;
        const desiredPosition = Math.floor(position);

        if (
            targetParentId === currentParentId &&
            !Number.isNaN(currentPosition) &&
            desiredPosition === currentPosition
        ) {
            updateDropHighlight(null, null);
            return;
        }

        sendRequest(
            htmx,
            `${base}/editor/definitions-move`,
            {
                id: dragId,
                parent_id: targetParentId ?? '',
                position: String(desiredPosition),
            },
            target
        );

        updateDropHighlight(null, null);
    });

    root.addEventListener('dragover', (event) => {
        if (!dragContext) return;

        const target = event.target as HTMLElement;

        if (!root.contains(target) || target.closest(listSelectors.item))
            return;

        event.preventDefault();

        dropContext = { item: null, position: null };
    });

    root.addEventListener('drop', (event) => {
        if (!dragContext) return;

        const targetEl = event.target as HTMLElement;

        if (targetEl.closest(listSelectors.item)) return;

        event.preventDefault();

        const rootItems = Array.from(
            root.querySelectorAll<HTMLElement>(
                `${listSelectors.item}:not(${listSelectors.item} ${listSelectors.item})`
            )
        );

        const position = rootItems.length;

        sendRequest(
            htmx,
            `${base}/editor/definitions-move`,
            {
                id: dragContext.id,
                parent_id: '',
                position,
            },
            target
        );

        updateDropHighlight(null, null);
    });
};

export default function init(el: HTMLElement) {
    const htmx = getHtmx();

    if (!htmx) return;

    const base = getBasePath(el);
    const target = el;
    const actualBase = base || '';
    const modalRoot = document.getElementById(
        'definitions-modal'
    ) as HTMLElement | null;

    const createTemplate = document.getElementById(
        'definition-create-template'
    ) as HTMLTemplateElement | null;

    const getParentSelectCache = () =>
        document.getElementById(
            'definition-parent-select'
        ) as HTMLElement | null;

    const openCreateButton = document.getElementById(
        'definition-open-create'
    ) as HTMLButtonElement | null;

    let escHandler: ((ev: KeyboardEvent) => void) | null = null;

    const closeModal = () => {
        if (!modalRoot) return;

        modalRoot.classList.add('hidden');
        modalRoot.setAttribute('aria-hidden', 'true');
        modalRoot.innerHTML = '';

        if (escHandler) {
            document.removeEventListener('keydown', escHandler);
            escHandler = null;
        }
    };

    const openModal = (title: string, body: HTMLElement) => {
        if (!modalRoot) return;

        modalRoot.innerHTML = `
            <div class="definitions-modal__overlay" data-modal-close></div>
            <div class="definitions-modal__panel" role="dialog" aria-modal="true">
              <header>
                <h3>${escapeHtml(title)}</h3>
                <button type="button" class="definition-action" data-modal-close aria-label="Zavřít">×</button>
              </header>
              <div class="definitions-modal__body"></div>
            </div>
        `;

        const bodyContainer = modalRoot.querySelector(
            '.definitions-modal__body'
        ) as HTMLElement;

        bodyContainer.appendChild(body);
        modalRoot.classList.remove('hidden');
        modalRoot.setAttribute('aria-hidden', 'false');
        modalRoot.querySelectorAll('[data-modal-close]').forEach((btn) =>
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                closeModal();
            })
        );

        escHandler = (ev) => {
            if (ev.key === 'Escape') {
                ev.preventDefault();
                closeModal();
            }
        };

        document.addEventListener('keydown', escHandler);
    };

    const openCreateModal = (options: OpenCreateOptions = {}) => {
        const parentSelectCache = getParentSelectCache();

        if (!modalRoot || !createTemplate || !parentSelectCache) return;

        const fragment = createTemplate.content.cloneNode(
            true
        ) as DocumentFragment;

        const form = fragment.querySelector('form');

        if (!form) return;

        const { parentId: rawParentId, parentTitle, childCount } = options;

        const desiredParentId =
            rawParentId === undefined || rawParentId === null
                ? ''
                : rawParentId;

        const normalizedParentTitle =
            typeof parentTitle === 'string' ? parentTitle.trim() : '';

        const slot = form.querySelector('[data-definition-select-slot]');

        let hiddenInput: HTMLInputElement | null = null;

        if (slot) {
            const clone = parentSelectCache.cloneNode(true) as HTMLElement;
            clone.id = 'definition-parent-select-modal';
            clone.removeAttribute('hx-swap-oob');
            clone.removeAttribute('aria-hidden');
            clone.removeAttribute('style');
            clone.removeAttribute('data-island');
            clone.removeAttribute('hx-on');

            hiddenInput = clone.querySelector<HTMLInputElement>(
                'input[name="parent_id"]'
            );

            if (hiddenInput) {
                hiddenInput.id = 'definition-parent-value-modal';
                hiddenInput.value = desiredParentId;
            }

            const button = clone.querySelector<HTMLButtonElement>(
                '#definition-parent-button'
            );

            if (button) {
                button.id = 'definition-parent-button-modal';
                button.setAttribute(
                    'aria-labelledby',
                    'definition-parent-label definition-parent-button-modal'
                );
            }

            slot.replaceWith(clone);
            enhanceSelects(clone);
            const selectElement = clone.querySelector<HTMLElement>('.select');

            if (selectElement) {
                setSelectValue(selectElement, desiredParentId);
            }

            clone.addEventListener('select:change', (ev) => {
                const detail = (ev as CustomEvent).detail as
                    | { value?: string }
                    | undefined;

                if (hiddenInput) hiddenInput.value = detail?.value ?? '';
            });
        }

        const legend = form.querySelector('legend');

        if (legend) {
            legend.textContent = normalizedParentTitle
                ? `Přidat poduzel k ${normalizedParentTitle}`
                : 'Přidat novou definici';
        }

        const positionInput = form.querySelector<HTMLInputElement>(
            'input[name="position"]'
        );

        if (positionInput) {
            if (typeof childCount === 'number' && Number.isFinite(childCount)) {
                positionInput.value = String(childCount);
            } else {
                positionInput.value = '';
            }
        }

        openModal(
            normalizedParentTitle ? 'Přidat poduzel' : 'Přidat definici',
            form
        );

        const titleInput = form.querySelector<HTMLInputElement>(
            'input[name="title"]'
        );

        titleInput?.focus();

        form.addEventListener('htmx:afterRequest', (event) => {
            const detail = (event as CustomEvent).detail as
                | { xhr?: XMLHttpRequest }
                | undefined;

            const status = detail?.xhr?.status ?? 0;

            if (status && status < 400) {
                closeModal();
            }
        });
    };

    setupNodeActions(
        el,
        actualBase,
        htmx,
        target,
        openModal,
        closeModal,
        openCreateModal
    );

    setupDragAndDrop(el, actualBase, htmx, target);

    if (openCreateButton) {
        openCreateButton.addEventListener('click', () => {
            openCreateModal();
        });
    }
}
