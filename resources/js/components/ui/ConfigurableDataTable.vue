<script setup lang="ts">
import { computed, ref, useSlots } from 'vue';
import type { TableColumnDef } from '@/lib/tableColumns';
import { useTableColumns } from '@/lib/tableColumns';
import { exportTableToExcel, parseExcelFile } from '@/lib/tableExcel';
import { rowMatchesSearch } from '@/lib/tableSearch';
import { TABLE_PAGE_SIZE_OPTIONS, useTablePagination } from '@/lib/tablePagination';
import { useToast } from '@/composables/useToast';
import TableColumnsPicker from '@/components/ui/TableColumnsPicker.vue';
import TableSearchPopover from '@/components/ui/TableSearchPopover.vue';
import TableFiltersPopover from '@/components/ui/TableFiltersPopover.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';

const toast = useToast();

const props = withDefaults(
    defineProps<{
        tableId: string;
        columns: TableColumnDef[];
        rows: unknown[];
        rowKey?: string | ((row: unknown, index: number) => string | number);
        tableClass?: string;
        clickable?: boolean;
        rowClass?: string | ((row: unknown) => string);
        emptyText?: string;
        showColumnPicker?: boolean;
        stopClickColumnIds?: string[];
        /** Popover de búsqueda (icono en barra de herramientas). */
        showSearch?: boolean;
        /** Si es false, solo emite `update:search` (p. ej. filtro en API). */
        clientSearch?: boolean;
        searchPlaceholder?: string;
        searchText?: (row: unknown) => string;
        showPagination?: boolean;
        showExport?: boolean;
        exportFileName?: string;
        allowExcelImport?: boolean;
        filtersActive?: boolean;
        filtersTitle?: string;
    }>(),
    {
        showColumnPicker: true,
        stopClickColumnIds: () => ['actions'],
        showSearch: true,
        clientSearch: true,
        searchPlaceholder: 'Buscar en la tabla…',
        showPagination: true,
        showExport: true,
        exportFileName: 'export',
        allowExcelImport: false,
    },
);

const searchQuery = defineModel<string>('search', { default: '' });

const slots = useSlots();

const emit = defineEmits<{
    rowClick: [row: unknown];
    importRows: [rows: Record<string, string>[]];
}>();

const { visibleColumns, columnChoices, setColumnVisible, resetColumns } = useTableColumns(
    () => props.tableId,
    () => props.columns,
);

const importInputRef = ref<HTMLInputElement | null>(null);
const importing = ref(false);

const filteredRows = computed(() => {
    if (!props.showSearch || !searchQuery.value.trim() || !props.clientSearch) {
        return props.rows;
    }
    return props.rows.filter((row) => rowMatchesSearch(row, searchQuery.value, props.searchText));
});

const { page, pageSize, totalPages, setPage, setPageSize, paginateRows, rangeLabel } = useTablePagination(
    () => props.tableId,
    () => filteredRows.value.length,
);

const displayRows = computed(() => {
    if (!props.showPagination) {
        return filteredRows.value;
    }
    return paginateRows(filteredRows.value);
});

function resolveRowKey(row: unknown, index: number): string | number {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row, index);
    }
    if (typeof props.rowKey === 'string') {
        return (row as Record<string, unknown>)[props.rowKey] as string | number;
    }
    return index;
}

function rowClasses(row: unknown): string {
    const extra =
        props.rowClass === undefined
            ? ''
            : typeof props.rowClass === 'function'
              ? props.rowClass(row)
              : props.rowClass;
    return [extra, props.clickable ? 'cursor-pointer transition hover:bg-white/5' : ''].filter(Boolean).join(' ');
}

function onRowClick(row: unknown) {
    if (props.clickable) {
        emit('rowClick', row);
    }
}

function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return Boolean(
        target.closest('input, textarea, select, button, a, [contenteditable="true"]'),
    );
}

function onRowKeydown(event: KeyboardEvent, row: unknown) {
    if (!props.clickable || isEditableTarget(event.target)) {
        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        onRowClick(row);
    }
}

