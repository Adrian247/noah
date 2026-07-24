<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';

type Invoice = {
    id: number;
    status: string;
    total: string;
    routine_id?: number;
};

const invoices = ref<Invoice[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await api<{ data: Invoice[] }>('/billing/invoices');
        invoices.value = res.data;
    } finally {
        loading.value = false;
    }
});

async function issue(id: number) {
    await api(`/billing/invoices/${id}/issue`, { method: 'POST' });
    const res = await api<{ data: Invoice[] }>('/billing/invoices');
    invoices.value = res.data;
}
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold">Facturación</h2>
        <p v-if="loading" class="mt-4 text-slate-500">Cargando…</p>
        <table v-else class="mt-4 w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">ID</th>
                    <th>Rutina</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="inv in invoices" :key="inv.id" class="border-b">
                    <td class="py-2">{{ inv.id }}</td>
                    <td>{{ inv.routine_id ?? '—' }}</td>
                    <td>{{ inv.status }}</td>
                    <td>${{ inv.total }}</td>
                    <td>
                        <button
                            v-if="inv.status === 'draft'"
                            type="button"
                            class="text-slate-900 underline"
                            @click="issue(inv.id)"
                        >
                            Emitir
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
