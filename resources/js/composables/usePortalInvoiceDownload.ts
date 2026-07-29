import { ref } from 'vue';
import { getCompanyId, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';

export function usePortalInvoiceDownload() {
    const toast = useToast();
    const downloadingId = ref<number | null>(null);

    async function downloadInvoicePackage(invoiceId: number) {
        if (downloadingId.value !== null) {
            return;
        }
        downloadingId.value = invoiceId;
        try {
            const res = await fetch(`/api/v1/portal/invoices/${invoiceId}/download`, {
                headers: {
                    Authorization: `Bearer ${getToken()}`,
                    'X-Company-Id': getCompanyId() ?? '',
                    Accept: 'application/zip, application/octet-stream',
                },
            });
            if (!res.ok) {
                throw new Error('No se pudo descargar el paquete.');
            }
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const disposition = res.headers.get('Content-Disposition') ?? '';
            const match = disposition.match(/filename="?([^";]+)"?/);
            const filename = match?.[1] ?? `factura-${invoiceId}-paquete.zip`;
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
            toast.success('Descarga iniciada. El ZIP incluye PDF, evidencias y reportes adjuntos.');
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            downloadingId.value = null;
        }
    }

    return { downloadingId, downloadInvoicePackage };
}
