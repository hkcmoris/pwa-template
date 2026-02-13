import { getCsrfToken } from '../utils/api';

const escapeHtml = (value: string) =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const getCurrentImage = (card: HTMLElement) => {
    const images = Array.from(
        card.querySelectorAll<HTMLImageElement>('[data-option-image]')
    );
    const currentIndex = images.findIndex((image) => !image.hidden);
    return {
        images,
        currentIndex: currentIndex >= 0 ? currentIndex : 0,
    };
};

const showImage = (card: HTMLElement, index: number) => {
    const { images } = getCurrentImage(card);
    if (!images.length) {
        return;
    }

    const nextIndex = ((index % images.length) + images.length) % images.length;
    images.forEach((image, imageIndex) => {
        const isActive = imageIndex === nextIndex;
        image.hidden = !isActive;
        image.classList.toggle('is-active', isActive);
    });
};

const closeModal = (modal: HTMLElement) => {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '';
};

const closeFinishModal = (modal: HTMLElement) => {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '';
};

const openFinishModal = (modal: HTMLElement, message: string, success: boolean) => {
    modal.innerHTML = `
        <div class="component-options-finish-modal-overlay" data-finish-modal-close></div>
        <div class="component-options-finish-modal-panel" role="dialog" aria-modal="true" aria-label="Výsledek dokončení konfigurace">
            <button type="button" class="component-options-finish-modal-close" data-finish-modal-close aria-label="Zavřít">×</button>
            <h3 class="component-options-finish-modal-title ${success ? 'is-success' : 'is-error'}">${
                success ? 'Hotovo' : 'Chyba'
            }</h3>
            <p>${escapeHtml(message)}</p>
        </div>
    `;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    modal.querySelectorAll<HTMLElement>('[data-finish-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeFinishModal(modal));
    });
};

const finishConfiguration = async (
    root: HTMLElement,
    finishButton: HTMLButtonElement,
    draftId: string
) => {
    const params = new URLSearchParams();
    params.set('draft_id', draftId);

    const headers = new Headers({
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'HX-Request': 'true',
    });
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
    }

    const base = document.documentElement.dataset.base ?? '';
    const modal =
        root.querySelector<HTMLElement>('.component-options-finish-modal') ??
        root.appendChild(document.createElement('div'));
    if (!modal.classList.contains('component-options-finish-modal')) {
        modal.className = 'component-options-finish-modal hidden';
        modal.setAttribute('aria-hidden', 'true');
    }

    finishButton.disabled = true;

    try {
        const response = await fetch(`${base}/configurator/wizard/finish`, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: params.toString(),
        });
        const payload = (await response.json().catch(() => null)) as {
            success?: boolean;
            message?: string;
        } | null;
        const success = Boolean(response.ok && payload?.success);
        const message =
            payload?.message ??
            (success
                ? 'Konfigurace byla dokončena.'
                : 'Dokončení konfigurace se nezdařilo.');

        openFinishModal(modal, message, success);

        if (success) {
            finishButton.textContent = 'Konfigurace dokončena';
            return;
        }

        finishButton.disabled = false;
    } catch {
        openFinishModal(
            modal,
            'Dokončení konfigurace se nezdařilo.',
            false
        );
        finishButton.disabled = false;
    }
};

