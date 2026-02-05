const updateSubmitState = (
    submitButton: HTMLButtonElement | null,
    confirmInput: HTMLInputElement | null,
    mode: 'import' | 'export'
) => {
    if (!submitButton) {
        return;
    }
    if (mode === 'import') {
        submitButton.disabled = !confirmInput?.checked;
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

    let currentMode: 'import' | 'export' = 'export';

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
        if (confirmFieldset) {
            confirmFieldset.classList.toggle('hidden', mode !== 'import');
        }
        if (confirmInput) {
            confirmInput.checked = false;
            confirmInput.required = mode === 'import';
        }
        updateSubmitState(submitButton, confirmInput, mode);
        requestAnimationFrame(() => {
            focusTarget?.focus();
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

    confirmInput?.addEventListener('change', () => {
        updateSubmitState(submitButton, confirmInput, currentMode);
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        closeModal();
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};
