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
import AutomationRuleBuilder, {
    type AutomationAction,
    type AutomationCondition,
} from '@/components/integrations/AutomationRuleBuilder.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

const EVENT_OPTIONS = [
    'routine.validated',
    'routine.rejected',
    'invoice.issued',
    'inventory.low_stock',
    '*',
];

type Webhook = {
    id: number;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
    last_delivered_at?: string | null;
    last_status?: string | null;
    secret?: string;
};

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

const tab = ref<'webhooks' | 'automation'>('webhooks');
const loading = ref(true);
const webhooks = ref<Webhook[]>([]);
const rules = ref<AutomationRule[]>([]);

const showWebhookForm = ref(false);
const webhookForm = ref({ name: '', url: '', events: ['routine.validated'] as string[], is_active: true });
const editingWebhookId = ref<number | null>(null);
const createdSecret = ref<string | null>(null);

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
const testingWebhookId = ref<number | null>(null);

const webhookColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'name', label: 'Nombre' },
        { id: 'url', label: 'URL' },
        { id: 'events', label: 'Eventos' },
        { id: 'status', label: 'Estado' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

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
        const [wh, rl] = await Promise.all([
            api<{ data: Webhook[] }>('/integrations/webhooks'),
            api<{ data: AutomationRule[] }>('/automation/rules'),
        ]);
        webhooks.value = wh.data;
        rules.value = rl.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function resetWebhookForm() {
    webhookForm.value = { name: '', url: '', events: ['routine.validated'], is_active: true };
    editingWebhookId.value = null;
    createdSecret.value = null;
}

function openWebhookCreate() {
    resetWebhookForm();
    showWebhookForm.value = true;
}

function openWebhookEdit(row: Webhook) {
    editingWebhookId.value = row.id;
    webhookForm.value = {
        name: row.name,
        url: row.url,
        events: [...row.events],
        is_active: row.is_active,
    };
    showWebhookForm.value = true;
}

function toggleWebhookEvent(event: string) {
    const set = new Set(webhookForm.value.events);
    if (set.has(event)) {
        set.delete(event);
    } else {
        set.add(event);
    }
    webhookForm.value.events = [...set];
}

async function saveWebhook() {
    const body = {
        name: webhookForm.value.name,
        url: webhookForm.value.url,
        events: webhookForm.value.events,
        is_active: webhookForm.value.is_active,
    };
    try {
        if (editingWebhookId.value) {
            await api(`/integrations/webhooks/${editingWebhookId.value}`, {
                method: 'PUT',
                body: JSON.stringify(body),
            });
            toast.success('Webhook actualizado.');
            showWebhookForm.value = false;
        } else {
            const res = await api<{ data: Webhook }>('/integrations/webhooks', {
                method: 'POST',
                body: JSON.stringify(body),
            });
            createdSecret.value = res.data.secret ?? null;
            toast.success('Webhook creado.');
            if (!createdSecret.value) {
                showWebhookForm.value = false;
            }
        }
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function deleteWebhook(row: Webhook) {
    try {
        await api(`/integrations/webhooks/${row.id}`, { method: 'DELETE' });
        toast.success('Webhook eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function testWebhook(row: Webhook) {
    testingWebhookId.value = row.id;
    try {
        const res = await api<{ data: { success: boolean; status: string; http_status?: number } }>(
            `/integrations/webhooks/${row.id}/test`,
            { method: 'POST' },
        );
        if (res.data.success) {
            toast.success(`Prueba enviada (${res.data.status}).`);
        } else {
            const detail = res.data.http_status
                ? `${res.data.status} (HTTP ${res.data.http_status})`
                : res.data.status;
            toast.error(`Prueba fallida: ${detail}`);
        }
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        testingWebhookId.value = null;
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
    <div class="portal-page" data-tour="page-integrations">
        <PageHeader
            title="Integraciones"
            subtitle="Webhooks salientes y reglas de automatización operativa."
        />

        <div class="mb-4 flex flex-wrap gap-2">
            <AppButton
                type="button"
                :variant="tab === 'webhooks' ? 'primary' : 'secondary'"
                @click="tab = 'webhooks'"
            >
                Webhooks
            </AppButton>
            <AppButton
                type="button"
                :variant="tab === 'automation' ? 'primary' : 'secondary'"
                @click="tab = 'automation'"
            >
                Automatización
            </AppButton>
        </div>

        <p v-if="loading" class="text-portal-muted">Cargandoâ€¦</p>

        <template v-else-if="tab === 'webhooks'">
            <div class="mb-3 flex justify-end">
                <AppButton v-if="canWrite" type="button" @click="openWebhookCreate">Nuevo webhook</AppButton>
            </div>
            <ConfigurableDataTable
                table-id="integrations-webhooks"
                :columns="webhookColumns"
                :rows="webhooks"
                row-key="id"
                empty-text="Sin webhooks configurados."
            >
                <template #name="{ row }">
                    <span class="text-portal-heading">{{ (row as Webhook).name }}</span>
                </template>
                <template #url="{ row }">
                    <span class="text-portal-muted font-mono text-xs">{{ (row as Webhook).url }}</span>
                </template>
                <template #events="{ row }">
                    <span class="text-portal-muted text-xs">{{ (row as Webhook).events.join(', ') }}</span>
                </template>
                <template #status="{ row }">
                    <span class="text-portal-muted text-xs">
                        {{ (row as Webhook).is_active ? 'Activo' : 'Inactivo' }}
                        <span v-if="(row as Webhook).last_status"> · {{ (row as Webhook).last_status }}</span>
                    </span>
                </template>
                <template #actions="{ row }">
                    <IconActionButton
                        icon="send"
                        label="Probar webhook"
                        :disabled="testingWebhookId === (row as Webhook).id"
                        @click="testWebhook(row as Webhook)"
                    />
                    <IconActionButton icon="pencil" label="Editar" @click="openWebhookEdit(row as Webhook)" />
                    <IconActionButton icon="trash" label="Eliminar" @click="deleteWebhook(row as Webhook)" />
                </template>
            </ConfigurableDataTable>
        </template>

        <template v-else>
            <div class="mb-3 flex justify-end">
                <AppButton v-if="canWrite" type="button" @click="openRuleCreate">Nueva regla</AppButton>
            </div>
            <ConfigurableDataTable
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
        </template>

        <ReadOnlyNotice v-if="!loading && !canWrite" module-label="Integraciones" />

        <AppModal
            :open="showWebhookForm && canWrite"
            :title="editingWebhookId ? 'Editar webhook' : 'Nuevo webhook'"
            size="md"
            @close="showWebhookForm = false"
        >
            <form id="webhook-form" class="grid gap-4" @submit.prevent="saveWebhook">
                <MaterialField v-model="webhookForm.name" label="Nombre" required />
                <MaterialField v-model="webhookForm.url" label="URL destino" type="url" required />
                <p class="text-portal-muted text-xs">
                    Slack Incoming Webhook (<code class="font-mono">hooks.slack.com</code>) se formatea
                    automáticamente. Otros destinos reciben el JSON estándar de Phoenix.
                </p>
                <fieldset class="space-y-2">
                    <legend class="text-portal-heading text-sm font-medium">Eventos</legend>
                    <label
                        v-for="ev in EVENT_OPTIONS"
                        :key="ev"
                        class="text-portal-muted flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="webhookForm.events.includes(ev)"
                            @change="toggleWebhookEvent(ev)"
                        />
                        <span class="font-mono text-xs">{{ ev }}</span>
                    </label>
                </fieldset>
                <label class="text-portal-muted flex items-center gap-2 text-sm">
                    <input v-model="webhookForm.is_active" type="checkbox" />
                    Activo
                </label>
                <p v-if="createdSecret" class="portal-callout portal-callout--info text-xs">
                    Guarda el secreto HMAC (solo se muestra una vez):
                    <code class="mt-1 block break-all">{{ createdSecret }}</code>
                </p>
            </form>
            <template #footer>
                <AppButton type="button" variant="ghost" @click="showWebhookForm = false">Cerrar</AppButton>
                <AppButton type="submit" form="webhook-form">Guardar</AppButton>
            </template>
        </AppModal>

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
                        <option v-for="ev in EVENT_OPTIONS.filter((e) => e !== '*')" :key="ev" :value="ev">
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
