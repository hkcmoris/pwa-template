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
        const viewportBottomSpacing = 64;
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
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal(modal);
        }
    });

    root.setAttribute('data-option-cards-mounted', '');
};
