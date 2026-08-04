<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AutomationRuleBuilder, {
    type AutomationAction,
    type AutomationCondition,
} from '@/components/integrations/AutomationRuleBuilder.vue';
import { integrationsSectionNav } from '@/lib/sectionNav';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

const EVENT_OPTIONS = [
    'routine.validated',
    'routine.rejected',
    'invoice.issued',
    'inventory.low_stock',
];

type AutomationRule = {
    id: number;
    name: string;
    trigger_type: string;
    conditions?: Record<string, unknown> | null;
    actions: { type: string; event?: string; message?: string }[];
    is_active: boolean;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('integrations'));

const loading = ref(true);
const rules = ref<AutomationRule[]>([]);
const showRuleForm = ref(false);
const ruleForm = ref({
    name: '',
    trigger_type: 'routine.validated',
    is_active: true,
});
const ruleConditions = ref<AutomationCondition[]>([]);
const ruleActions = ref<AutomationAction[]>([{ type: 'log', message: 'Regla ejecutada' }]);
const ruleBuilderRef = ref<InstanceType<typeof AutomationRuleBuilder> | null>(null);
const editingRuleId = ref<number | null>(null);

const ruleColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'name', label: 'Nombre' },
        { id: 'trigger', label: 'Disparador' },
        { id: 'action_summary', label: 'Acciones' },
        { id: 'active', label: 'Activa' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

async function load() {
    loading.value = true;
    try {
        const rl = await api<{ data: AutomationRule[] }>('/automation/rules');
        rules.value = rl.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function resetRuleForm() {
    ruleForm.value = {
        name: '',
        trigger_type: 'routine.validated',
        is_active: true,
    };
    ruleConditions.value = [];
    ruleActions.value = [{ type: 'log', message: 'Regla ejecutada' }];
    editingRuleId.value = null;
}

function openRuleCreate() {
    resetRuleForm();
    showRuleForm.value = true;
}

function openRuleEdit(row: AutomationRule) {
    editingRuleId.value = row.id;
    ruleForm.value = {
        name: row.name,
        trigger_type: row.trigger_type,
        is_active: row.is_active,
    };
    ruleConditions.value = conditionsToBuilder(row.conditions);
    ruleActions.value = row.actions.length
        ? row.actions.map((action) => ({
              type: action.type as 'log' | 'webhook',
              message: action.message,
              event: action.event,
          }))
        : [{ type: 'log', message: 'Regla ejecutada' }];
    showRuleForm.value = true;
}

function conditionsToBuilder(conditions?: Record<string, unknown> | null): AutomationCondition[] {
    if (!conditions) {
        return [];
    }

    return Object.entries(conditions).map(([field, value]) => {
        if (value !== null && typeof value === 'object' && 'min' in (value as Record<string, unknown>)) {
            return {
                field,
                operator: 'min' as const,
                value: String((value as { min: number }).min),
            };
        }

        return { field, operator: 'eq' as const, value: String(value) };
    });
}

async function saveRule() {
    const conditions = ruleBuilderRef.value?.toApiConditions() ?? null;
    const actions = ruleBuilderRef.value?.toApiActions() ?? [];
    if (actions.length === 0) {
        toast.error('Agrega al menos una acción.');
        return;
    }

    const body = {
        name: ruleForm.value.name,
        trigger_type: ruleForm.value.trigger_type,
        conditions,
        actions,
        is_active: ruleForm.value.is_active,
    };

    try {
        if (editingRuleId.value) {
            await api(`/automation/rules/${editingRuleId.value}`, {
                method: 'PUT',
                body: JSON.stringify(body),
            });
        } else {
            await api('/automation/rules', { method: 'POST', body: JSON.stringify(body) });
        }
        showRuleForm.value = false;
        toast.success(editingRuleId.value ? 'Regla actualizada.' : 'Regla creada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function deleteRule(row: AutomationRule) {
    try {
        await api(`/automation/rules/${row.id}`, { method: 'DELETE' });
        toast.success('Regla eliminada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-integrations-automation">
        <SectionSubnav :items="integrationsSectionNav" />
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Automatización"
                subtitle="Reglas que reaccionan a eventos operativos (log o webhook)."
            />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openRuleCreate">
                Nueva regla
            </AppButton>
        </div>

        <ReadOnlyNotice v-if="!canWrite" module-label="Integraciones" />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="integrations-automation"
            :columns="ruleColumns"
            :rows="rules"
            row-key="id"
            empty-text="Sin reglas de automatización."
        >
            <template #name="{ row }">
                <span class="text-portal-heading">{{ (row as AutomationRule).name }}</span>
            </template>
            <template #trigger="{ row }">
                <span class="font-mono text-xs">{{ (row as AutomationRule).trigger_type }}</span>
            </template>
            <template #action_summary="{ row }">
                <span class="text-portal-muted text-xs">{{ (row as AutomationRule).actions.length }} acción(es)</span>
            </template>
            <template #active="{ row }">
                {{ (row as AutomationRule).is_active ? 'Sí' : 'No' }}
            </template>
            <template #actions="{ row }">
                <IconActionButton icon="pencil" label="Editar" @click="openRuleEdit(row as AutomationRule)" />
                <IconActionButton icon="trash" label="Eliminar" @click="deleteRule(row as AutomationRule)" />
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showRuleForm && canWrite"
            :title="editingRuleId ? 'Editar regla' : 'Nueva regla'"
            size="lg"
            @close="showRuleForm = false"
        >
            <form id="rule-form" class="grid gap-4" @submit.prevent="saveRule">
                <MaterialField v-model="ruleForm.name" label="Nombre" required />
                <label class="text-portal-heading block text-sm">
                    Disparador
                    <select v-model="ruleForm.trigger_type" class="field-input mt-1 w-full">
                        <option v-for="ev in EVENT_OPTIONS" :key="ev" :value="ev">
                            {{ ev }}
                        </option>
                    </select>
                </label>
                <label class="text-portal-muted flex items-center gap-2 text-sm">
                    <input v-model="ruleForm.is_active" type="checkbox" />
                    Activa
                </label>
                <AutomationRuleBuilder
                    ref="ruleBuilderRef"
                    :trigger-type="ruleForm.trigger_type"
                    :conditions="ruleConditions"
                    :actions="ruleActions"
                    @update:conditions="ruleConditions = $event"
                    @update:actions="ruleActions = $event"
                />
            </form>
            <template #footer>
                <AppButton type="button" variant="ghost" @click="showRuleForm = false">Cancelar</AppButton>
                <AppButton type="submit" form="rule-form">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
