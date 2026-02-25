import { listSelectors } from './constants';
import type {
    ComponentApiClient,
    DragContext,
    DropContext,
    DropPosition,
} from './types';

const clearDropClasses = (root: HTMLElement) => {
    root.querySelectorAll(
        '.component-item--drop-before, .component-item--drop-after, .component-item--drop-inside'
    ).forEach((el) =>
        el.classList.remove(
            'component-item--drop-before',
            'component-item--drop-after',
            'component-item--drop-inside'
        )
    );
};

const getItemMap = (root: HTMLElement): Map<string, HTMLElement> => {
    const items = root.querySelectorAll<HTMLElement>(listSelectors.item);
    const map = new Map<string, HTMLElement>();

    items.forEach((item) => {
        const id = (item.dataset.id ?? '').trim();
        if (id !== '') {
            map.set(id, item);
        }
    });

    return map;
};

const isDescendant = (
    ancestorId: string,
    candidateId: string,
    itemsById: Map<string, HTMLElement>
): boolean => {
    if (ancestorId === candidateId) {
        return true;
    }

    let current = candidateId;
    const visited = new Set<string>();

    while (current !== '') {
        if (visited.has(current)) {
            return false;
        }
        visited.add(current);

        const item = itemsById.get(current);
        if (!item) {
            return false;
        }

        const parentId = (item.dataset.parent ?? '').trim();
        if (parentId === '') {
            return false;
        }

        if (parentId === ancestorId) {
            return true;
        }

        current = parentId;
    }

    return false;
};

const getPositionForDrop = (
    root: HTMLElement,
    item: HTMLElement,
    parentId: string | null,
    positionAttr: string | undefined
): number => {
    const rawPosition = Number.parseInt(positionAttr ?? '', 10);
    if (!Number.isNaN(rawPosition)) {
        return rawPosition;
    }

    const normalizedParent = parentId ?? '';
    const siblings = Array.from(
        root.querySelectorAll<HTMLElement>(listSelectors.item)
    ).filter(
        (entry) =>
            (entry.dataset.parent ?? '') === normalizedParent &&
            entry.dataset.id !== undefined
    );
    const index = siblings.indexOf(item);
    return index >= 0 ? index : siblings.length ? siblings.length - 1 : 0;
};

const getChildCount = (item: HTMLElement, root: HTMLElement): number => {
    const rawChildCount = Number.parseInt(item.dataset.childrenCount ?? '', 10);
    if (!Number.isNaN(rawChildCount)) {
        return rawChildCount;
    }
    const id = item.dataset.id ?? '';
    if (!id) {
        return 0;
    }
    return Array.from(
        root.querySelectorAll<HTMLElement>(listSelectors.item)
    ).filter((entry) => (entry.dataset.parent ?? '') === id).length;
};

export const setupDragAndDrop = (
    root: HTMLElement,
    api: ComponentApiClient
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
                ? 'component-item--drop-before'
                : position === 'after'
                  ? 'component-item--drop-after'
                  : 'component-item--drop-inside'
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
            parentId: item.dataset.parent ?? '',
        };

        targetNode.classList.add('component-node--dragging');
        item.classList.add('component-item--dragging');
        event.dataTransfer?.setData('text/plain', dragContext.id);

        const rect = targetNode.getBoundingClientRect();
        const offsetX = (event.clientX ?? rect.left) - rect.left;
        const offsetY = (event.clientY ?? rect.top) - rect.top;

        event.dataTransfer?.setDragImage(targetNode, offsetX, offsetY);
    });

    root.addEventListener('dragend', () => {
        if (dragContext) {
            dragContext.item.classList.remove('component-item--dragging');
            dragContext.item
                .querySelector(listSelectors.node)
                ?.classList.remove('component-node--dragging');
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

        const targetId = (item.dataset.id ?? '').trim();
        const dragId = (dragContext.id ?? '').trim();

        if (!targetId || !dragId) return;

        const itemsById = getItemMap(root);
        if (isDescendant(dragId, targetId, itemsById)) {
            return;
        }

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
        const targetParent = parentAttr === '' ? null : parentAttr.trim();
        const targetPosition = getPositionForDrop(
            root,
            targetItem,
            targetParent,
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
            position = getChildCount(targetItem, root);
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

        if (!root.contains(target) || target.closest(listSelectors.item)) {
            return;
        }

        event.preventDefault();

        updateDropHighlight(null, null);
    });

    root.addEventListener('drop', (event) => {
        if (!dragContext) return;

        const targetEl = event.target as HTMLElement;

        if (targetEl.closest(listSelectors.item)) return;

        event.preventDefault();

        const rootItems = Array.from(
            root.querySelectorAll<HTMLElement>(listSelectors.item)
        ).filter((item) => (item.dataset.parent ?? '') === '');

        const position = rootItems.length;

        api.move({
            id: dragContext.id,
            parentId: null,
            position,
        });

        updateDropHighlight(null, null);
    });
};
