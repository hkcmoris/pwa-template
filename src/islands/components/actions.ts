import type { ComponentApiClient } from './types';
import { parsePriceHistoryDataset } from './form';
import { ComponentModalManager } from './modal';
import { ComponentModalOptions } from './types';
import { escapeHtml, parseImagesDataset } from './utils';
import { listSelectors } from './constants';

export const setupNodeActions = (
    root: HTMLElement,
    api: ComponentApiClient,
    modal: ComponentModalManager,
    openComponentModal: (options?: ComponentModalOptions) => void
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
            const raw = item.dataset.childrenCount;
            const parsed = raw !== undefined ? Number.parseInt(raw, 10) : NaN;
            const childCount = Number.isNaN(parsed) ? undefined : parsed;
            openComponentModal({
                parentId: id,
                parentTitle: title,
                childCount,
            });
        } else if (action === 'edit') {
            const parentId = item.dataset.parent ?? '';
            const positionRaw = item.dataset.position;
            const parsedPosition =
                positionRaw !== undefined
                    ? Number.parseInt(positionRaw, 10)
                    : NaN;
            const priceAmount = item.dataset.priceAmount ?? '';
            const priceCurrency = item.dataset.priceCurrency ?? 'CZK';
            const priceHistory = parsePriceHistoryDataset(
                item.dataset.priceHistory
            );
            const images = parseImagesDataset(
                item.dataset.images,
                item.dataset.image
            );
            openComponentModal({
                mode: 'edit',
                componentId: id,
                definitionId: item.dataset.definitionId ?? '',
                alternateTitle: item.dataset.alternateTitle ?? '',
                description: item.dataset.description ?? '',
                image: item.dataset.image ?? '',
                images,
                color: item.dataset.color ?? '',
                mediaType:
                    (item.dataset.mediaType as 'image' | 'color' | undefined) ??
                    undefined,
                parentId,
                position: Number.isNaN(parsedPosition)
                    ? undefined
                    : parsedPosition,
                displayName: title ?? '',
                priceAmount,
                priceCurrency,
                priceHistory,
                dependencyTree: item.dataset.dependencyTree ?? '',
            });
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
