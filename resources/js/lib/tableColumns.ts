import { computed, ref, watch, type MaybeRefOrGetter, toValue } from 'vue';

export type TableColumnDef = {
    id: string;
    label: string;
    /** Por defecto visible salvo que sea `false`. */
    defaultVisible?: boolean;
    /** No se puede ocultar (p. ej. acciones). */
    locked?: boolean;
    headerClass?: string;
    cellClass?: string;
    /** Valor para exportación Excel (columnas visibles). */
    exportValue?: (row: unknown) => string | number | null | undefined;
};

export type TableColumnChoice = {
    id: string;
    label: string;
    visible: boolean;
    locked: boolean;
};

const STORAGE_PREFIX = 'phoenix_table_columns:v1:';

function defaultVisibleIds(defs: TableColumnDef[]): string[] {
    return defs.filter((d) => d.defaultVisible !== false || d.locked).map((d) => d.id);
}

function mergeVisibleIds(saved: string[], defs: TableColumnDef[]): string[] {
    const defById = Object.fromEntries(defs.map((d) => [d.id, d]));
    const visibleSet = new Set(saved.filter((id) => defById[id] !== undefined));

    for (const def of defs) {
        if (def.locked) {
            visibleSet.add(def.id);
        } else if (!visibleSet.has(def.id) && def.defaultVisible !== false) {
            visibleSet.add(def.id);
        }
    }

    return defs.map((d) => d.id).filter((id) => visibleSet.has(id));
}

export function useTableColumns(
    tableId: MaybeRefOrGetter<string>,
    columnDefs: MaybeRefOrGetter<TableColumnDef[]>,
) {
    const visibleOrder = ref<string[]>([]);

    function storageKey(): string {
        return `${STORAGE_PREFIX}${toValue(tableId)}`;
    }

    function syncFromDefs() {
        const defs = toValue(columnDefs);
        const raw = localStorage.getItem(storageKey());
        if (raw) {
            try {
                const parsed = JSON.parse(raw) as string[];
                if (Array.isArray(parsed)) {
                    visibleOrder.value = mergeVisibleIds(parsed, defs);
                    return;
                }
            } catch {
                /* ignore */
            }
        }
        visibleOrder.value = defaultVisibleIds(defs);
    }

    watch([() => toValue(tableId), () => toValue(columnDefs)], syncFromDefs, { immediate: true, deep: true });

    function persist() {
        localStorage.setItem(storageKey(), JSON.stringify(visibleOrder.value));
    }

    const visibleColumns = computed(() => {
        const defs = toValue(columnDefs);
        const byId = Object.fromEntries(defs.map((d) => [d.id, d]));
        return visibleOrder.value.map((id) => byId[id]).filter((d): d is TableColumnDef => d !== undefined);
    });

    const columnChoices = computed((): TableColumnChoice[] => {
        const defs = toValue(columnDefs);
        const visibleSet = new Set(visibleOrder.value);
        return defs.map((d) => ({
            id: d.id,
            label: d.label || d.id,
            visible: visibleSet.has(d.id),
            locked: Boolean(d.locked),
        }));
    });

    function setColumnVisible(id: string, visible: boolean) {
        const def = toValue(columnDefs).find((d) => d.id === id);
        if (!def || def.locked) {
            return;
        }
        const set = new Set(visibleOrder.value);
        if (visible) {
            set.add(id);
        } else {
            set.delete(id);
        }
        const defs = toValue(columnDefs);
        visibleOrder.value = defs.map((d) => d.id).filter((colId) => set.has(colId));
        persist();
    }

    function resetColumns() {
        visibleOrder.value = defaultVisibleIds(toValue(columnDefs));
        persist();
    }

    return {
        visibleColumns,
        columnChoices,
        setColumnVisible,
        resetColumns,
    };
}

export function tableActionsColumn(overrides: Partial<TableColumnDef> = {}): TableColumnDef {
    return {
        id: 'actions',
        label: '',
        locked: true,
        defaultVisible: true,
        headerClass: 'portal-table-col-actions',
        cellClass: 'portal-table-col-actions text-right',
        ...overrides,
    };
}
