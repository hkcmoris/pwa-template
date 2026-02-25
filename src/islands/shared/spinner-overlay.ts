import './spinner-overlay.css';

export type SpinnerOverlayHandle = {
    element: HTMLElement;
    show: () => void;
    hide: () => void;
    setLabel: (label: string) => void;
};

export type SpinnerOverlayOptions = {
    /**
     * DOM id for the overlay. Defaults to `spinner-overlay` if omitted.
     */
    id?: string;
    /**
     * Text label presented to users while the overlay is visible.
     */
    label: string;
    /**
     * Optional container that will receive the overlay. Falls back to
     * `document.body` when not provided.
     */
    parent?: HTMLElement | DocumentFragment;
    /**
     * Aria-live politeness level. Defaults to `assertive`.
     */
    ariaLive?: 'polite' | 'assertive';
    /**
     * Extra class names to append to the overlay root.
     */
    className?: string;
};

const hiddenClass = 'spinner-overlay--hidden';

export const createSpinnerOverlay = (
    options: SpinnerOverlayOptions
): SpinnerOverlayHandle | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const {
        id = 'spinner-overlay',
        label,
        parent,
        ariaLive = 'assertive',
        className,
    } = options;

    const targetParent = parent ?? document.body;
    if (!targetParent) {
        return null;
    }

    let overlay = document.getElementById(id) as HTMLElement | null;

    const buildMarkup = () => {
        overlay = overlay ?? document.createElement('div');
        overlay.id = id;
        overlay.innerHTML = '';
        overlay.className = '';
        overlay.classList.add('spinner-overlay', hiddenClass);
        if (className) {
            className
                .split(' ')
                .map((token) => token.trim())
                .filter(Boolean)
                .forEach((token) => overlay?.classList.add(token));
        }
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-busy', 'false');

        const panel = document.createElement('div');
        panel.className = 'spinner-overlay-panel';
        panel.setAttribute('role', 'status');
        panel.setAttribute('aria-live', ariaLive);

        const spinner = document.createElement('div');
        spinner.className = 'spinner-overlay-spinner';
        spinner.setAttribute('aria-hidden', 'true');

        const labelElement = document.createElement('p');
        labelElement.className = 'spinner-overlay-label';
        labelElement.textContent = label;

        panel.appendChild(spinner);
        panel.appendChild(labelElement);
        overlay.appendChild(panel);
    };

    if (!overlay) {
        buildMarkup();
        targetParent.appendChild(overlay!);
    } else {
        if (!overlay.classList.contains('spinner-overlay')) {
            // If an existing element shares the id but not our structure, rebuild it.
            buildMarkup();
        } else {
            const panel = overlay.querySelector<HTMLElement>(
                '.spinner-overlay-panel'
            );
            if (panel) {
                panel.setAttribute('aria-live', ariaLive);
            }
            const labelElement = overlay.querySelector<HTMLElement>(
                '.spinner-overlay-label'
            );
            if (labelElement) {
                labelElement.textContent = label;
            } else {
                buildMarkup();
            }
        }
        if (!overlay.isConnected) {
            targetParent.appendChild(overlay);
        }
        if (className) {
            className
                .split(' ')
                .map((token) => token.trim())
                .filter(Boolean)
                .forEach((token) => overlay?.classList.add(token));
        }
    }

    const element = overlay!;

    const setLabel = (value: string) => {
        const labelElement = element.querySelector<HTMLElement>(
            '.spinner-overlay-label'
        );
        if (labelElement) {
            labelElement.textContent = value;
        }
    };

    setLabel(label);

    return {
        element,
        show: () => {
            element.classList.remove(hiddenClass);
            element.setAttribute('aria-hidden', 'false');
            element.setAttribute('aria-busy', 'true');
        },
        hide: () => {
            element.classList.add(hiddenClass);
            element.setAttribute('aria-hidden', 'true');
            element.setAttribute('aria-busy', 'false');
        },
        setLabel,
    };
};

export default createSpinnerOverlay;
