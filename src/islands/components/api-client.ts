import type { HTMX } from './types';

export type ComponentApiClient = {
    deleteComponent: (componentId: string) => void;
};

type CreateApiClientOptions = {
    deleteUrl: string;
    listTarget: string;
    htmx: HTMX | null;
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
    deleteUrl,
    listTarget,
    htmx,
}: CreateApiClientOptions): ComponentApiClient => ({
    deleteComponent(componentId) {
        if (!componentId) {
            return;
        }

        if (htmx && typeof htmx.ajax === 'function') {
            htmx.ajax('POST', deleteUrl, {
                source: listTarget,
                target: listTarget,
                select: listTarget,
                swap: 'outerHTML',
                values: { component_id: componentId },
            });
            return;
        }

        deleteViaForm(deleteUrl, componentId);
    },
});
