import type { ComponentApiClient } from './types';
import { parsePriceHistoryDataset } from './form';
import { ComponentModalManager } from './modal';
import { ComponentModalOptions, ComponentProperty } from './types';
import { escapeHtml, parseImagesDataset } from './utils';
import { listSelectors } from './constants';

type CopyKey = 'description' | 'images' | 'properties' | 'color' | 'price';

type CopyOption = {
    key: CopyKey;
    label: string;
    summary: string;
};

type ComponentCopyPayload = {
    copiedAt: number;
    sourceTitle: string;
    values: Partial<Pick<ComponentModalOptions, 'description' | 'images' | 'image' | 'properties' | 'color' | 'mediaType' | 'priceAmount' | 'priceCurrency'>>;
};

const CLIPBOARD_KEY = 'editor.componentCopyClipboard.v1';

const parseProperties = (raw: string | undefined): ComponentProperty[] => {
    try {
        const parsed = JSON.parse(raw ?? '[]') as unknown;
        if (!Array.isArray(parsed)) return [];
        return parsed.filter(
            (entry): entry is ComponentProperty =>
                entry !== null && typeof entry === 'object'
        );
    } catch {
        return [];
    }
};

const readItemOptions = (item: HTMLElement): ComponentModalOptions => {
    const parentId = item.dataset.parent ?? '';
    const positionRaw = item.dataset.position;
    const parsedPosition =
        positionRaw !== undefined ? Number.parseInt(positionRaw, 10) : NaN;
    const priceAmount = item.dataset.priceAmount ?? '';
    const priceCurrency = item.dataset.priceCurrency ?? 'CZK';

    return {
        mode: 'edit',
        componentId: item.dataset.id ?? '',
        definitionId: item.dataset.definitionId ?? '',
        alternateTitle: item.dataset.alternateTitle ?? '',
        description: item.dataset.description ?? '',
        image: item.dataset.image ?? '',
        images: parseImagesDataset(item.dataset.images, item.dataset.image),
        color: item.dataset.color ?? '',
        mediaType:
            (item.dataset.mediaType as 'image' | 'color' | undefined) ??
            undefined,
        parentId,
        position: Number.isNaN(parsedPosition) ? undefined : parsedPosition,
        displayName: item.dataset.title ?? '',
        priceAmount,
        priceCurrency,
        priceHistory: parsePriceHistoryDataset(item.dataset.priceHistory),
        dependencyTree: item.dataset.dependencyTree ?? '',
        properties: parseProperties(item.dataset.properties),
        allowMultiSelect: item.dataset.allowMultiSelect === '1',
    };
};

const getCopyOptions = (options: ComponentModalOptions): CopyOption[] => {
    const copyOptions: CopyOption[] = [];
    const images = options.images ?? [];
    const properties = options.properties ?? [];

    if ((options.description ?? '').trim() !== '') {
        copyOptions.push({
            key: 'description',
            label: 'Popis',
            summary: options.description ?? '',
        });
    }

    if (images.length > 0) {
        copyOptions.push({
            key: 'images',
            label: 'Sada obrázků',
            summary: `${images.length} obr.`,
        });
    }

    if (properties.length > 0) {
        copyOptions.push({
            key: 'properties',
            label: 'Vlastnosti',
            summary: `${properties.length} položek`,
        });
    }

    if ((options.color ?? '').trim() !== '') {
        copyOptions.push({
            key: 'color',
            label: 'Barva',
            summary: options.color ?? '',
        });
    }

    if ((options.priceAmount ?? '').trim() !== '') {
        copyOptions.push({
            key: 'price',
            label: 'Cena',
            summary: `${options.priceAmount} ${options.priceCurrency ?? 'CZK'}`,
        });
    }

    return copyOptions;
};

