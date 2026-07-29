import type { TableColumnDef } from '@/lib/tableColumns';
import * as XLSX from 'xlsx';

function cellValueForExport(row: unknown, col: TableColumnDef): string {
    if (col.exportValue) {
        const v = col.exportValue(row);
        return v === null || v === undefined ? '' : String(v);
    }
    const record = row as Record<string, unknown>;
    const direct = record[col.id];
    if (direct !== null && direct !== undefined && typeof direct !== 'object') {
        return String(direct);
    }
    return '';
}

export function exportTableToExcel(
    fileName: string,
    columns: TableColumnDef[],
    rows: unknown[],
): void {
    const exportCols = columns.filter((c) => c.id !== 'actions' && c.label);
    const header = exportCols.map((c) => c.label);
    const data = rows.map((row) => exportCols.map((col) => cellValueForExport(row, col)));
    const sheet = XLSX.utils.aoa_to_sheet([header, ...data]);
    const book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, sheet, 'Datos');
    XLSX.writeFile(book, fileName.endsWith('.xlsx') ? fileName : `${fileName}.xlsx`);
}

export type ParsedExcelSheet = {
    headers: string[];
    rows: Record<string, string>[];
};

export function parseExcelFile(file: File): Promise<ParsedExcelSheet> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('No se pudo leer el archivo.'));
        reader.onload = () => {
            try {
                const data = new Uint8Array(reader.result as ArrayBuffer);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.SheetNames[0];
                if (!firstSheet) {
                    reject(new Error('El archivo no contiene hojas.'));
                    return;
                }
                const sheet = workbook.Sheets[firstSheet];
                const matrix = XLSX.utils.sheet_to_json<(string | number | null)[]>(sheet, {
                    header: 1,
                    defval: '',
                    raw: false,
                }) as (string | number | null)[][];
                if (matrix.length === 0) {
                    resolve({ headers: [], rows: [] });
                    return;
                }
                const headers = matrix[0].map((h) => String(h ?? '').trim());
                const rows = matrix.slice(1).map((line) => {
                    const record: Record<string, string> = {};
                    headers.forEach((header, idx) => {
                        if (!header) {
                            return;
                        }
                        record[header] = String(line[idx] ?? '').trim();
                    });
                    return record;
                });
                resolve({ headers, rows });
            } catch (e) {
                reject(e instanceof Error ? e : new Error('Formato de Excel no válido.'));
            }
        };
        reader.readAsArrayBuffer(file);
    });
}
