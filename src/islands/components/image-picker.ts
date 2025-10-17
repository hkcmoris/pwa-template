import type { HTMX } from './types';
import { getHtmx } from './utils';

type ImagePickerSelection = {
    value: string;
    rel: string;
    name: string;
};

type ImagePickerOptions = {
    basePath: string;
    currentValue?: string;
};

const normalizeBasePath = (basePath: string): string => {
    if (!basePath) {
        return '';
    }
    if (basePath === '/') {
        return '';
    }
    const trimmed = basePath.replace(/\/+$/u, '');
    if (!trimmed) {
        return '';
    }
    return trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
};

const joinWithBase = (basePath: string, path: string): string => {
    const normalisedBase = normalizeBasePath(basePath);
    const normalisedPath = path.startsWith('/') ? path : `/${path}`;
    if (!normalisedBase) {
        return normalisedPath;
    }
    return `${normalisedBase}${normalisedPath}`;
};

const normaliseCandidate = (value: string): string =>
    value
        .trim()
        .replace(/\\/gu, '/')
        .replace(/(?<!:)\/{2,}/gu, '/')
        .replace(/\/+$/u, '');

const candidateAbsPathsFromRel = (
    rel: string,
    basePath: string
): readonly string[] => {
    const trimmedRel = rel.replace(/^\/+/, '').trim();
    if (!trimmedRel) {
        return [];
    }
    const baseVariants = new Set<string>(['', normalizeBasePath(basePath)]);
    const roots = [
        '/public/assets/images/upload',
        '/assets/images/upload',
    ];
    const candidates = new Set<string>();
    candidates.add(trimmedRel);
    candidates.add(`/${trimmedRel}`);
    baseVariants.forEach((baseVariant) => {
        roots.forEach((root) => {
            const prefix = baseVariant ? `${baseVariant}${root}` : root;
            candidates.add(
                normaliseCandidate(`${prefix}/${trimmedRel}`)
            );
        });
    });
    return Array.from(candidates);
};

let container: HTMLElement | null = null;
let confirmButton: HTMLButtonElement | null = null;
let bodyContainer: HTMLElement | null = null;
let selectionLabel: HTMLElement | null = null;
let imagesRoot: HTMLElement | null = null;
let loadPromise: Promise<void> | null = null;
let listenersAttached = false;
let escHandler: ((event: KeyboardEvent) => void) | null = null;
let resolveSelection:
    | ((value: ImagePickerSelection | null) => void)
    | null = null;
let currentBasePath = '';
let selectedValue: string = '';
let selectedRel: string | null = null;
let selectedName: string | null = null;

const ensureContainer = (): HTMLElement => {
    if (container) {
        return container;
    }
    const node = document.createElement('div');
    node.id = 'component-image-picker-modal';
    node.className = 'components-modal hidden';
    node.setAttribute('aria-hidden', 'true');
    document.body.appendChild(node);
    container = node;
    return node;
};

const ensureShell = () => {
    const root = ensureContainer();
    if (!root.dataset.shell) {
        root.innerHTML = `
            <div class="components-modal-overlay" data-image-picker-dismiss></div>
            <div class="components-modal-panel components-modal-panel--wide" role="dialog" aria-modal="true" aria-label="Vybrat obrázek">
              <header>
                <h3>Vybrat obrázek</h3>
                <button type="button" class="component-action" data-image-picker-dismiss aria-label="Zavřít">×</button>
              </header>
              <div class="components-modal-body" data-image-picker-body>
                <p>Načítám galerii…</p>
              </div>
              <p class="component-image-picker-selection" data-image-picker-selection>Vyberte obrázek z galerie.</p>
              <div class="components-modal-actions">
                <button type="button" class="component-action" data-image-picker-dismiss>Storno</button>
                <button type="button" class="component-primary" data-image-picker-confirm disabled>Použít obrázek</button>
              </div>
            </div>
        `;
        root.dataset.shell = '1';
    }
    confirmButton = root.querySelector(
        '[data-image-picker-confirm]'
    ) as HTMLButtonElement | null;
    bodyContainer = root.querySelector(
        '[data-image-picker-body]'
    ) as HTMLElement | null;
    selectionLabel = root.querySelector(
        '[data-image-picker-selection]'
    ) as HTMLElement | null;
};

const updateSelectionSummary = () => {
    if (selectionLabel) {
        if (selectedValue) {
            const name = selectedName || selectedRel || selectedValue;
            selectionLabel.textContent = `Vybráno: ${name}`;
        } else {
            selectionLabel.textContent = 'Vyberte obrázek z galerie.';
        }
    }
    if (confirmButton) {
        confirmButton.disabled = !selectedValue;
    }
};

