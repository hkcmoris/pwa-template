import { getCsrfToken } from '../../utils/api';

type LogoUploadOk = {
    message: string;
    path: string;
    width: number;
    height: number;
    updated_at: string;
};

function isLogoUploadOk(x: unknown): x is LogoUploadOk {
    if (typeof x !== 'object' || x === null) return false;

    const r = x as Record<string, unknown>;
    return (
        typeof r.path === 'string' &&
        typeof r.width === 'number' &&
        typeof r.height === 'number' &&
        typeof r.updated_at === 'string' &&
        typeof r.message === 'string'
    );
}

function getLogoUploadError(x: unknown): string | null {
    if (typeof x !== 'object' || x === null) return null;
    const r = x as Record<string, unknown>;
    return typeof r.error === 'string' ? r.error : null;
}

const buildAdminUrl = (path: string) => {
    const base = document.documentElement?.dataset?.base ?? '';
    return `${base}${path}`;
};

const updateSubmitState = (
    submitButton: HTMLButtonElement | null,
    fileInput?: HTMLInputElement | null
) => {
    if (!submitButton) {
        return;
    }

    submitButton.disabled = !fileInput?.files?.length;
};

export const initAdminLogo = (root: HTMLElement) => {
    const modal = root.querySelector<HTMLElement>('#admin-logo-modal');
    if (!modal) {
        return;
    }

    const title = modal.querySelector<HTMLHeadingElement>('#admin-logo-title');
    const form = modal.querySelector<HTMLFormElement>('#admin-logo-form');
    const dataFieldset = modal.querySelector<HTMLElement>('[data-admin-data]');
    // const fileFieldset = modal.querySelector<HTMLElement>('[data-admin-file]');
    const fileInput = modal.querySelector<HTMLInputElement>(
        'input[name="svg_file"]'
    );
    const logoPreview = modal.querySelector<HTMLElement>('.admin-logo-preview');
    const logoPreviewImg = modal.querySelector<HTMLImageElement>(
        '#admin-logo-preview-img'
    );
    const submitButton = modal.querySelector<HTMLButtonElement>(
        '[data-admin-submit]'
    );
    const feedback = root.querySelector<HTMLElement>('#admin-messages');

    const resultModal = root.querySelector<HTMLElement>(
        '#admin-import-result-modal'
    );
    const resultMessage = resultModal?.querySelector<HTMLElement>(
        '#admin-import-result-message'
    );

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
            'admin-logo-result-message--success',
            status === 'success'
        );
        resultMessage.classList.toggle(
            'admin-logo-result-message--error',
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
            'admin-logo-result-message--success',
            'admin-logo-result-message--error'
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

    const refreshSubmitState = () => updateSubmitState(submitButton, fileInput);

    const toNumber = (v: string): number => {
        if (!v) return 0;
        const s = v.trim();

        // Percent needs a viewport to resolve; treat as unknown
        if (s.endsWith('%')) return 0;

        const m = s.match(/-?\d+(\.\d+)?/);
        if (!m) return 0;

        const n = Number(m[0]);
        return Number.isFinite(n) ? n : 0;
    };

    const getAndClampLogoDimensions = (
        svg: string,
        maxW = 130,
        maxH = 30
    ): [number, number] => {
        const widthRegex = /<svg[^>]*\bwidth=["']([^"']+)["']/i;
        const heightRegex = /<svg[^>]*\bheight=["']([^"']+)["']/i;
        const viewBoxRegex =
            /\bviewBox=["']\s*[-0-9.]+\s+[-0-9.]+\s+([0-9.]+)\s+([0-9.]+)\s*["']/i;

        const wRaw = toNumber(widthRegex.exec(svg)?.[1] ?? '');
        const hRaw = toNumber(heightRegex.exec(svg)?.[1] ?? '');

        let w = wRaw;
        let h = hRaw;

        if (!(w > 0 && h > 0)) {
            const vb = viewBoxRegex.exec(svg);
            if (vb) {
                w = toNumber(vb[1]);
                h = toNumber(vb[2]);
            }
        }

        // Still nothing? Give a sane default so UI doesn't explode
        if (!(w > 0 && h > 0)) return [maxW, maxH];

        // Contain within max box, preserve aspect ratio
        const scale = Math.min(maxW / w, maxH / h, 1); // never upscale
        const cw = Math.round(w * scale);
        const ch = Math.round(h * scale);

        return [cw, ch];
    };

    let lastOpener: HTMLElement | null = null;

    const openModal = (opener?: HTMLElement) => {
        lastOpener = opener ?? (document.activeElement as HTMLElement | null);

        modal.classList.remove('hidden');
        modal.removeAttribute('inert');
        modal.setAttribute('aria-hidden', 'false');

        const firstFocusable = modal.querySelector<HTMLElement>(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        firstFocusable?.focus();
        
        if (title) {
            title.textContent = 'Nahrání loga';
        }
        if (submitButton) {
            submitButton.textContent = 'Nahrát logo';
        }
        if (fileInput) {
            fileInput.value = '';
            fileInput.required = true;
        }
        updateSubmitState(submitButton, fileInput);
        hideFeedback();
        requestAnimationFrame(() => {
            fileInput?.click();
        });
    };

    const closeModal = () => {
        const active = document.activeElement as HTMLElement | null;
        if (active && modal.contains(active)) {
            (lastOpener ?? document.body).focus?.();
        }

        modal.classList.add('hidden');
        modal.setAttribute('inert', '');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('data-mode');

        if (!logoPreview) {
            return;
        }

        logoPreview.classList.add('hidden');
    };

    root.querySelectorAll<HTMLButtonElement>('[data-admin-modal-logo]').forEach(
        (button) => {
            button.addEventListener('click', () => {
                openModal(button);
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
        if (!fileInput.files?.length) {
            dataFieldset?.classList.add('hidden');
            return;
        }
        const [file] = fileInput.files;
        file.text()
            .then((contents) => {
                // TODO: read logo dimensions in php and store keys in the app_settings table in database.
                if (logoPreview && logoPreviewImg) {
                    const [w, h] = getAndClampLogoDimensions(contents, 130, 30);

                    const blob = new Blob([contents], {
                        type: 'image/svg+xml',
                    });
                    const url = URL.createObjectURL(blob);
                    logoPreviewImg.src = url;

                    // Optional: force exact clamped render size
                    logoPreviewImg.width = w;
                    logoPreviewImg.height = h;

                    logoPreviewImg.onload = () => URL.revokeObjectURL(url);
                    logoPreview.classList.remove('hidden');
                }
                dataFieldset?.classList.remove('hidden');
                hideFeedback();
                refreshSubmitState();
            })
            .catch(() => {
                showFeedback('SVG soubor se nepodařilo načíst.', 'error');
                dataFieldset?.classList.add('hidden');
                refreshSubmitState();
            });
    });

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

    function updateLogoInLayout(payload: {
        path: string;
        width: number;
        height: number;
        updated_at: string;
    }) {
        const base = document.documentElement?.dataset?.base ?? '';
        const url = `${base}${payload.path}?v=${encodeURIComponent(payload.updated_at)}`;

        // pick whatever you use
        const img = document.querySelector<HTMLImageElement>('[data-app-logo]');
        if (!img) return;

        img.src = url;
        img.width = Math.round(payload.width);
        img.height = Math.round(payload.height);

        // if you also want to update any link preload etc, you can do that too
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form) {
            return;
        }
        if (!fileInput?.files?.length) {
            showFeedback('Vyberte SVG soubor k nahrání.', 'error');
            return;
        }
        const formData = new FormData(form);
        const endpoint = buildAdminUrl('/admin/logo');
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
                closeModal();
                openResultModal(message, 'error');
                return;
            }
            const payload = (await response
                .json()
                .catch(() => null)) as unknown;
            if (isLogoUploadOk(payload)) {
                updateLogoInLayout(payload);
                closeModal();
                openResultModal(payload.message, 'success');
                return;
            }

            const err = getLogoUploadError(payload);
            closeModal();
            openResultModal(
                err ?? 'Nahrání loga proběhlo úspěšně.',
                err ? 'error' : 'success'
            );
            return;
        } catch {
            closeModal();
            openResultModal('Operaci se nepodařilo dokončit.', 'error');
            return;
        }
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};
