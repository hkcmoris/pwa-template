import './select.css';

export function setSelectValue(sel: HTMLElement, value: string) {
    const btn = sel.querySelector<HTMLButtonElement>('.select__button');
    const list = sel.querySelector<HTMLUListElement>('.select__list');
    if (!btn || !list) return;
    const options = Array.from(
        list.querySelectorAll<HTMLLIElement>('.select__option')
    );
    options.forEach((o) =>
        o.setAttribute('aria-selected', String(o.dataset.value === value))
    );
    btn.textContent = value;
    sel.setAttribute('data-value', value);
}

export function enhanceSelects(root: Document | HTMLElement = document) {
    const selects = root.querySelectorAll<HTMLElement>('.select[data-select]');
    if (!selects.length) return;

    const closeAll = () => {
        selects.forEach((s) => {
            const btn = s.querySelector<HTMLButtonElement>('.select__button');
            const list = s.querySelector<HTMLUListElement>('.select__list');
            if (btn && list) {
                btn.setAttribute('aria-expanded', 'false');
                list.hidden = true;
            }
        });
    };

    document.addEventListener('click', (e) => {
        const target = e.target as HTMLElement;
        if (!target.closest('.select[data-select]')) closeAll();
    });

    selects.forEach((sel) => {
        const btn = sel.querySelector<HTMLButtonElement>('.select__button');
        const list = sel.querySelector<HTMLUListElement>('.select__list');
        if (!btn || !list) return;
        const options = Array.from(
            list.querySelectorAll<HTMLLIElement>('.select__option')
        );

        const open = () => {
            closeAll();
            btn.setAttribute('aria-expanded', 'true');
            list.hidden = false;
            list.focus();
        };
        const close = () => {
            btn.setAttribute('aria-expanded', 'false');
            list.hidden = true;
            btn.focus();
        };

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                close();
            } else {
                open();
            }
        });

        list.addEventListener('keydown', (e) => {
            const currentIndex = options.findIndex((o) =>
                o.matches('[data-active]')
            );
            let nextIndex = currentIndex < 0 ? 0 : currentIndex;
            if (e.key === 'ArrowDown') nextIndex = Math.min(options.length - 1, currentIndex + 1);
            if (e.key === 'ArrowUp') nextIndex = Math.max(0, currentIndex - 1);
            if (e.key === 'Escape') return close();
            if (e.key === 'Enter') {
                const active = options[currentIndex] || options[0];
                active?.click();
                return;
            }
            options.forEach((o) => o.removeAttribute('data-active'));
            const next = options[nextIndex] || options[0];
            next.setAttribute('data-active', '');
            next.scrollIntoView({ block: 'nearest' });
            e.preventDefault();
        });

        options.forEach((opt) => {
            opt.addEventListener('click', () => {
                const val = opt.dataset.value || '';
                const prev = sel.getAttribute('data-value') || '';
                if (!val || val === prev) {
                    close();
                    return;
                }
                setSelectValue(sel, val);
                sel.dispatchEvent(
                    new CustomEvent('select:change', {
                        bubbles: true,
                        detail: { value: val, previous: prev },
                    })
                );
                close();
            });
        });
    });
}

export default function mount(el: HTMLElement) {
    enhanceSelects(el);
}