const readClipboard = (): ComponentCopyPayload | null => {
    try {
        const raw = window.localStorage.getItem(CLIPBOARD_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as ComponentCopyPayload;
        if (!parsed || typeof parsed !== 'object' || !parsed.values) {
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
};

const writeClipboard = (payload: ComponentCopyPayload) => {
    window.localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(payload));
    window.dispatchEvent(new CustomEvent('component-copy:changed'));
};

const labelsForPayload = (payload: ComponentCopyPayload | null): string[] => {
    if (!payload) return [];
    const labels: string[] = [];
    if (payload.values.description !== undefined) labels.push('Popis');
    if (payload.values.images !== undefined) labels.push('Obrázky');
    if (payload.values.properties !== undefined) labels.push('Vlastnosti');
    if (payload.values.color !== undefined) labels.push('Barva');
    if (payload.values.priceAmount !== undefined) labels.push('Cena');
    return labels;
};

const updatePasteButtons = (root: HTMLElement) => {
    const hasClipboard = readClipboard() !== null;
    root.querySelectorAll<HTMLButtonElement>('[data-action="paste"]').forEach(
        (button) => {
            button.classList.toggle('hidden', !hasClipboard);
            button.setAttribute('aria-hidden', hasClipboard ? 'false' : 'true');
        }
    );
};

const ensureIndicator = () => {
    let indicator = document.querySelector<HTMLElement>(
        '[data-component-copy-indicator]'
    );

    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'component-copy-indicator hidden';
        indicator.dataset.componentCopyIndicator = 'true';
        document.body.appendChild(indicator);
    }

    const payload = readClipboard();
    const labels = labelsForPayload(payload);
    indicator.classList.toggle('hidden', labels.length === 0);
    indicator.innerHTML = payload
        ? `<strong>V paměti kopie</strong><span>${escapeHtml(
              labels.join(', ')
          )}</span><small>Zdroj: ${escapeHtml(payload.sourceTitle)}</small>`
        : '';
};

const setupClipboardUi = (root: HTMLElement) => {
    updatePasteButtons(root);
    ensureIndicator();
    window.addEventListener('component-copy:changed', () => {
        updatePasteButtons(root);
        ensureIndicator();
    });
    window.addEventListener('storage', (event) => {
        if (event.key === CLIPBOARD_KEY) {
            updatePasteButtons(root);
            ensureIndicator();
        }
    });
};

const openCopyModal = (
    item: HTMLElement,
    title: string,
    modal: ComponentModalManager
) => {
    const options = readItemOptions(item);
    const copyOptions = getCopyOptions(options);
    const container = document.createElement('div');
    container.className = 'component-modal-body';

    if (copyOptions.length === 0) {
        container.innerHTML = `<p>Komponenta <strong>${escapeHtml(
            title
        )}</strong> nemá žádné vyplněné údaje vhodné ke kopírování.</p>`;
        modal.open('Kopírovat vlastnosti', container);
        return;
    }

    container.innerHTML = `
        <p>Vyberte údaje, které chcete uložit do lokální paměti pro vložení do jiné komponenty.</p>
        <fieldset class="component-copy-options">
          <legend class="sr-only">Údaje ke kopírování</legend>
          ${copyOptions
              .map(
                  (option) => `
                    <label class="component-checkbox component-copy-option">
                      <input type="checkbox" value="${option.key}" checked>
                      <span><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(
                        option.summary
                    )}</small></span>
                    </label>`
              )
              .join('')}
        </fieldset>
        <div class="components-modal-actions">
          <button type="button" class="component-action" data-modal-close>Storno</button>
          <button type="button" class="component-primary" data-copy-confirm>Kopírovat</button>
        </div>`;

    modal.open('Kopírovat vlastnosti', container);
    container
        .querySelector<HTMLButtonElement>('[data-copy-confirm]')
        ?.addEventListener('click', () => {
            const selected = Array.from(
                container.querySelectorAll<HTMLInputElement>(
                    '.component-copy-option input:checked'
                )
            ).map((input) => input.value as CopyKey);
            const values: ComponentCopyPayload['values'] = {};

            if (selected.includes('description')) {
                values.description = options.description ?? '';
            }
            if (selected.includes('images')) {
                values.images = options.images ?? [];
                values.image = options.image ?? '';
                values.mediaType = 'image';
            }
            if (selected.includes('properties')) {
                values.properties = options.properties ?? [];
            }
            if (selected.includes('color')) {
                values.color = options.color ?? '';
                values.mediaType = 'color';
            }
            if (selected.includes('price')) {
                values.priceAmount = options.priceAmount ?? '';
                values.priceCurrency = options.priceCurrency ?? 'CZK';
            }

            writeClipboard({
                copiedAt: Date.now(),
                sourceTitle: title,
                values,
            });
            modal.close();
        });
};

export const setupNodeActions = (
    root: HTMLElement,
    api: ComponentApiClient,
    modal: ComponentModalManager,
    openComponentModal: (options?: ComponentModalOptions) => void
) => {
    setupClipboardUi(root);

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
            const raw = item.dataset.childrenCount;
            const parsed = raw !== undefined ? Number.parseInt(raw, 10) : NaN;
            const childCount = Number.isNaN(parsed) ? undefined : parsed;
            openComponentModal({
                parentId: id,
                parentTitle: title,
                childCount,
            });
        } else if (action === 'edit') {
            openComponentModal(readItemOptions(item));
        } else if (action === 'copy') {
            openCopyModal(item, title, modal);
        } else if (action === 'paste') {
            const payload = readClipboard();
            if (payload) {
                openComponentModal({
                    ...readItemOptions(item),
                    ...payload.values,
                });
            }
        } else if (action === 'clone') {
            api.cloneComponent(id);
        } else if (action === 'delete') {
            let childCount = 0;
            if (item.dataset.childrenCount) {
                const parsedChildren = Number.parseInt(
                    item.dataset.childrenCount,
                    10
                );
                if (!Number.isNaN(parsedChildren) && parsedChildren > 0) {
                    childCount = parsedChildren;
                }
            }

            const container = document.createElement('div');
            container.className = 'component-modal-body';
            const detailText =
                childCount > 0
                    ? `Tato akce odstrani take vsechny jeji podkomponenty (vcetne ${
                          childCount === 1
                              ? '1 primo podrizene'
                              : `${childCount} primo podrizenych`
                      } komponent).`
                    : 'Tato akce nelze vratit.';
            container.innerHTML = `
                <p>Opravdu chcete smazat komponentu <strong>${escapeHtml(
                    title
                )}</strong>?</p>
                <p>${escapeHtml(detailText)}</p>
                <div class="components-modal-actions">
                  <button type="button" class="component-action" data-modal-close>Storno</button>
                  <button type="button" class="component-action component-action--danger" data-modal-confirm>Smazat</button>
                </div>
            `;

            modal.open('Smazat komponentu', container);

            container
                .querySelector<HTMLButtonElement>('[data-modal-confirm]')
                ?.addEventListener('click', () => {
                    api.deleteComponent(id);
                    modal.close();
                });
        }
    });
};
