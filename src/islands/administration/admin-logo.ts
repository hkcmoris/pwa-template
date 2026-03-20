import { getCsrfToken } from '../../utils/api';

type LogoVariant = {
    path?: string;
    width?: number;
    height?: number;
    updated_at?: string;
    url?: string;
};

type LogoUploadOk = {
    message?: string;
    logos?: {
        light?: LogoVariant;
        dark?: LogoVariant;
        pdf?: LogoVariant;
        watermark?: LogoVariant;
        has_dark_logo?: boolean;
    };
    error?: string;
};

const buildAdminUrl = (path: string) => {
    const base = document.documentElement?.dataset?.base ?? '';
    return `${base}${path}`;
};

const toPositiveInt = (value: unknown, fallback = 0) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return fallback;
    }
    return Math.round(parsed);
};

const applyHeaderLogoFromDataset = () => {
    const root = document.documentElement;
    const headerLogo = document.querySelector<HTMLImageElement>('[data-app-logo]');
    if (!headerLogo) {
        return;
    }

    const theme = root.dataset.theme === 'dark' ? 'dark' : 'light';
    const hasDark = root.dataset.logoHasDark === '1';

    const lightSrc = root.dataset.logoLightSrc ?? '';
    const darkSrc = root.dataset.logoDarkSrc ?? '';
    const lightWidth = toPositiveInt(root.dataset.logoLightWidth, headerLogo.width);
    const lightHeight = toPositiveInt(
        root.dataset.logoLightHeight,
        headerLogo.height
    );
    const darkWidth = toPositiveInt(root.dataset.logoDarkWidth, lightWidth);
    const darkHeight = toPositiveInt(root.dataset.logoDarkHeight, lightHeight);

    const nextSrc = theme === 'dark' && hasDark && darkSrc ? darkSrc : lightSrc;
    const nextWidth = theme === 'dark' && hasDark ? darkWidth : lightWidth;
    const nextHeight = theme === 'dark' && hasDark ? darkHeight : lightHeight;

    if (nextSrc && headerLogo.getAttribute('src') !== nextSrc) {
        headerLogo.setAttribute('src', nextSrc);
    }
    if (nextWidth > 0) {
        headerLogo.width = nextWidth;
    }
    if (nextHeight > 0) {
        headerLogo.height = nextHeight;
    }
};

