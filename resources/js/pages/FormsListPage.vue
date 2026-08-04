<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import {
    FORM_USAGE_OPTIONS,
    FORM_USAGE_SECTION_ORDER_KEY,
    defaultFormUsageOrder,
    formUsageLabel,
    type FormUsageValue,
} from '@/lib/formUsage';

type FormRow = {
    id: number;
    name: string;
    slug: string;
    usage: string;
    usage_label?: string;
    published_version?: { version: number; status: string } | null;
    draft_version?: { version: number; status: string } | null;
};

const USAGE_CANONICAL: Record<string, FormUsageValue> = {
    service: 'service',
    article: 'article',
    inventory: 'inventory',
    routine: 'service',
    equipment: 'article',
    supply: 'inventory',
};

const { canWriteModule } = useModuleAccess();
const confirm = useConfirm();
const toast = useToast();
const router = useRouter();
const canWrite = computed(() => canWriteModule('design_forms'));

const forms = ref<FormRow[]>([]);
const loading = ref(true);
const name = ref('');
const usage = ref<FormUsageValue>('service');
const showCreate = ref(false);
const creating = ref(false);
const deletingId = ref<number | null>(null);

const sectionOrder = ref<FormUsageValue[]>(loadSectionOrder());
const collapsed = ref<Record<string, boolean>>(loadCollapsed());
const dragUsage = ref<FormUsageValue | null>(null);

function loadSectionOrder(): FormUsageValue[] {
    try {
        const raw = localStorage.getItem(FORM_USAGE_SECTION_ORDER_KEY);
        if (!raw) {
            return defaultFormUsageOrder();
        }
        const parsed = JSON.parse(raw) as string[];
        const valid = parsed.filter((u): u is FormUsageValue =>
            FORM_USAGE_OPTIONS.some((o) => o.value === u),
        );
        for (const opt of defaultFormUsageOrder()) {
            if (!valid.includes(opt)) {
                valid.push(opt);
            }
        }
        return valid.length ? valid : defaultFormUsageOrder();
    } catch {
        return defaultFormUsageOrder();
    }
}

function persistSectionOrder() {
    localStorage.setItem(FORM_USAGE_SECTION_ORDER_KEY, JSON.stringify(sectionOrder.value));
}

function loadCollapsed(): Record<string, boolean> {
    try {
        const raw = localStorage.getItem(`${FORM_USAGE_SECTION_ORDER_KEY}:collapsed`);
        return raw ? (JSON.parse(raw) as Record<string, boolean>) : {};
    } catch {
        return {};
    }
}

function persistCollapsed() {
    localStorage.setItem(`${FORM_USAGE_SECTION_ORDER_KEY}:collapsed`, JSON.stringify(collapsed.value));
}

function canonicalUsage(usageValue: string): FormUsageValue {
    return USAGE_CANONICAL[usageValue] ?? 'service';
}

const sections = computed(() => {
    const byUsage = new Map<FormUsageValue, FormRow[]>();
    for (const opt of FORM_USAGE_OPTIONS) {
        byUsage.set(opt.value, []);
    }
    for (const form of forms.value) {
        const key = canonicalUsage(form.usage);
        const list = byUsage.get(key) ?? [];
        list.push(form);
        byUsage.set(key, list);
    }
    for (const [, list] of byUsage) {
        list.sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
    }

    return sectionOrder.value.map((usageKey) => ({
        usage: usageKey,
        label: formUsageLabel(usageKey),
        forms: byUsage.get(usageKey) ?? [],
        collapsed: Boolean(collapsed.value[usageKey]),
    }));
});

function toggleSection(usageKey: FormUsageValue) {
    collapsed.value = { ...collapsed.value, [usageKey]: !collapsed.value[usageKey] };
    persistCollapsed();
}

function onDragStart(usageKey: FormUsageValue, event: DragEvent) {
    dragUsage.value = usageKey;
    event.dataTransfer?.setData('text/plain', usageKey);
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragOver(event: DragEvent) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDrop(targetUsage: FormUsageValue, event: DragEvent) {
    event.preventDefault();
    const source = dragUsage.value ?? (event.dataTransfer?.getData('text/plain') as FormUsageValue);
    dragUsage.value = null;
    if (!source || source === targetUsage) {
        return;
    }
    const next = [...sectionOrder.value];
    const from = next.indexOf(source);
    const to = next.indexOf(targetUsage);
    if (from < 0 || to < 0) {
        return;
    }
    next.splice(from, 1);
    next.splice(to, 0, source);
    sectionOrder.value = next;
    persistSectionOrder();
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: FormRow[] }>('/design/forms');
        forms.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    name.value = '';
    usage.value = 'service';
    showCreate.value = true;
}

