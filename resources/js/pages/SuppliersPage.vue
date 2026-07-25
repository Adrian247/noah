<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';

type Supplier = {
    id: number;
    code: string;
    name: string;
    contact_email?: string | null;
    contact_phone?: string | null;
};

const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('catalog_suppliers'));

const suppliers = ref<Supplier[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
const form = ref({ code: '', name: '', contact_email: '', contact_phone: '' });
const editingId = ref<number | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Supplier[] }>('/catalog/suppliers');
        suppliers.value = res.data;
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function save() {
    message.value = null;
    const body = {
        code: form.value.code,
        name: form.value.name,
        contact_email: form.value.contact_email || null,
        contact_phone: form.value.contact_phone || null,
    };
    try {
        if (editingId.value) {
            await api(`/catalog/suppliers/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/suppliers', { method: 'POST', body: JSON.stringify(body) });
        }
        form.value = { code: '', name: '', contact_email: '', contact_phone: '' };
        editingId.value = null;
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    }
}

function edit(s: Supplier) {
    editingId.value = s.id;
    form.value = {
        code: s.code,
        name: s.name,
        contact_email: s.contact_email ?? '',
        contact_phone: s.contact_phone ?? '',
    };
}

onMounted(load);
</script>

<template>
    <div class="max-w-4xl space-y-4">
        <h2 class="text-xl font-semibold">Proveedores</h2>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Código</th>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th v-if="canWrite" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in suppliers" :key="s.id" class="border-b border-slate-100">
                    <td class="py-2 font-mono text-xs">{{ s.code }}</td>
                    <td>{{ s.name }}</td>
                    <td>{{ s.contact_email ?? s.contact_phone ?? '—' }}</td>
                    <td v-if="canWrite" class="text-right">
                        <button type="button" class="text-sm underline" @click="edit(s)">Editar</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <form
            v-if="canWrite"
            class="grid gap-3 rounded-lg border bg-white p-4 text-sm sm:grid-cols-2"
            @submit.prevent="save"
        >
            <label class="block">
                Código
                <input v-model="form.code" required class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="block">
                Nombre
                <input v-model="form.name" required class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="block">
                Email
                <input v-model="form.contact_email" type="email" class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="block">
                Teléfono
                <input v-model="form.contact_phone" class="mt-1 w-full rounded-md border px-2 py-1.5" />
            </label>
            <button type="submit" class="w-fit rounded-md bg-slate-900 px-3 py-2 text-white sm:col-span-2">
                Guardar
            </button>
        </form>
        <ReadOnlyNotice v-else module-label="Proveedores" />
        <p v-if="message" class="text-sm text-red-600">{{ message }}</p>
    </div>
</template>
