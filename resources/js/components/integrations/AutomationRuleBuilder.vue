<script setup lang="ts">
import { computed } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

export type AutomationCondition = {
    field: string;
    operator: 'eq' | 'min';
    value: string;
};

export type AutomationAction = {
    type: 'log' | 'webhook';
    message?: string;
    event?: string;
};

const props = defineProps<{
    triggerType: string;
    conditions: AutomationCondition[];
    actions: AutomationAction[];
}>();

const emit = defineEmits<{
    'update:conditions': [AutomationCondition[]];
    'update:actions': [AutomationAction[]];
}>();

const FIELD_OPTIONS = [
    { value: 'status', label: 'Estado (contexto)' },
    { value: 'routine_id', label: 'ID servicio' },
    { value: 'invoice_id', label: 'ID factura' },
    { value: 'total', label: 'Total factura' },
];

const localConditions = computed({
    get: () => props.conditions,
    set: (value) => emit('update:conditions', value),
});

const localActions = computed({
    get: () => props.actions,
    set: (value) => emit('update:actions', value),
});

function addCondition() {
    localConditions.value = [...localConditions.value, { field: 'status', operator: 'eq', value: '' }];
}

function removeCondition(index: number) {
    localConditions.value = localConditions.value.filter((_, i) => i !== index);
}

function addAction() {
    localActions.value = [...localActions.value, { type: 'log', message: 'Regla ejecutada' }];
}

function removeAction(index: number) {
    localActions.value = localActions.value.filter((_, i) => i !== index);
}

function toApiConditions(): Record<string, unknown> | null {
    if (localConditions.value.length === 0) {
        return null;
    }

    const out: Record<string, unknown> = {};
    for (const condition of localConditions.value) {
        if (!condition.field || condition.value === '') {
            continue;
        }
        if (condition.operator === 'min') {
            out[condition.field] = { min: Number(condition.value) };
        } else {
            out[condition.field] = condition.value;
        }
    }

    return Object.keys(out).length === 0 ? null : out;
}

function toApiActions(): AutomationAction[] {
    return localActions.value
        .filter((action) => action.type === 'log' || action.type === 'webhook')
        .map((action) => {
            if (action.type === 'webhook') {
                return {
                    type: 'webhook',
                    event: action.event?.trim() || props.triggerType,
                };
            }

            return {
                type: 'log',
                message: action.message?.trim() || 'Regla ejecutada',
            };
        });
}

defineExpose({ toApiConditions, toApiActions });
</script>

<template>
    <div class="grid gap-5">
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-portal-heading text-sm font-medium">Condiciones (opcional)</h3>
                <AppButton type="button" variant="secondary" @click="addCondition">Añadir condición</AppButton>
            </div>
            <p v-if="localConditions.length === 0" class="text-portal-muted text-xs">
                Sin condiciones: la regla se ejecuta en cada disparador.
            </p>
            <div
                v-for="(condition, index) in localConditions"
                :key="index"
                class="grid gap-2 rounded-lg border border-portal-border p-3 md:grid-cols-[1fr_auto_1fr_auto]"
            >
                <select v-model="condition.field" class="field-input">
                    <option v-for="opt in FIELD_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <select v-model="condition.operator" class="field-input">
                    <option value="eq">igual a</option>
                    <option value="min">mínimo</option>
                </select>
                <input v-model="condition.value" class="field-input" placeholder="Valor" />
                <AppButton type="button" variant="ghost" @click="removeCondition(index)">Quitar</AppButton>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-portal-heading text-sm font-medium">Acciones</h3>
                <AppButton type="button" variant="secondary" @click="addAction">Añadir acción</AppButton>
            </div>
            <div
                v-for="(action, index) in localActions"
                :key="index"
                class="grid gap-2 rounded-lg border border-portal-border p-3"
            >
                <label class="text-portal-heading block text-sm">
                    Tipo
                    <select v-model="action.type" class="field-input mt-1 w-full">
                        <option value="log">Registrar en log</option>
                        <option value="webhook">Disparar webhook</option>
                    </select>
                </label>
                <MaterialField
                    v-if="action.type === 'log'"
                    v-model="action.message"
                    label="Mensaje de log"
                />
                <MaterialField
                    v-else
                    v-model="action.event"
                    :label="`Evento webhook (vacío = ${triggerType})`"
                />
                <AppButton type="button" variant="ghost" @click="removeAction(index)">Quitar acción</AppButton>
            </div>
        </section>
    </div>
</template>
