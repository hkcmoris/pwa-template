import { definitionsTargetSelector } from './constants';
import type { DefinitionsApiClient, HTMX } from './types';

const sendRequest = (
    htmx: HTMX,
    url: string,
    values: Record<string, unknown>
): XMLHttpRequest =>
    htmx.ajax('POST', url, {
        source: definitionsTargetSelector,
        target: definitionsTargetSelector,
        values,
        swap: 'outerHTML',
        select: definitionsTargetSelector,
    });

export const createDefinitionsApiClient = (
    htmx: HTMX,
    base: string
): DefinitionsApiClient => ({
    rename(id, title) {
        if (!id || !title.trim()) {
            return;
        }
        sendRequest(htmx, `${base}/editor/definitions/rename`, {
            id,
            title,
        });
    },
    delete(id) {
        if (!id) {
            return;
        }
        sendRequest(htmx, `${base}/editor/definitions/delete`, { id });
    },
    move({ id, parentId, position }) {
        if (!id) {
            return;
        }
        const payload: Record<string, unknown> = {
            id,
            parent_id: parentId ?? '',
            position: String(Math.max(0, Math.floor(position))),
        };
        sendRequest(htmx, `${base}/editor/definitions/move`, payload);
    },
    updateRange(id, options) {
        if (!id) {
            return;
        }
        const payload: Record<string, unknown> = { id };

        if (options.mode === 'clear') {
            payload.mode = 'clear';
        } else {
            payload.mode = 'set';
            payload.value_min = options.min ?? '';
            payload.value_max = options.max ?? '';
        }

        sendRequest(htmx, `${base}/editor/definitions/range`, payload);
    },
});
