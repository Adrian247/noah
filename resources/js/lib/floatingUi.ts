export type FloatingBox = {
    top: number;
    left: number;
};

/**
 * Coloca un panel flotante junto al ancla sin salirse del viewport.
 */
export function placeFloatingPanel(
    anchor: DOMRect,
    panelWidth: number,
    panelHeight: number,
    options: {
        margin?: number;
        prefer: 'right' | 'above' | 'below';
    },
): FloatingBox {
    const margin = options.margin ?? 12;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    let left = anchor.left;
    let top = anchor.top;

    if (options.prefer === 'right') {
        left = anchor.right + 10;
        top = anchor.top + anchor.height / 2 - panelHeight / 2;
    } else if (options.prefer === 'above') {
        left = anchor.left;
        top = anchor.top - panelHeight - 8;
        if (top < margin) {
            top = anchor.bottom + 8;
            if (top + panelHeight > vh - margin) {
                left = anchor.right + 10;
                top = anchor.top + anchor.height / 2 - panelHeight / 2;
            }
        }
    } else {
        left = anchor.left;
        top = anchor.bottom + 8;
        if (top + panelHeight > vh - margin) {
            top = anchor.top - panelHeight - 8;
        }
    }

    left = Math.min(Math.max(margin, left), vw - panelWidth - margin);
    top = Math.min(Math.max(margin, top), vh - panelHeight - margin);

    return { top, left };
}
