import type { ComponentApiClient, HTMX } from './types';

type CreateApiClientOptions = {
    listTarget: string;
    htmx: HTMX | null;
    base: string;
};

const deleteViaForm = (deleteUrl: string, componentId: string) => {
    const bodyEl = document.body;
    if (!bodyEl) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'post';
    form.action = deleteUrl;

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'component_id';
    input.value = componentId;
    form.appendChild(input);

    bodyEl.appendChild(form);
    form.submit();
};

export const createComponentApiClient = ({
    listTarget,
    htmx,
    base,
}: CreateApiClientOptions): ComponentApiClient => ({
    deleteComponent(componentId) {
        if (!componentId) {
            return;
        }

        if (htmx && typeof htmx.ajax === 'function') {
            htmx.ajax('POST', `${base}/editor/components/delete`, {
                source: listTarget,
                target: listTarget,
                select: listTarget,
                swap: 'outerHTML',
                values: { component_id: componentId },
            });
            return;
        }

        deleteViaForm(`${base}/editor/components/delete`, componentId);
    },
    move({ id, parentId, position }) {
        if (!id || !htmx || typeof htmx.ajax !== 'function') {
            return;
        }

        const payload: Record<string, unknown> = {
            id,
            parent_id: parentId ?? '',
            position: String(Math.max(0, Math.floor(position))),
        };

        htmx.ajax('POST', `${base}/editor/components/move`, {
            source: listTarget,
            target: listTarget,
            select: listTarget,
            swap: 'outerHTML',
            values: payload,
        });
    },
});
