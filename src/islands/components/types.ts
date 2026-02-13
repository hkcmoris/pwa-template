export type HTMX = {
    process?: (elt: Element) => void;
    ajax?: (
        method: string,
        url: string,
        options: {
            source?: Element | string;
            values?: Record<string, unknown>;
            target?: Element | string;
            swap?: string;
            select?: string;
            headers?: Record<string, string>;
        }
    ) => XMLHttpRequest;
};

export type AfterRequestDetail = {
    xhr?: XMLHttpRequest;
};

export type SelectChangeDetail = {
    value?: string;
};

export type ComponentModalMode = 'create' | 'edit';

export type DropPosition = 'before' | 'after' | 'inside';

export type DragContext = {
    item: HTMLElement;
    id: string;
    parentId: string;
};

export type DropContext = {
    item: HTMLElement | null;
    position: DropPosition | null;
};

export type ComponentProperty = {
    name?: string;
    value?: string;
    unit?: string;
};

export type PriceHistoryItem = {
    amount?: string;
    currency?: string;
    created_at?: string;
};

export type ComponentModalOptions = {
    mode?: ComponentModalMode;
    componentId?: string;
    definitionId?: string;
    parentId?: string;
    parentTitle?: string;
    childCount?: number;
    alternateTitle?: string;
    description?: string;
    image?: string;
    images?: string[];
    color?: string;
    mediaType?: 'image' | 'color';
    position?: number;
    displayName?: string;
    priceAmount?: string;
    priceCurrency?: string;
    priceHistory?: PriceHistoryItem[];
    dependencyTree?: string;
    properties?: ComponentProperty[];
};

export type MediaMode = 'image' | 'color';

export type SelectedImageEntry = {
    value: string;
    label: string | null;
};

export type ComponentApiClient = {
    deleteComponent: (componentId: string) => void;
    cloneComponent: (componentId: string) => void;
    move: (options: {
        id: string;
        parentId: string | null;
        position: number;
    }) => void;
};