const matchesTile = (tile: HTMLElement): boolean => {
    if (!selectedValue && !selectedRel) {
        return false;
    }
    const tileUrl = (tile.dataset.imageUrl ?? '').trim();
    const tileRel = (tile.dataset.imageRel ?? '').trim();
    if (selectedValue) {
        const target = normaliseCandidate(selectedValue);
        if (tileUrl && normaliseCandidate(tileUrl) === target) {
            return true;
        }
        if (tileRel) {
            const candidates = candidateAbsPathsFromRel(
                tileRel,
                currentBasePath
            );
            if (
                candidates.some(
                    (candidate) => normaliseCandidate(candidate) === target
                )
            ) {
                return true;
            }
        }
    }
    if (selectedRel && tileRel) {
        if (normaliseCandidate(tileRel) === normaliseCandidate(selectedRel)) {
            return true;
        }
    }
    return false;
};

const highlightSelection = (): HTMLElement | null => {
    if (!imagesRoot) {
        return null;
    }
    let matched: HTMLElement | null = null;
    imagesRoot
        .querySelectorAll<HTMLElement>('.tile.image')
        .forEach((tile) => {
            const isMatch = matchesTile(tile);
            tile.toggleAttribute('data-selected', isMatch);
            if (isMatch && !matched) {
                matched = tile;
            }
        });
    return matched;
};

const buildValueFromTile = (tile: HTMLElement): string => {
    const url = (tile.dataset.imageUrl ?? '').trim();
    if (url) {
        return url;
    }
    const rel = (tile.dataset.imageRel ?? '').trim();
    if (!rel) {
        return '';
    }
    return joinWithBase(
        currentBasePath,
        `/public/assets/images/upload/${rel.replace(/^\/+/, '')}`
    );
};

const applySelectionFromTile = (tile: HTMLElement) => {
    selectedValue = buildValueFromTile(tile);
    selectedRel = (tile.dataset.imageRel ?? '').trim() || null;
    const label = tile.querySelector('.label');
    selectedName = label?.textContent?.trim() || selectedRel || selectedValue;
    highlightSelection();
    updateSelectionSummary();
};

const resetSelection = (value: string) => {
    selectedValue = value.trim();
    selectedRel = null;
    selectedName = null;
    const matched = highlightSelection();
    if (matched) {
        selectedRel = (matched.dataset.imageRel ?? '').trim() || selectedRel;
        const label = matched.querySelector('.label');
        selectedName =
            label?.textContent?.trim() || selectedRel || selectedValue;
    }
    updateSelectionSummary();
};

const focusInitialTile = () => {
    if (!imagesRoot) {
        return;
    }
    const selected = imagesRoot.querySelector<HTMLElement>(
        '.tile.image[data-selected]'
    );
    if (selected) {
        selected.focus();
        return;
    }
    const first = imagesRoot.querySelector<HTMLElement>('.tile.image');
    first?.focus();
};

const closeModal = (result: ImagePickerSelection | null) => {
    if (!container) {
        return;
    }
    container.classList.add('hidden');
    container.setAttribute('aria-hidden', 'true');
    if (escHandler) {
        document.removeEventListener('keydown', escHandler);
        escHandler = null;
    }
    const resolver = resolveSelection;
    resolveSelection = null;
    if (resolver) {
        resolver(result);
    }
};

const attachPersistentListeners = () => {
    if (!container || listenersAttached) {
        return;
    }
    listenersAttached = true;
    const handleDismiss = (event: Event) => {
        event.preventDefault();
        closeModal(null);
    };
    container
        .querySelectorAll('[data-image-picker-dismiss]')
        .forEach((el) => {
            el.addEventListener('click', handleDismiss);
        });
    confirmButton?.addEventListener('click', (event) => {
        event.preventDefault();
        if (!selectedValue || container?.classList.contains('hidden')) {
            return;
        }
        closeModal({
            value: selectedValue,
            rel: selectedRel ?? '',
            name: selectedName ?? selectedValue,
        });
    });
};

