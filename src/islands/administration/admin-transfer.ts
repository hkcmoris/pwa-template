import { getCsrfToken } from '../../utils/api';

const buildAdminUrl = (path: string) => {
    const base = document.documentElement?.dataset?.base ?? '';
    return `${base}${path}`;
};

const updateSubmitState = (
    submitButton: HTMLButtonElement | null,
    mode: 'import' | 'export',
    fileInput?: HTMLInputElement | null,
    confirmInput?: HTMLInputElement | null,
    selectionActive = true
) => {
    if (!submitButton) {
        return;
    }
    if (mode === 'import') {
        submitButton.disabled =
            !fileInput?.files?.length ||
            !confirmInput?.checked ||
            !selectionActive;
    } else {
        submitButton.disabled = !selectionActive;
    }
};

export const initAdminTransfer = (root: HTMLElement) => {
    const modal = root.querySelector<HTMLElement>('#admin-transfer-modal');
    if (!modal) {
        return;
    }

    const title = modal.querySelector<HTMLHeadingElement>(
        '#admin-transfer-title'
    );
    const form = modal.querySelector<HTMLFormElement>('#admin-transfer-form');
    const dataFieldset = modal.querySelector<HTMLElement>('[data-admin-data]');
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
    const submitButton = modal.querySelector<HTMLButtonElement>(
        '[data-admin-submit]'
    );
    const focusTarget = modal.querySelector<HTMLInputElement>(
        'input[name="definitions"]'
    );
    const definitionsInput = modal.querySelector<HTMLInputElement>(
        'input[name="definitions"]'
    );
    const componentsInput = modal.querySelector<HTMLInputElement>(
        'input[name="components"]'
    );
    const pricesInput = modal.querySelector<HTMLInputElement>(
        'input[name="prices"]'
    );
    const usersInput = modal.querySelector<HTMLInputElement>(
        'input[name="users"]'
    );
    const feedback = root.querySelector<HTMLElement>('#admin-messages');

    const resultModal = root.querySelector<HTMLElement>(
        '#admin-import-result-modal'
    );
    const resultMessage = resultModal?.querySelector<HTMLElement>(
        '#admin-import-result-message'
    );

    let currentMode: 'import' | 'export' = 'export';

    const showFeedback = (message: string, status: 'success' | 'error') => {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.classList.remove('hidden');
        feedback.classList.toggle(
            'admin-feedback--success',
            status === 'success'
        );
        feedback.classList.toggle('admin-feedback--error', status === 'error');
    };

    const openResultModal = (message: string, status: 'success' | 'error') => {
        if (!resultModal || !resultMessage) {
            showFeedback(message, status);
            return;
        }
        resultMessage.textContent = message;
        resultMessage.classList.toggle(
            'admin-import-result-message--success',
            status === 'success'
        );
        resultMessage.classList.toggle(
            'admin-import-result-message--error',
            status === 'error'
        );
        resultModal.classList.remove('hidden');
        resultModal.setAttribute('aria-hidden', 'false');
    };

    const closeResultModal = () => {
        if (!resultModal || !resultMessage) {
            return;
        }
        resultModal.classList.add('hidden');
        resultModal.setAttribute('aria-hidden', 'true');
        resultMessage.textContent = '';
        resultMessage.classList.remove(
            'admin-import-result-message--success',
            'admin-import-result-message--error'
        );
    };

    const hideFeedback = () => {
        feedback?.classList.add('hidden');
        feedback?.classList.remove(
            'admin-feedback--success',
            'admin-feedback--error'
        );
        if (feedback) {
            feedback.textContent = '';
        }
    };

    const hasSelection = () =>
        Boolean(
            definitionsInput?.checked ||
            componentsInput?.checked ||
            pricesInput?.checked ||
            usersInput?.checked
        );

    const refreshSubmitState = () =>
        updateSubmitState(
            submitButton,
            currentMode,
            fileInput,
            confirmInput,
            hasSelection()
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

    const getAvailableTables = (sql: string) => {
        const tables = new Set<string>();
        const truncateRegex = /TRUNCATE\s+TABLE\s+`?([a-z0-9_]+)`?/gi;
        const insertRegex = /INSERT\s+INTO\s+`?([a-z0-9_]+)`?/gi;
        let match: RegExpExecArray | null;
        while ((match = truncateRegex.exec(sql)) !== null) {
            tables.add(match[1]);
        }
        while ((match = insertRegex.exec(sql)) !== null) {
            tables.add(match[1]);
        }
        return tables;
    };

    const updateOptionAvailability = (availableTables: Set<string>) => {
        const optionMapping = [
            { input: definitionsInput, tables: ['definitions'] },
            { input: componentsInput, tables: ['components'] },
            { input: pricesInput, tables: ['prices'] },
            { input: usersInput, tables: ['users'] },
        ];

        optionMapping.forEach(({ input, tables }) => {
            if (!input) {
                return;
            }
            const isAvailable = tables.some((table) =>
                availableTables.has(table)
            );
            input.disabled = !isAvailable;
            if (!isAvailable) {
                input.checked = false;
            } else {
                input.checked = true;
            }
        });
        syncDefinitionDependency();
    };

    const resetOptionState = () => {
        [definitionsInput, componentsInput, pricesInput, usersInput].forEach(
            (input) => {
                if (!input) {
                    return;
                }
                input.disabled = false;
                input.checked = true;
            }
        );
        syncDefinitionDependency();
    };

    const clearOptionState = () => {
        [definitionsInput, componentsInput, pricesInput, usersInput].forEach(
            (input) => {
                if (!input) {
                    return;
                }
                input.disabled = true;
                input.checked = false;
            }
        );
        syncDefinitionDependency();
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
        if (dataFieldset) {
            dataFieldset.classList.toggle('hidden', mode === 'import');
        }
        if (mode === 'export') {
            resetOptionState();
        } else {
            clearOptionState();
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
        updateSubmitState(
            submitButton,
            mode,
            fileInput,
            confirmInput,
            hasSelection()
        );
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

    modal
        .querySelectorAll<HTMLElement>('[data-admin-modal-close]')
        .forEach((button) => {
            button.addEventListener('click', () => closeModal());
        });

    resultModal
        ?.querySelectorAll<HTMLElement>('[data-admin-result-close]')
        .forEach((button) => {
            button.addEventListener('click', () => closeResultModal());
        });

    resultModal?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeResultModal();
        }
    });

    fileInput?.addEventListener('change', () => {
        refreshSubmitState();
        if (currentMode !== 'import') {
            return;
        }
        if (!fileInput.files?.length) {
            dataFieldset?.classList.add('hidden');
            return;
        }
        const [file] = fileInput.files;
        file.text()
            .then((contents) => {
                if (!contents.includes('-- HAGEMANN APP EXPORT v1')) {
                    showFeedback(
                        'Soubor není exportem této aplikace.',
                        'error'
                    );
                    dataFieldset?.classList.add('hidden');
                    return;
                }
                const tables = getAvailableTables(contents);
                if (tables.size === 0) {
                    showFeedback(
                        'SQL soubor neobsahuje žádné tabulky k importu.',
                        'error'
                    );
                    dataFieldset?.classList.add('hidden');
                    return;
                }
                updateOptionAvailability(tables);
                dataFieldset?.classList.remove('hidden');
                hideFeedback();
                refreshSubmitState();
            })
            .catch(() => {
                showFeedback('SQL soubor se nepodařilo načíst.', 'error');
                dataFieldset?.classList.add('hidden');
                refreshSubmitState();
            });
    });
    confirmInput?.addEventListener('change', () => {
        refreshSubmitState();
    });

    componentsInput?.addEventListener('change', () => {
        syncDefinitionDependency();
        refreshSubmitState();
    });
    definitionsInput?.addEventListener('change', refreshSubmitState);
    pricesInput?.addEventListener('change', refreshSubmitState);
    usersInput?.addEventListener('change', refreshSubmitState);
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
                if (currentMode === 'import') {
                    closeModal();
                    openResultModal(message, 'error');
                } else {
                    showFeedback(message, 'error');
                }
                return;
            }
            if (currentMode === 'export') {
                const blob = await response.blob();
                const disposition = response.headers.get('content-disposition');
                const match = disposition?.match(/filename="?([^";]+)"?/i);
                const filename = match?.[1] || 'admin-export.sql';
                downloadBlob(blob, filename);
                showFeedback(
                    'Export byl úspěšně připraven ke stažení.',
                    'success'
                );
            } else {
                const payload = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                const importMessage =
                    payload?.message || 'Import proběhl úspěšně.';
                closeModal();
                openResultModal(importMessage, 'success');
                return;
            }
            closeModal();
        } catch {
            if (currentMode === 'import') {
                closeModal();
                openResultModal('Operaci se nepodařilo dokončit.', 'error');
                return;
            }
            showFeedback('Operaci se nepodařilo dokončit.', 'error');
        }
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};
