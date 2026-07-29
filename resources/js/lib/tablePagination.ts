import { computed, ref, watch, type MaybeRefOrGetter, toValue } from 'vue';

export const TABLE_PAGE_SIZE_MIN = 10;
export const TABLE_PAGE_SIZE_MAX = 100;
export const TABLE_PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

const STORAGE_PREFIX = 'phoenix_table_page_size:v1:';

function clampPageSize(n: number): number {
    return Math.min(TABLE_PAGE_SIZE_MAX, Math.max(TABLE_PAGE_SIZE_MIN, n));
}

export function useTablePagination(tableId: MaybeRefOrGetter<string>, totalItems: MaybeRefOrGetter<number>) {
    const page = ref(1);
    const pageSize = ref(TABLE_PAGE_SIZE_MIN);

    function storageKey(): string {
        return `${STORAGE_PREFIX}${toValue(tableId)}`;
    }

    function loadPageSize() {
        const raw = localStorage.getItem(storageKey());
        if (raw) {
            const parsed = Number.parseInt(raw, 10);
            if (!Number.isNaN(parsed)) {
                pageSize.value = clampPageSize(parsed);
            }
        }
    }

    watch(() => toValue(tableId), loadPageSize, { immediate: true });

    function setPageSize(size: number) {
        pageSize.value = clampPageSize(size);
        localStorage.setItem(storageKey(), String(pageSize.value));
        page.value = 1;
    }

    const totalPages = computed(() => {
        const total = toValue(totalItems);
        return Math.max(1, Math.ceil(total / pageSize.value));
    });

    watch([totalItems, pageSize], () => {
        if (page.value > totalPages.value) {
            page.value = totalPages.value;
        }
    });

    watch(
        () => toValue(totalItems),
        () => {
            page.value = 1;
        },
    );

    function setPage(next: number) {
        page.value = Math.min(totalPages.value, Math.max(1, next));
    }

    function paginateRows<T>(rows: T[]): T[] {
        const start = (page.value - 1) * pageSize.value;
        return rows.slice(start, start + pageSize.value);
    }

    const rangeLabel = computed(() => {
        const total = toValue(totalItems);
        if (total === 0) {
            return '0 resultados';
        }
        const start = (page.value - 1) * pageSize.value + 1;
        const end = Math.min(total, page.value * pageSize.value);
        return `${start}–${end} de ${total}`;
    });

    return {
        page,
        pageSize,
        totalPages,
        setPage,
        setPageSize,
        paginateRows,
        rangeLabel,
    };
}
