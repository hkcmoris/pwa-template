import { getCsrfToken } from '../../utils/api';

type AddressSaveOk = {
    message: string;
    address_id: number;
};

function isAddressSaveOk(payload: unknown): payload is AddressSaveOk {
    if (typeof payload !== 'object' || payload === null) {
        return false;
    }

    const candidate = payload as Record<string, unknown>;
    return (
        typeof candidate.message === 'string' &&
        typeof candidate.address_id === 'number'
    );
}

const getAddressError = (payload: unknown): string | null => {
    if (typeof payload !== 'object' || payload === null) {
        return null;
    }

    const candidate = payload as Record<string, unknown>;
    return typeof candidate.error === 'string' ? candidate.error : null;
};

const buildAdminUrl = (path: string) => {
    const base = document.documentElement?.dataset?.base ?? '';
    return `${base}${path}`;
};

export const initAdminAddress = (root: HTMLElement) => {
    const form = root.querySelector<HTMLFormElement>('#admin-address-form');
    const feedback = root.querySelector<HTMLElement>('#admin-address-feedback');
    if (!form || !feedback) {
        return;
    }

    const showFeedback = (message: string, kind: 'success' | 'error') => {
        feedback.classList.remove('hidden');
        feedback.classList.toggle('admin-address-feedback--success', kind === 'success');
        feedback.classList.toggle('admin-address-feedback--error', kind === 'error');
        feedback.textContent = message;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const endpoint = buildAdminUrl('/admin/address');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': getCsrfToken(),
                },
            });

            const payload = (await response.json().catch(() => null)) as unknown;

            if (!response.ok) {
                const message = getAddressError(payload) ?? 'Uložení adresy selhalo.';
                showFeedback(message, 'error');
                return;
            }

            if (isAddressSaveOk(payload)) {
                showFeedback(payload.message, 'success');
                return;
            }

            showFeedback('Adresa byla uložena.', 'success');
        } catch {
            showFeedback('Operaci se nepodařilo dokončit.', 'error');
        }
    });
};