const attachGridListeners = () => {
    if (!imagesRoot || imagesRoot.dataset.pickerListeners === '1') {
        return;
    }
    imagesRoot.dataset.pickerListeners = '1';
    imagesRoot.addEventListener('click', (event) => {
        const tile = (event.target as HTMLElement).closest<HTMLElement>(
            '.tile.image'
        );
        if (!tile) {
            return;
        }
        event.preventDefault();
        applySelectionFromTile(tile);
    });
    imagesRoot.addEventListener(
        'dblclick',
        (event) => {
            const tile = (event.target as HTMLElement).closest<HTMLElement>(
                '.tile.image'
            );
            if (!tile) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            applySelectionFromTile(tile);
            if (selectedValue) {
                closeModal({
                    value: selectedValue,
                    rel: selectedRel ?? '',
                    name: selectedName ?? selectedValue,
                });
            }
        },
        { capture: true }
    );
    imagesRoot.addEventListener('keydown', (event) => {
        const key = (event as KeyboardEvent).key;
        if (key !== 'Enter' && key !== ' ') {
            return;
        }
        const tile = (event.target as HTMLElement).closest<HTMLElement>(
            '.tile.image'
        );
        if (!tile) {
            return;
        }
        event.preventDefault();
        applySelectionFromTile(tile);
        if (key === 'Enter' && selectedValue) {
            closeModal({
                value: selectedValue,
                rel: selectedRel ?? '',
                name: selectedName ?? selectedValue,
            });
        }
    });
    imagesRoot.addEventListener('htmx:afterSwap', (event) => {
        const target = (event as CustomEvent).detail?.target as
            | HTMLElement
            | null
            | undefined;
        if (target && target.id === 'image-grid') {
            highlightSelection();
        }
    });
};

const openModal = () => {
    if (!container) {
        return;
    }
    container.classList.remove('hidden');
    container.setAttribute('aria-hidden', 'false');
    if (escHandler) {
        document.removeEventListener('keydown', escHandler);
        escHandler = null;
    }
    escHandler = (event: KeyboardEvent) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(null);
        }
    };
    document.addEventListener('keydown', escHandler);
    focusInitialTile();
};

const loadImagesMarkup = async (basePath: string, htmx: HTMX | null) => {
    if (imagesRoot) {
        return;
    }
    if (loadPromise) {
        await loadPromise;
        return;
    }
    const currentBody = bodyContainer;
    loadPromise = (async () => {
        if (!currentBody) {
            return;
        }
        currentBody.innerHTML = '<p>Načítám galerii…</p>';
        try {
            const response = await fetch(`${basePath}/editor/images`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'HX-Request': 'true',
                },
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const html = await response.text();
            const template = document.createElement('template');
            template.innerHTML = html;
            const root = template.content.querySelector<HTMLElement>(
                '#images-root'
            );
            if (!root) {
                throw new Error('Galerie nebyla nalezena.');
            }
            root.removeAttribute('data-island-mounted');
            currentBody.innerHTML = '';
            currentBody.appendChild(root);
            imagesRoot = root;
            htmx?.process?.(root);
            const module = await import('../images');
            module.default(root);
            attachGridListeners();
        } catch (error) {
            console.error('Image picker load failed', error);
            currentBody.innerHTML =
                '<p role="alert">Galerii se nepodařilo načíst.</p>';
            selectionLabel?.setAttribute('data-error', 'true');
            if (selectionLabel) {
                selectionLabel.textContent =
                    'Galerii se nepodařilo načíst. Zkuste to prosím později.';
            }
        }
    })();
    await loadPromise;
};

export const openImagePickerModal = async (
    options: ImagePickerOptions
): Promise<ImagePickerSelection | null> => {
    currentBasePath = options.basePath || '';
    ensureShell();
    attachPersistentListeners();
    const htmx = getHtmx();
    await loadImagesMarkup(currentBasePath, htmx);

    if (!container) {
        return null;
    }

    if (!imagesRoot) {
        // Loading failed
        updateSelectionSummary();
        return new Promise<ImagePickerSelection | null>((resolve) => {
            if (resolveSelection) {
                const prev = resolveSelection;
                resolveSelection = null;
                prev(null);
            }
            resolveSelection = resolve;
            openModal();
        });
    }

    if (selectionLabel?.hasAttribute('data-error')) {
        selectionLabel.removeAttribute('data-error');
        selectionLabel.textContent = 'Vyberte obrázek z galerie.';
    }

    resetSelection(options.currentValue ?? '');

    attachGridListeners();

    return new Promise<ImagePickerSelection | null>((resolve) => {
        if (resolveSelection) {
            const prev = resolveSelection;
            resolveSelection = null;
            prev(null);
        }
        resolveSelection = resolve;
        openModal();
    });
};
