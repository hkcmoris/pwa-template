import { createDefinitionsApiClient } from './api-client';
import { setupNodeActions } from './actions';
import { createModalManager } from './modal';
import { createOpenCreateModal } from './open-create-modal';
import { setupDragAndDrop } from './drag-drop';
import { getBasePath, getHtmx } from './utils';
import type { DefinitionsApiClient } from './types';
import { parseNumber } from '../shared/parser';

export { isDescendantPath, setupDragAndDrop } from './drag-drop';

const listTarget = '#definitions-tree';
const listWrapperTarget = '#definitions-list';

type PageResponse = {
    html: string;
    nextOffset: number;
    hasMore: boolean;
};

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

const initializeDragAndDrop = (el: HTMLElement, api: DefinitionsApiClient) => {
    setupDragAndDrop(el, api);
};

const setupInfiniteScroll = (root: HTMLElement, basePath: string) => {
    const sentinel = root.querySelector<HTMLElement>(
        '[data-definition-sentinel]'
    );
    const list = root.querySelector<HTMLElement>(listTarget);

    if (!sentinel || !list) {
        return;
    }

    const sentinelElement = sentinel;
    const listElement = list;

    let nextOffset = parseNumber(sentinelElement.dataset.nextOffset);
    let loading = false;
    const total = parseNumber(sentinelElement.dataset.total);

    const hasMore = () => sentinelElement.dataset.hasMore !== '0';

    if (sentinelElement.dataset.bound === '1') {
        return;
    }

    sentinelElement.dataset.bound = '1';

    if (!hasMore()) {
        sentinelElement.remove();
        return;
    }

    const observer = new IntersectionObserver(
        (entries: IntersectionObserverEntry[]) => {
            if (!hasMore()) {
                observer.disconnect();
                return;
            }

            if (entries.some((entry) => entry.isIntersecting)) {
                void fetchNext();
            }
        },
        { rootMargin: '200px' }
    );

    observer.observe(sentinelElement);

    async function fetchNext() {
        if (loading || !hasMore()) {
            return;
        }

        loading = true;
        sentinelElement.dataset.loading = '1';

        try {
            const url = `${basePath}/editor/definitions/page?offset=${nextOffset}`;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'HX-Request': 'true',
                },
            });

            if (!response.ok) {
                observer.disconnect();
                return;
            }

            const payload = (await response.json()) as PageResponse;
            const markup = (payload.html ?? '').trim();

            if (markup) {
                const fragment = document
                    .createRange()
                    .createContextualFragment(markup);
                listElement.appendChild(fragment);
            }

            nextOffset = payload.nextOffset ?? nextOffset;
            sentinelElement.dataset.nextOffset = String(nextOffset);
            sentinelElement.dataset.hasMore = payload.hasMore ? '1' : '0';

            if (!payload.hasMore || nextOffset >= total) {
                observer.disconnect();
                sentinelElement.remove();
            }
        } catch (error) {
            console.error('Failed to fetch definition page', error);
            observer.disconnect();
        } finally {
            loading = false;
            delete sentinelElement.dataset.loading;
        }
    }
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

    setupInfiniteScroll(el, base);

    el.addEventListener('htmx:afterSwap', (event) => {
        const detail = (event as CustomEvent<{ target: HTMLElement | null }>)
            .detail;
        const target = detail?.target;

        if (
            target &&
            (target.matches(listTarget) || target.matches(listWrapperTarget))
        ) {
            setupInfiniteScroll(el, base);
        }
    });
}