function onCellClick(event: MouseEvent, columnId: string) {
    if (props.stopClickColumnIds.includes(columnId)) {
        event.stopPropagation();
    }
}

function onExport() {
    exportTableToExcel(props.exportFileName, visibleColumns.value, filteredRows.value);
}

function onImportClick() {
    importInputRef.value?.click();
}

async function onImportFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
        return;
    }
    importing.value = true;
    try {
        const parsed = await parseExcelFile(file);
        emit('importRows', parsed.rows);
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        importing.value = false;
    }
}

const showToolbar = computed(
    () =>
        props.showSearch ||
        props.showColumnPicker ||
        props.showExport ||
        props.allowExcelImport ||
        Boolean(slots.filters),
);
</script>

<template>
    <div class="portal-table-wrap">
        <div v-if="showToolbar" class="portal-table-toolbar">
            <div class="portal-table-toolbar__actions">
                <TableFiltersPopover
                    v-if="slots.filters"
                    :active="filtersActive"
                    :title="filtersTitle"
                >
                    <slot name="filters" />
                </TableFiltersPopover>
                <TableSearchPopover
                    v-if="showSearch"
                    v-model="searchQuery"
                    :placeholder="searchPlaceholder"
                />
                <IconActionButton
                    v-if="showExport"
                    icon="download"
                    label="Exportar a Excel"
                    @click="onExport"
                />
                <template v-if="allowExcelImport">
                    <input
                        ref="importInputRef"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="sr-only"
                        @change="onImportFile"
                    />
                    <IconActionButton
                        icon="upload"
                        :label="importing ? 'Importando…' : 'Importar desde Excel'"
                        :disabled="importing"
                        @click="onImportClick"
                    />
                </template>
                <TableColumnsPicker
                    v-if="showColumnPicker"
                    :choices="columnChoices"
                    @toggle="setColumnVisible"
                    @reset="resetColumns"
                />
            </div>
        </div>

        <table class="portal-data-table" :class="tableClass">
            <thead>
                <tr class="border-b">
                    <th
                        v-for="col in visibleColumns"
                        :key="col.id"
                        class="py-2"
                        :class="col.headerClass"
                    >
                        <slot :name="`header-${col.id}`">{{ col.label }}</slot>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in displayRows"
                    :key="resolveRowKey(row, index)"
                    class="border-b align-middle"
                    :class="rowClasses(row)"
                    :tabindex="clickable ? 0 : undefined"
                    :role="clickable ? 'button' : undefined"
                    @click="onRowClick(row)"
                    @keydown="onRowKeydown($event, row)"
                >
                    <td
                        v-for="col in visibleColumns"
                        :key="col.id"
                        :class="col.cellClass"
                        @click="onCellClick($event, col.id)"
                    >
                        <slot :name="col.id" :row="row" :index="index" />
                    </td>
                </tr>
                <tr v-if="displayRows.length === 0 && emptyText">
                    <td :colspan="visibleColumns.length || 1" class="text-portal-muted py-8 text-center text-sm">
                        {{ emptyText }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="showPagination && filteredRows.length > 0" class="portal-table-pagination">
            <span class="portal-table-pagination__range">{{ rangeLabel }}</span>
            <label class="portal-table-pagination__size">
                <span class="sr-only">Filas por página</span>
                <select
                    :value="pageSize"
                    class="portal-table-pagination__select"
                    @change="setPageSize(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="size in TABLE_PAGE_SIZE_OPTIONS" :key="size" :value="size">
                        {{ size }} / página
                    </option>
                </select>
            </label>
            <div class="portal-table-pagination__nav">
                <button
                    type="button"
                    class="portal-table-pagination__btn"
                    :disabled="page <= 1"
                    @click="setPage(page - 1)"
                >
                    Anterior
                </button>
                <span class="portal-table-pagination__page">{{ page }} / {{ totalPages }}</span>
                <button
                    type="button"
                    class="portal-table-pagination__btn"
                    :disabled="page >= totalPages"
                    @click="setPage(page + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
