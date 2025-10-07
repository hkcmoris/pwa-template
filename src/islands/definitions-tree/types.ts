export type DropPosition = 'before' | 'after' | 'inside';

export type HTMX = {
    ajax: (
        method: string,
        url: string,
        options: {
            source?: Element | string;
            values?: Record<string, unknown>;
            target?: Element | string;
            swap?: string;
            select?: string;
        }
    ) => XMLHttpRequest;
    process?: (elt: Element) => void;
};

export type DragContext = {
    item: HTMLElement;
    id: string;
    path: string;
};

export type DropContext = {
    item: HTMLElement | null;
    position: DropPosition | null;
};

export type OpenCreateOptions = {
    parentId?: string | null;
    parentTitle?: string;
    childCount?: number;
};

export type ModalManager = {
    open: (title: string, body: HTMLElement) => void;
    close: () => void;
};

export type DefinitionsApiClient = {
    rename: (id: string, title: string) => void;
    delete: (id: string) => void;
    move: (options: { id: string; parentId: string | null; position: number }) => void;
};