export const initAdminLogo = (root: HTMLElement) => {
    const form = root.querySelector<HTMLFormElement>('#admin-logo-form');
    if (!form) {
        return;
    }

    const feedback = root.querySelector<HTMLElement>('#admin-logo-feedback');
    const submitButton = form.querySelector<HTMLButtonElement>(
        '[data-admin-logo-submit]'
    );

    const lightInput = form.querySelector<HTMLInputElement>(
        'input[name="logo_light_svg"]'
    );
    const darkInput = form.querySelector<HTMLInputElement>(
        'input[name="logo_dark_svg"]'
    );
    const pdfInput = form.querySelector<HTMLInputElement>(
        'input[name="logo_pdf_svg"]'
    );
    const watermarkInput = form.querySelector<HTMLInputElement>(
        'input[name="watermark_tile_svg"]'
    );

    const lightPreview = root.querySelector<HTMLImageElement>(
        '#admin-logo-current-light'
    );
    const darkPreview = root.querySelector<HTMLImageElement>(
        '#admin-logo-current-dark'
    );
    const pdfPreview = root.querySelector<HTMLImageElement>(
        '#admin-logo-current-pdf'
    );
    const watermarkPreview = root.querySelector<HTMLImageElement>(
        '#admin-logo-current-watermark'
    );

    const darkFallbackHint = root.querySelector<HTMLElement>(
        '#admin-logo-dark-fallback-hint'
    );
    const pdfFallbackHint = root.querySelector<HTMLElement>(
        '#admin-logo-pdf-fallback-hint'
    );
    const watermarkFallbackHint = root.querySelector<HTMLElement>(
        '#admin-logo-watermark-fallback-hint'
    );

    const tempObjectUrls = new Set<string>();
    const makeTemporaryPreview = (
        input: HTMLInputElement | null,
        image: HTMLImageElement | null
    ) => {
        if (!input || !image || !input.files?.length) {
            return;
        }
        const file = input.files[0];
        const objectUrl = URL.createObjectURL(file);
        tempObjectUrls.add(objectUrl);
        image.src = objectUrl;
    };

    const cleanupObjectUrls = () => {
        tempObjectUrls.forEach((url) => URL.revokeObjectURL(url));
        tempObjectUrls.clear();
    };

    const hasAnyFile = () =>
        Boolean(
            lightInput?.files?.length ||
                darkInput?.files?.length ||
                pdfInput?.files?.length ||
                watermarkInput?.files?.length
        );

    const setSubmitState = () => {
        if (!submitButton) {
            return;
        }
        submitButton.disabled = !hasAnyFile();
    };

    const showFeedback = (message: string, kind: 'success' | 'error') => {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.classList.remove('hidden');
        feedback.classList.toggle('admin-address-feedback--success', kind === 'success');
        feedback.classList.toggle('admin-address-feedback--error', kind === 'error');
    };

    const hideFeedback = () => {
        if (!feedback) {
            return;
        }
        feedback.textContent = '';
        feedback.classList.add('hidden');
        feedback.classList.remove(
            'admin-address-feedback--success',
            'admin-address-feedback--error'
        );
    };

    const applyResponse = (payload: LogoUploadOk) => {
        const logos = payload.logos;
        if (!logos) {
            return;
        }

        const light = logos.light;
        const dark = logos.dark;
        const pdf = logos.pdf;
        const watermark = logos.watermark;
        const hasDarkLogo = Boolean(logos.has_dark_logo && dark?.url);
        const hasPdfLogo = Boolean(pdf?.url);
        const hasCustomWatermark = Boolean(watermark?.url);

        if (light?.url && lightPreview) {
            lightPreview.src = light.url;
        }

        if (darkPreview) {
            if (hasDarkLogo && dark?.url) {
                darkPreview.src = dark.url;
                darkPreview.classList.remove('admin-logo-preview-image--fallback-invert');
            } else if (light?.url) {
                darkPreview.src = light.url;
                darkPreview.classList.add('admin-logo-preview-image--fallback-invert');
            }
        }

        if (pdfPreview) {
            if (hasPdfLogo && pdf?.url) {
                pdfPreview.src = pdf.url;
            } else if (light?.url) {
                pdfPreview.src = light.url;
            }
        }

        if (watermarkPreview) {
            if (hasCustomWatermark && watermark?.url) {
                watermarkPreview.src = watermark.url;
            }
        }

        darkFallbackHint?.classList.toggle('hidden', hasDarkLogo);
        pdfFallbackHint?.classList.toggle('hidden', hasPdfLogo);
        watermarkFallbackHint?.classList.toggle('hidden', hasCustomWatermark);

        const doc = document.documentElement;
        if (light?.url) {
            doc.dataset.logoLightSrc = light.url;
            doc.dataset.logoLightWidth = String(
                toPositiveInt(light.width, toPositiveInt(doc.dataset.logoLightWidth, 130))
            );
            doc.dataset.logoLightHeight = String(
                toPositiveInt(light.height, toPositiveInt(doc.dataset.logoLightHeight, 30))
            );
        }
        doc.dataset.logoHasDark = hasDarkLogo ? '1' : '0';
        if (hasDarkLogo && dark?.url) {
            doc.dataset.logoDarkSrc = dark.url;
            doc.dataset.logoDarkWidth = String(
                toPositiveInt(dark.width, toPositiveInt(doc.dataset.logoDarkWidth, 130))
            );
            doc.dataset.logoDarkHeight = String(
                toPositiveInt(dark.height, toPositiveInt(doc.dataset.logoDarkHeight, 30))
            );
        } else {
            delete doc.dataset.logoDarkSrc;
            delete doc.dataset.logoDarkWidth;
            delete doc.dataset.logoDarkHeight;
        }

        applyHeaderLogoFromDataset();
    };

    const bindPreview = (
        input: HTMLInputElement | null,
        image: HTMLImageElement | null
    ) => {
        if (!input) {
            return;
        }
        input.addEventListener('change', () => {
            hideFeedback();
            setSubmitState();
            makeTemporaryPreview(input, image);
        });
    };

    bindPreview(lightInput, lightPreview);
    bindPreview(darkInput, darkPreview);
    bindPreview(pdfInput, pdfPreview);
    bindPreview(watermarkInput, watermarkPreview);
    setSubmitState();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideFeedback();

        if (!hasAnyFile()) {
            showFeedback('Vyberte alespoň jeden SVG soubor k nahrání.', 'error');
            return;
        }

        const formData = new FormData(form);
        const endpoint = buildAdminUrl('/admin/logo');

        submitButton?.setAttribute('disabled', 'true');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': getCsrfToken(),
                },
            });

            const payload = (await response
                .json()
                .catch(() => null)) as LogoUploadOk | null;
            if (!response.ok) {
                showFeedback(
                    payload?.error ?? 'Nahrání souborů se nezdařilo.',
                    'error'
                );
                return;
            }

            applyResponse(payload ?? {});
            showFeedback(payload?.message ?? 'Soubory byly úspěšně nahrány.', 'success');

            form.reset();
            cleanupObjectUrls();
            setSubmitState();
        } catch {
            showFeedback('Nahrání souborů se nezdařilo.', 'error');
        } finally {
            submitButton?.removeAttribute('disabled');
        }
    });
};
