<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import { integrationsSectionNav } from '@/lib/sectionNav';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';

type McpTool = {
    name: string;
    description: string;
    domain: string;
    domain_label: string;
    required_permissions: string[];
    available: boolean;
    mode: 'read';
};

type McpToken = {
    id: number;
    label: string;
    abilities: string[];
    last_used_at?: string | null;
    created_at?: string | null;
};

type ConnectionInfo = {
    base_url: string;
    company_id: number;
    auth: {
        type: string;
        header: string;
        company_header: string;
        token_ability: string;
        note: string;
    };
    cursor_mcp_json: Record<string, unknown>;
    http_examples: Record<string, unknown>;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('integrations'));

const loading = ref(true);
const tools = ref<McpTool[]>([]);
const tokens = ref<McpToken[]>([]);
const connection = ref<ConnectionInfo | null>(null);
const availableCount = ref(0);
const totalCount = ref(0);
const scopeNote = ref('');

const showTokenForm = ref(false);
const tokenLabel = ref('');
const createdToken = ref<string | null>(null);
const createdCompanyId = ref<number | null>(null);

const toolColumns = computed((): TableColumnDef[] => [
    { id: 'domain_label', label: 'Dominio' },
    { id: 'name', label: 'Tool' },
    { id: 'description', label: 'Descripción' },
    { id: 'available', label: 'Tu acceso' },
    { id: 'permissions', label: 'Permisos requeridos' },
]);

const tokenColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'label', label: 'Etiqueta' },
        { id: 'created_at', label: 'Creado' },
        { id: 'last_used_at', label: 'Último uso' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

const cursorSnippet = computed(() => {
    if (!connection.value) {
        return '';
    }
    return JSON.stringify(connection.value.cursor_mcp_json, null, 2);
});

const flatTools = computed(() =>
    [...tools.value].sort((a, b) => {
        const byDomain = a.domain_label.localeCompare(b.domain_label, 'es');
        return byDomain !== 0 ? byDomain : a.name.localeCompare(b.name);
    }),
);

async function load() {
    loading.value = true;
    try {
        const [toolsRes, tokensRes, connRes] = await Promise.all([
            api<{
                data: {
                    tools: McpTool[];
                    available_count: number;
                    total_count: number;
                    note: string;
                };
            }>('/integrations/mcp/tools'),
            api<{ data: McpToken[] }>('/integrations/mcp/tokens'),
            api<{ data: ConnectionInfo }>('/integrations/mcp/connection'),
        ]);
        tools.value = toolsRes.data.tools;
        availableCount.value = toolsRes.data.available_count;
        totalCount.value = toolsRes.data.total_count;
        scopeNote.value = toolsRes.data.note;
        tokens.value = tokensRes.data;
        connection.value = connRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openTokenCreate() {
    tokenLabel.value = '';
    createdToken.value = null;
    createdCompanyId.value = null;
    showTokenForm.value = true;
}

async function createToken() {
    try {
        const res = await api<{
            data: McpToken & { token: string; company_id: number; note: string };
        }>('/integrations/mcp/tokens', {
            method: 'POST',
            body: JSON.stringify({ label: tokenLabel.value || undefined }),
        });
        createdToken.value = res.data.token;
        createdCompanyId.value = res.data.company_id;
        toast.success('Token MCP generado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function revokeToken(row: McpToken) {
    const accepted = await confirm(
        `¿Revocar el token «${row.label}»? Las integraciones que lo usen dejarán de autenticarse.`,
        { title: 'Revocar token MCP', confirmLabel: 'Revocar', danger: true },
    );
    if (!accepted) {
        return;
    }
    try {
        await api(`/integrations/mcp/tokens/${row.id}`, { method: 'DELETE' });
        toast.success('Token revocado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function copyText(text: string, okMessage: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(okMessage);
    } catch {
        toast.error('No se pudo copiar al portapapeles.');
    }
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-integrations-mcp">
        <SectionSubnav :items="integrationsSectionNav" />
        <PageHeader
            title="MCP"
            subtitle="Conecta herramientas externas (Cursor, agentes) al dominio Phoenix en solo lectura, con el alcance de tu usuario y rol."
        />

        <ReadOnlyNotice v-if="!canWrite" module-label="Integraciones" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <template v-else>
            <section class="border-portal-border mb-6 space-y-3 rounded-xl border p-4">
                <h2 class="text-portal-heading text-base font-semibold">Ejemplo de conexión</h2>
                <p class="text-portal-muted text-sm">
                    Copia el fragmento a tu <code class="font-mono text-xs">mcp.json</code> de Cursor. La URL debe
                    ser <code class="font-mono text-xs">…/api/v1/integrations/mcp</code> (protocolo MCP), no
                    <code class="font-mono text-xs">…/tools</code>. Usa un token MCP +
                    <code class="font-mono text-xs">X-Company-Id</code>. No agregues
                    <code class="font-mono text-xs">Accept: application/json</code> (rompe el handshake SSE).
                </p>
                <dl v-if="connection" class="text-portal-muted grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-portal-heading text-xs font-medium">Base URL</dt>
                        <dd class="font-mono text-xs break-all">{{ connection.base_url }}</dd>
                    </div>
                    <div>
                        <dt class="text-portal-heading text-xs font-medium">Empresa (scope)</dt>
                        <dd class="font-mono text-xs">{{ connection.company_id }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-portal-heading text-xs font-medium">Autenticación</dt>
                        <dd class="text-xs">{{ connection.auth.note }}</dd>
                    </div>
                </dl>
                <div class="relative">
                    <pre
                        class="border-portal-border text-portal-heading max-h-64 overflow-auto rounded-lg border p-3 font-mono text-xs"
                    >{{ cursorSnippet }}</pre>
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="mt-2"
                        @click="copyText(cursorSnippet, 'Ejemplo Cursor copiado.')"
                    >
                        Copiar mcp.json (Cursor)
                    </AppButton>
                </div>
            </section>

            <section class="mb-6">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-portal-heading text-base font-semibold">Tokens de autenticación</h2>
                        <p class="text-portal-muted text-sm">
                            El token identifica a tu usuario. Las tools disponibles al usarlo coinciden con los
                            permisos de tu rol ({{ availableCount }} de {{ totalCount }} habilitadas ahora).
                        </p>
                    </div>
                    <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openTokenCreate">
                        Generar token
                    </AppButton>
                </div>

                <ConfigurableDataTable
                    table-id="integrations-mcp-tokens"
                    :columns="tokenColumns"
                    :rows="tokens"
                    row-key="id"
                    empty-text="Sin tokens MCP. Genera uno para conectar herramientas externas."
                >
                    <template #label="{ row }">
                        <span class="text-portal-heading">{{ (row as McpToken).label }}</span>
                    </template>
                    <template #created_at="{ row }">
                        <span class="text-portal-muted text-xs">{{ formatDate((row as McpToken).created_at) }}</span>
                    </template>
                    <template #last_used_at="{ row }">
                        <span class="text-portal-muted text-xs">{{ formatDate((row as McpToken).last_used_at) }}</span>
                    </template>
                    <template #actions="{ row }">
                        <IconActionButton
                            icon="trash"
                            label="Revocar"
                            @click="revokeToken(row as McpToken)"
                        />
                    </template>
                </ConfigurableDataTable>
            </section>

            <section>
                <h2 class="text-portal-heading mb-1 text-base font-semibold">Tools (solo lectura)</h2>
                <p class="text-portal-muted mb-3 text-sm">{{ scopeNote }}</p>

                <ConfigurableDataTable
                    table-id="integrations-mcp-tools"
                    :columns="toolColumns"
                    :rows="flatTools"
                    row-key="name"
                    empty-text="Sin tools MCP registradas."
                >
                    <template #domain_label="{ row }">
                        {{ (row as McpTool).domain_label }}
                    </template>
                    <template #name="{ row }">
                        <code class="font-mono text-xs">{{ (row as McpTool).name }}</code>
                    </template>
                    <template #description="{ row }">
                        <span class="text-portal-muted text-xs">{{ (row as McpTool).description }}</span>
                    </template>
                    <template #available="{ row }">
                        <span class="text-xs font-medium">
                            {{ (row as McpTool).available ? 'Disponible' : 'Sin permiso' }}
                        </span>
                    </template>
                    <template #permissions="{ row }">
                        <span class="text-portal-muted font-mono text-[11px]">
                            {{ (row as McpTool).required_permissions.join(' · ') }}
                        </span>
                    </template>
                </ConfigurableDataTable>
            </section>
        </template>

        <AppModal
            :open="showTokenForm && canWrite"
            title="Generar token MCP"
            size="md"
            @close="showTokenForm = false"
        >
            <form id="mcp-token-form" class="grid gap-4" @submit.prevent="createToken">
                <MaterialField
                    v-model="tokenLabel"
                    label="Etiqueta (opcional)"
                    placeholder="p. ej. Cursor escritorio"
                />
                <p class="text-portal-muted text-xs">
                    El token hereda tu identidad y solo podrá ejecutar tools MCP de lectura permitidas por tu
                    rol en la empresa activa.
                </p>
                <p v-if="createdToken" class="portal-callout portal-callout--info text-xs">
                    Guarda el token (solo se muestra una vez). Empresa sugerida
                    <code class="font-mono">X-Company-Id: {{ createdCompanyId }}</code>
                    <code class="mt-2 block break-all font-mono">{{ createdToken }}</code>
                    <AppButton
                        type="button"
                        variant="secondary"
                        class="mt-2"
                        @click="copyText(createdToken!, 'Token copiado.')"
                    >
                        Copiar token
                    </AppButton>
                </p>
            </form>
            <template #footer>
                <AppButton type="button" variant="ghost" @click="showTokenForm = false">Cerrar</AppButton>
                <AppButton v-if="!createdToken" type="submit" form="mcp-token-form">Generar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