const openModal = (modal: HTMLElement, src: string, alt: string) => {
    modal.innerHTML = `
        <div class="options-card-modal-overlay" data-option-modal-close></div>
        <div class="options-card-modal-panel" role="dialog" aria-modal="true" aria-label="Náhled obrázku">
            <button type="button" class="options-card-modal-close" data-option-modal-close aria-label="Zavřít">×</button>
            <img class="options-card-modal-image" loading="eager" decoding="async">
        </div>
    `;

    const image = modal.querySelector<HTMLImageElement>(
        '.options-card-modal-image'
    );
    if (image) {
        image.src = src;
        image.alt = alt;
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    modal
        .querySelectorAll<HTMLElement>('[data-option-modal-close]')
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
};

const ensureModal = (root: HTMLElement) => {
    let modal = root.querySelector<HTMLElement>('.options-card-modal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.className = 'options-card-modal hidden';
    modal.setAttribute('aria-hidden', 'true');
    root.appendChild(modal);
    return modal;
};

const initializeCardImages = (root: HTMLElement) => {
    root.querySelectorAll<HTMLElement>('.options-card-media').forEach(
        (card) => {
            const { images, currentIndex } = getCurrentImage(card);
            if (!images.length) {
                return;
            }

            showImage(card, currentIndex);
        }
    );
};

const recalculateCardMediaHeights = (root: HTMLElement) => {
    root.querySelectorAll<HTMLElement>('.options-card-inner').forEach((card) => {
        const media = card.querySelector<HTMLElement>('.options-card-media');
        if (!media) {
            return;
        }

        card.style.removeProperty('--options-card-media-max-height');

        const mediaHeight = media.offsetHeight;
        if (!mediaHeight) {
            return;
        }

        const cardRect = card.getBoundingClientRect();
        const viewportBottomSpacing = 16;
        const availableHeight = Math.floor(
            window.innerHeight - cardRect.top - viewportBottomSpacing
        );
        if (availableHeight <= 0) {
            return;
        }

        const nonMediaHeight = Math.max(0, card.scrollHeight - mediaHeight);
        const mediaMaxHeight = Math.max(160, availableHeight - nonMediaHeight);
        card.style.setProperty(
            '--options-card-media-max-height',
            `${mediaMaxHeight}px`
        );
    });
};

const setupCardMediaHeightRecalculation = (root: HTMLElement) => {
    let rafId = 0;
    const scheduleRecalculation = () => {
        if (rafId) {
            return;
        }
        rafId = window.requestAnimationFrame(() => {
            rafId = 0;
            recalculateCardMediaHeights(root);
        });
    };

    root.querySelectorAll<HTMLImageElement>('[data-option-image]').forEach((image) => {
        image.addEventListener('load', scheduleRecalculation);
    });

    window.addEventListener('resize', scheduleRecalculation, { passive: true });
    scheduleRecalculation();
};

export default (root: HTMLElement) => {
    if (root.hasAttribute('data-option-cards-mounted')) {
        return;
    }

    const modal = ensureModal(root);
    initializeCardImages(root);
    setupCardMediaHeightRecalculation(root);

    root.addEventListener('click', (event) => {
        const target = event.target as HTMLElement;

        const navButton = target.closest<HTMLElement>(
            '[data-option-image-nav]'
        );
        if (navButton) {
            const card = navButton.closest<HTMLElement>('.options-card-media');
            const direction = navButton.dataset.optionImageNav;
            if (!card || !direction) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const { currentIndex } = getCurrentImage(card);
            const delta = direction === 'next' ? 1 : -1;
            showImage(card, currentIndex + delta);
            return;
        }

        const openButton = target.closest<HTMLElement>(
            '[data-option-image-open]'
        );
        if (openButton) {
            const card = openButton.closest<HTMLElement>('.options-card-media');
            if (!card) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const { images, currentIndex } = getCurrentImage(card);
            const active = images[currentIndex];
            if (!active?.src) {
                return;
            }

            openModal(modal, active.src, active.alt || 'Náhled obrázku');
            return;
        }

        const finishButton = target.closest<HTMLButtonElement>('[data-wizard-finish]');
        if (finishButton) {
            const draftId = finishButton.dataset.draftId ?? '';
            if (!draftId) {
                return;
            }
            event.preventDefault();
            void finishConfiguration(root, finishButton, draftId);
        }
    });

    const finishModal = root.querySelector<HTMLElement>('.component-options-finish-modal');

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal(modal);
        }

        if (
            event.key === 'Escape' &&
            finishModal &&
            !finishModal.classList.contains('hidden')
        ) {
            closeFinishModal(finishModal);
        }
    });

    root.setAttribute('data-option-cards-mounted', '');
};
