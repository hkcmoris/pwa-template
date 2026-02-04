import { listSelectors } from './constants';
import type {
    DefinitionsApiClient,
    DragContext,
    DropContext,
    DropPosition,
} from './types';
import { isDescendantPath } from './utils';

const clearDropClasses = (root: HTMLElement) => {
    root.querySelectorAll(
        '.definition-item--drop-before, .definition-item--drop-after, .definition-item--drop-inside'
    ).forEach((el) =>
        el.classList.remove(
            'definition-item--drop-before',
            'definition-item--drop-after',
            'definition-item--drop-inside'
        )
    );
};

const getSiblingItems = (
    root: HTMLElement,
    parentId: string | null
): HTMLElement[] => {
    const desiredParent = parentId ?? '';
    return Array.from(root.querySelectorAll<HTMLElement>(listSelectors.item))
        .filter((item) => (item.dataset.parent ?? '') === desiredParent);
};

const getPositionForDrop = (
    root: HTMLElement,
    item: HTMLElement,
    positionAttr: string | undefined
): number => {
    const rawPosition = Number.parseInt(positionAttr ?? '', 10);
    if (!Number.isNaN(rawPosition)) {
        return rawPosition;
    }
    const parentId = item.dataset.parent ?? '';
    const siblingList = getSiblingItems(root, parentId);
    const index = siblingList.indexOf(item);
    return index >= 0 ? index : siblingList.length ? siblingList.length - 1 : 0;
};

const getChildCount = (root: HTMLElement, parentId: string): number =>
    getSiblingItems(root, parentId).length;

export const setupDragAndDrop = (
    root: HTMLElement,
    api: DefinitionsApiClient
) => {
    let dragContext: DragContext | null = null;
    const dropContext: DropContext = { item: null, position: null };

    const updateDropHighlight = (
        item: HTMLElement | null,
        position: DropPosition | null
    ) => {
        clearDropClasses(root);

        dropContext.item = item;
        dropContext.position = position;

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

        const rect = targetNode.getBoundingClientRect();
        const offsetX = (event.clientX ?? rect.left) - rect.left;
        const offsetY = (event.clientY ?? rect.top) - rect.top;

        event.dataTransfer?.setDragImage(targetNode, offsetX, offsetY);
    });

    root.addEventListener('dragend', () => {
        if (dragContext) {
            dragContext.item.classList.remove('definition-item--dragging');
            dragContext.item
                .querySelector(listSelectors.node)
                ?.classList.remove('definition-node--dragging');
        }

        dragContext = null;
        dropContext.item = null;
        dropContext.position = null;
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
        const targetPosition = getPositionForDrop(
            root,
            targetItem,
            targetItem.dataset.position
        );

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
            position = getChildCount(root, targetIdRaw);
        } else {
            position =
                dropPosition === 'before' ? targetPosition : targetPosition + 1;
        }

        if (parentId === dragId) {
            updateDropHighlight(null, null);
            return;
        }

        if (parentId && !/^\d+$/.test(parentId)) {
            parentId = null;
        }

        const currentParentRaw = dragContext.item.dataset.parent ?? '';
        const currentParentId =
            currentParentRaw === '' ? null : currentParentRaw.trim();
        const currentPosition = Number.parseInt(
            dragContext.item.dataset.position ?? '',
            10
        );
        const desiredPosition = Math.floor(position);

        if (
            parentId === currentParentId &&
            !Number.isNaN(currentPosition) &&
            desiredPosition === currentPosition
        ) {
            updateDropHighlight(null, null);
            return;
        }

        api.move({
            id: dragId,
            parentId,
            position: desiredPosition,
        });

        updateDropHighlight(null, null);
    });

    root.addEventListener('dragover', (event) => {
        if (!dragContext) return;

        const target = event.target as HTMLElement;

        if (!root.contains(target) || target.closest(listSelectors.item))
            return;

        event.preventDefault();

        updateDropHighlight(null, null);
    });

    root.addEventListener('drop', (event) => {
        if (!dragContext) return;

        const targetEl = event.target as HTMLElement;

        if (targetEl.closest(listSelectors.item)) return;

        event.preventDefault();

        const rootItems = Array.from(
            getSiblingItems(root, null)
        );

        const position = rootItems.length;

        api.move({
            id: dragContext.id,
            parentId: null,
            position,
        });

        updateDropHighlight(null, null);
    });
};

export { isDescendantPath };
