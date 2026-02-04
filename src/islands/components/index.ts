import { createComponentApiClient } from './api-client';
import { COMPONENT_FORM_ERROR_ID } from './form';
import { createComponentModalManager } from './modal';
import { getBasePath, getHtmx } from './utils';
import { parseNumber } from '../shared/parser';
import { setupNodeActions } from './actions';
import { createOpenComponentModal } from './open-component-modal';
import { setupDragAndDrop } from './drag-drop';

const listTarget = '#components-list';
const listWrapperTarget = '#components-list-wrapper';

type PageResponse = {
    html: string;
    nextOffset: number;
    hasMore: boolean;
};

const bindCreateButton = (
    button: HTMLButtonElement | null,
    openCreateModal: () => void
) => {
    button?.addEventListener('click', () => {
        openCreateModal();
    });
};

const setupInfiniteScroll = (root: HTMLElement, basePath: string) => {
    const sentinel = root.querySelector<HTMLElement>(
        '[data-component-sentinel]'
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

    const rootMargin = 200;
    let scrollRaf = 0;
    let observer: IntersectionObserver | null = null;

    const cleanup = () => {
        observer?.disconnect();
        window.removeEventListener('scroll', handleScroll);
        window.removeEventListener('resize', handleScroll);
        if (scrollRaf) {
            window.cancelAnimationFrame(scrollRaf);
            scrollRaf = 0;
        }
    };

    const isSentinelInView = () => {
        const rect = sentinelElement.getBoundingClientRect();
        return rect.top <= window.innerHeight + rootMargin;
    };

    const observerCallback = (entries) => {
        if (!hasMore()) {
            cleanup();
            return;
        }

        if (entries.some((entry) => entry.isIntersecting)) {
            void fetchNext();
        }
    };

    observer = new IntersectionObserver(observerCallback, {
        rootMargin: `${rootMargin}px`,
    });

    const handleScroll = () => {
        if (scrollRaf) {
            return;
        }
        scrollRaf = window.requestAnimationFrame(() => {
            scrollRaf = 0;
            if (hasMore() && isSentinelInView()) {
                void fetchNext();
            }
        });
    };

    observer.observe(sentinelElement);
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll);
    handleScroll();

    async function fetchNext() {
        if (loading || !hasMore()) {
            return;
        }

        loading = true;
        sentinelElement.dataset.loading = '1';

        try {
            const url = `${basePath}/editor/components/page?offset=${nextOffset}`;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'HX-Request': 'true',
                },
            });

            if (!response.ok) {
                cleanup();
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
                cleanup();
                sentinelElement.remove();
            }
            if (hasMore() && isSentinelInView()) {
                void fetchNext();
            }
        } catch (error) {
            console.error('Failed to fetch component page', error);
            cleanup();
        } finally {
            loading = false;
            delete sentinelElement.dataset.loading;
        }
    }
};

export default function init(root: HTMLElement) {
    const htmx = getHtmx();

    if (!htmx) return;

    const base = getBasePath(root);
    const actualBase = base || '';
    const modalRoot = document.getElementById(
        'components-modal'
    ) as HTMLElement | null;

    const createTemplate = document.getElementById(
        'component-create-template'
    ) as HTMLTemplateElement | null;

    if (!modalRoot || !createTemplate) {
        return;
    }

    const errorBox = document.getElementById(
        COMPONENT_FORM_ERROR_ID
    ) as HTMLElement | null;

    const modal = createComponentModalManager(modalRoot, htmx, errorBox);
    const api = createComponentApiClient({
        listTarget: listWrapperTarget,
        htmx,
        base: actualBase,
    });

    const openComponentModal = createOpenComponentModal({
        modal,
        createTemplate,
        base: actualBase,
    });

    const openCreateButton = root.querySelector<HTMLButtonElement>(
        '#component-open-create'
    ) as HTMLButtonElement | null;

    setupInfiniteScroll(root, base);
    setupNodeActions(root, api, modal, openComponentModal);
    setupDragAndDrop(root, api);

    bindCreateButton(openCreateButton, openComponentModal);

    root.addEventListener('htmx:afterSwap', (event) => {
        const detail = (event as CustomEvent<{ target: HTMLElement | null }>)
            .detail;
        const target = detail?.target;

        if (
            target &&
            (target.matches(listTarget) || target.matches(listWrapperTarget))
        ) {
            setupInfiniteScroll(root, base);
        }
    });
}
