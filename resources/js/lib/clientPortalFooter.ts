import type { PortalPublicContent } from '@/composables/usePortalPublicContent';

const LOGIN_HELP_MARKERS = ['para entrar', 'olvidaste tu contraseña', 'no tienes acceso asignado'];

export function isLoginOrientedPortalHelp(content: PortalPublicContent | null | undefined): boolean {
    if (!content) {
        return true;
    }
    const title = (content.help_title ?? '').toLowerCase();
    const text = (content.help_text ?? '').toLowerCase();
    if (title.includes('para entrar') || title.includes('iniciar sesión')) {
        return true;
    }
    return LOGIN_HELP_MARKERS.some((m) => text.includes(m));
}

export type ClientPortalSessionFooter = {
    title: string;
    description: string | null;
    showDescription: boolean;
};

export function clientPortalSessionFooter(content: PortalPublicContent | null | undefined): ClientPortalSessionFooter {
    const loginHelp = isLoginOrientedPortalHelp(content);

    if (loginHelp) {
        return {
            title: 'Contacto con tu proveedor',
            description:
                'Para facturas, informes de servicio o dudas sobre tus equipos, usa los datos de contacto.',
            showDescription: true,
        };
    }

    return {
        title: content?.help_title?.trim() || 'Contacto y soporte',
        description: content?.help_text?.trim() || null,
        showDescription: Boolean(content?.help_text?.trim()),
    };
}
