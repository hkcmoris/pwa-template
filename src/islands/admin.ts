import { getCsrfToken } from '../utils/api';

const buildAdminUrl = (path: string) => {
    const base = document.documentElement?.dataset?.base ?? '';
    return `${base}${path}`;
};

const updateSubmitState = (
    submitButton: HTMLButtonElement | null,
    mode: 'import' | 'export',
    fileInput?: HTMLInputElement | null,
    confirmInput?: HTMLInputElement | null
) => {
    if (!submitButton) {
        return;
    }
    if (mode === 'import') {
        submitButton.disabled =
            !fileInput?.files?.length || !confirmInput?.checked;
    } else {
        submitButton.disabled = false;
    }
};

export default (root: HTMLElement) => {
    const modal = root.querySelector<HTMLElement>('#admin-transfer-modal');
    if (!modal) {
        return;
    }

    const title = modal.querySelector<HTMLHeadingElement>(
        '#admin-transfer-title'
    );
    const form = modal.querySelector<HTMLFormElement>('#admin-transfer-form');
    const fileFieldset = modal.querySelector<HTMLElement>('[data-admin-file]');
    const fileInput = modal.querySelector<HTMLInputElement>(
        'input[name="sql_file"]'
    );
    const confirmFieldset = modal.querySelector<HTMLElement>(
        '[data-admin-confirm]'
    );
    const confirmInput = modal.querySelector<HTMLInputElement>(
        'input[name="confirm_overwrite"]'
    );
    const submitButton =
        modal.querySelector<HTMLButtonElement>('[data-admin-submit]');
    const focusTarget = modal.querySelector<HTMLInputElement>(
        'input[name="definitions"]'
    );
    const definitionsInput = modal.querySelector<HTMLInputElement>(
        'input[name="definitions"]'
    );
    const componentsInput = modal.querySelector<HTMLInputElement>(
        'input[name="components"]'
    );
    const usersInput = modal.querySelector<HTMLInputElement>(
        'input[name="users"]'
    );
    const feedback = root.querySelector<HTMLElement>('#admin-messages');

    let currentMode: 'import' | 'export' = 'export';

    const showFeedback = (message: string, status: 'success' | 'error') => {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.classList.remove('hidden');
        feedback.classList.toggle('admin-feedback--success', status === 'success');
        feedback.classList.toggle('admin-feedback--error', status === 'error');
    };

    const hideFeedback = () => {
        feedback?.classList.add('hidden');
        feedback?.classList.remove('admin-feedback--success', 'admin-feedback--error');
        if (feedback) {
            feedback.textContent = '';
        }
    };

    const hasSelection = () =>
        Boolean(
            definitionsInput?.checked ||
                componentsInput?.checked ||
                usersInput?.checked
        );

    const syncDefinitionDependency = () => {
        if (!definitionsInput || !componentsInput) {
            return;
        }
        if (componentsInput.checked) {
            definitionsInput.checked = true;
            definitionsInput.disabled = true;
        } else {
            definitionsInput.disabled = false;
        }
    };

    const openModal = (mode: 'import' | 'export') => {
        currentMode = mode;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        modal.dataset.mode = mode;
        if (title) {
            title.textContent =
                mode === 'import' ? 'Import databáze' : 'Export databáze';
        }
        if (submitButton) {
            submitButton.textContent =
                mode === 'import' ? 'Importovat' : 'Exportovat';
        }
        if (fileFieldset) {
            fileFieldset.classList.toggle('hidden', mode !== 'import');
        }
        if (fileInput) {
            fileInput.value = '';
            fileInput.required = mode === 'import';
        }
        if (confirmFieldset) {
            confirmFieldset.classList.toggle('hidden', mode !== 'import');
        }
        if (confirmInput) {
            confirmInput.checked = false;
            confirmInput.required = mode === 'import';
        }
        updateSubmitState(submitButton, mode, fileInput, confirmInput);
        hideFeedback();
        requestAnimationFrame(() => {
            if (mode === 'import') {
                fileInput?.click();
            } else {
                focusTarget?.focus();
            }
        });
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('data-mode');
    };

    root.querySelectorAll<HTMLButtonElement>('[data-admin-modal]').forEach(
        (button) => {
            button.addEventListener('click', () => {
                const mode =
                    button.dataset.adminModal === 'import'
                        ? 'import'
                        : 'export';
                openModal(mode);
            });
        }
    );

    modal.querySelectorAll<HTMLElement>('[data-admin-modal-close]').forEach(
        (button) => {
            button.addEventListener('click', () => closeModal());
        }
    );

    fileInput?.addEventListener('change', () => {
        updateSubmitState(submitButton, currentMode, fileInput, confirmInput);
    });
    confirmInput?.addEventListener('change', () => {
        updateSubmitState(submitButton, currentMode, fileInput, confirmInput);
    });

    componentsInput?.addEventListener('change', syncDefinitionDependency);
    syncDefinitionDependency();

    const downloadBlob = (blob: Blob, filename: string) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    };

    const parseErrorMessage = async (response: Response) => {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            const payload = (await response.json().catch(() => null)) as {
                error?: string;
            } | null;
            if (payload?.error) {
                return payload.error;
            }
        }
        const text = await response.text().catch(() => '');
        return text || 'Operaci se nepodařilo dokončit.';
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form) {
            return;
        }
        if (!hasSelection()) {
            showFeedback('Vyberte alespoň jednu skupinu dat.', 'error');
            return;
        }
        if (currentMode === 'import' && !fileInput?.files?.length) {
            showFeedback('Vyberte SQL soubor k importu.', 'error');
            return;
        }
        if (currentMode === 'import' && !confirmInput?.checked) {
            showFeedback('Potvrďte přepsání dat před importem.', 'error');
            return;
        }
        const formData = new FormData(form);
        const endpoint =
            currentMode === 'import'
                ? buildAdminUrl('/admin/import')
                : buildAdminUrl('/admin/export');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': getCsrfToken(),
                },
            });
            if (!response.ok) {
                const message = await parseErrorMessage(response);
                showFeedback(message, 'error');
                return;
            }
            if (currentMode === 'export') {
                const blob = await response.blob();
                const disposition = response.headers.get('content-disposition');
                const match = disposition?.match(/filename="?([^";]+)"?/i);
                const filename =
                    match?.[1] || 'admin-export.sql';
                downloadBlob(blob, filename);
                showFeedback('Export byl úspěšně připraven ke stažení.', 'success');
            } else {
                const payload = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                showFeedback(
                    payload?.message || 'Import proběhl úspěšně.',
                    'success'
                );
            }
            closeModal();
        } catch {
            showFeedback('Operaci se nepodařilo dokončit.', 'error');
        }
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};
