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
    color?: string;
    mediaType?: 'image' | 'color';
    position?: number;
    displayName?: string;
    priceAmount?: string;
    priceCurrency?: string;
    priceHistory?: PriceHistoryItem[];
};

export type MediaMode = 'image' | 'color';