async function createForm() {
    if (!name.value.trim()) {
        toast.warning('Indica el nombre del formulario.');
        return;
    }
    creating.value = true;
    try {
        await api('/design/forms', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim(), usage: usage.value }),
        });
        showCreate.value = false;
        toast.success('Formulario creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
    }
}

async function removeForm(row: FormRow) {
    const accepted = await confirm(
        `¿Eliminar el formulario «${row.name}»? Esta acción no se puede deshacer.`,
        { title: 'Eliminar formulario', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    deletingId.value = row.id;
    try {
        await api(`/design/forms/${row.id}`, { method: 'DELETE' });
        toast.success('Formulario eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deletingId.value = null;
    }
}

onMounted(load);
watch(sectionOrder, persistSectionOrder);
</script>

<template>
    <div class="portal-page" data-tour="page-design-forms">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Formularios"
                subtitle="Agrupados por tipo de formulario. Arrastra las secciones para reordenarlas; colapsa las que no uses."
            />
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <AppButton v-if="canWrite" type="button" @click="openCreate">
                    Nuevo formulario
                </AppButton>
                <RouterLink v-if="canWrite" to="/app/design/forms/settings">
                    <AppButton type="button" variant="secondary">Configuración de campos</AppButton>
                </RouterLink>
            </div>
        </div>

        <AppModal
            :open="showCreate && canWrite"
            title="Nuevo formulario"
            size="sm"
            @close="showCreate = false"
        >
            <form id="create-form-def" class="space-y-4" @submit.prevent="createForm">
                <MaterialField v-model="name" label="Nombre" required />
                <MaterialSelect
                    v-model="usage"
                    label="Tipo de formulario"
                    :options="[...FORM_USAGE_OPTIONS]"
                    required
                />
                <p class="text-portal-muted text-xs">
                    El tipo no se puede cambiar después. Servicio: tipos de servicio; Artículo/Inventario: fichas en catálogo.
                </p>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showCreate = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="create-form-def" :disabled="creating">
                    {{ creating ? 'Creando…' : 'Crear' }}
                </AppButton>
            </template>
        </AppModal>

        <ReadOnlyNotice v-if="!canWrite" module-label="Formularios" />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="space-y-4">
            <section
                v-for="section in sections"
                :key="section.usage"
                class="portal-list-panel overflow-hidden"
                @dragover="onDragOver"
                @drop="onDrop(section.usage, $event)"
            >
                <header
                    class="flex cursor-grab items-center gap-2 border-b border-[color:var(--portal-border)] px-4 py-3 active:cursor-grabbing"
                    draggable="true"
                    @dragstart="onDragStart(section.usage, $event)"
                >
                    <button
                        type="button"
                        class="text-portal-muted hover:text-portal-heading flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                        :aria-expanded="!section.collapsed"
                        :aria-label="section.collapsed ? 'Expandir sección' : 'Colapsar sección'"
                        @click="toggleSection(section.usage)"
                    >
                        <span class="text-xs">{{ section.collapsed ? '▸' : '▾' }}</span>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-portal-heading text-sm font-semibold">
                            {{ section.label }}
                            <span class="text-portal-muted ml-2 font-normal">({{ section.forms.length }})</span>
                        </h2>
                        <p class="text-portal-muted text-[11px]">Arrastra para cambiar el orden de las secciones</p>
                    </div>
                </header>
                <ul v-if="!section.collapsed" class="divide-y divide-[color:var(--portal-border)]">
                    <li
                        v-for="f in section.forms"
                        :key="f.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <RouterLink
                                class="text-portal-heading font-medium hover:text-amber-600"
                                :to="`/app/design/forms/${f.id}`"
                            >
                                {{ f.name }}
                            </RouterLink>
                            <p class="text-portal-muted text-xs">{{ f.slug }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <p v-if="f.published_version" class="text-portal-muted text-right text-xs">
                                En uso:
                                <span class="text-portal-heading font-medium"
                                    >v{{ f.published_version.version }} publicada</span
                                >
                            </p>
                            <p v-else class="text-right text-xs text-amber-500">Sin versión publicada</p>
                            <p v-if="f.draft_version" class="text-portal-muted text-right text-xs">
                                Borrador: v{{ f.draft_version.version }}
                            </p>
                            <div v-if="canWrite" class="table-row-actions justify-end">
                                <IconActionButton
                                    icon="pencil"
                                    label="Abrir diseñador de formulario"
                                    @click="router.push(`/app/design/forms/${f.id}`)"
                                />
                                <IconActionButton
                                    icon="trash"
                                    label="Eliminar formulario"
                                    variant="danger"
                                    :disabled="deletingId === f.id"
                                    @click="removeForm(f)"
                                />
                            </div>
                        </div>
                    </li>
                    <li v-if="section.forms.length === 0" class="text-portal-muted px-4 py-5 text-sm">
                        Sin formularios de este tipo.
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
