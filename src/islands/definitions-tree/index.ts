import { createDefinitionsApiClient } from './api-client';
import { setupNodeActions } from './actions';
import { createModalManager } from './modal';
import { createOpenCreateModal } from './open-create-modal';
import { setupDragAndDrop } from './drag-drop';
import { getBasePath, getHtmx } from './utils';
import type { DefinitionsApiClient } from './types';

export { isDescendantPath, setupDragAndDrop } from './drag-drop';

const getParentSelectCache = () =>
    document.getElementById('definition-parent-select') as HTMLElement | null;

const bindCreateButton = (
    button: HTMLButtonElement | null,
    openCreateModal: () => void
) => {
    button?.addEventListener('click', () => {
        openCreateModal();
    });
};

const initializeDragAndDrop = (
    el: HTMLElement,
    api: DefinitionsApiClient
) => {
    setupDragAndDrop(el, api);
};

export default function init(el: HTMLElement) {
    const htmx = getHtmx();

    if (!htmx) return;

    const base = getBasePath(el);
    const actualBase = base || '';
    const modalRoot = document.getElementById(
        'definitions-modal'
    ) as HTMLElement | null;

    const createTemplate = document.getElementById(
        'definition-create-template'
    ) as HTMLTemplateElement | null;

    if (!modalRoot || !createTemplate) {
        return;
    }

    const modal = createModalManager(modalRoot, htmx);
    const api = createDefinitionsApiClient(htmx, actualBase);

    const openCreateModal = createOpenCreateModal({
        modal,
        createTemplate,
        getParentSelectCache,
    });

    const openCreateButton = document.getElementById(
        'definition-open-create'
    ) as HTMLButtonElement | null;

    setupNodeActions(el, api, modal, openCreateModal);
    initializeDragAndDrop(el, api);

    bindCreateButton(openCreateButton, openCreateModal);
}
