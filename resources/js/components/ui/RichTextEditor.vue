<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { common, createLowlight } from 'lowlight';

const props = defineProps<{
    label?: string;
}>();

const model = defineModel<string>({ default: '' });

const lowlight = createLowlight(common);

const editor = useEditor({
    content: model.value || '<p></p>',
    extensions: [
        StarterKit.configure({
            codeBlock: false,
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        CodeBlockLowlight.configure({
            lowlight,
            defaultLanguage: 'sql',
        }),
        Table.configure({
            resizable: false,
        }),
        TableRow,
        TableHeader,
        TableCell,
    ],
    editorProps: {
        attributes: {
            class: 'rich-text-editor__content prose prose-invert max-w-none text-sm focus:outline-none',
        },
    },
    onUpdate: ({ editor: ed }) => {
        const html = ed.getHTML();
        model.value = html === '<p></p>' ? '' : html;
    },
});

watch(
    () => model.value,
    (html) => {
        const ed = editor.value;
        if (!ed) {
            return;
        }
        const current = ed.getHTML();
        const next = html || '<p></p>';
        if (current !== next) {
            ed.commands.setContent(next, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function run(action: () => void) {
    action();
    editor.value?.commands.focus();
}
</script>

<template>
    <div class="rich-text-editor space-y-2">
        <p v-if="label" class="text-portal-heading text-sm font-medium">{{ label }}</p>
        <div v-if="editor" class="rich-text-editor__toolbar flex flex-wrap gap-1">
            <button type="button" class="report-md-btn" title="Negrita" @click="run(() => editor!.chain().toggleBold().run())">
                B
            </button>
            <button type="button" class="report-md-btn" title="Cursiva" @click="run(() => editor!.chain().toggleItalic().run())">
                I
            </button>
            <button type="button" class="report-md-btn" title="Título" @click="run(() => editor!.chain().toggleHeading({ level: 2 }).run())">
                H2
            </button>
            <button type="button" class="report-md-btn" title="Lista" @click="run(() => editor!.chain().toggleBulletList().run())">
                •
            </button>
            <button
                type="button"
                class="report-md-btn"
                title="Alinear izquierda"
                @click="run(() => editor!.chain().setTextAlign('left').run())"
            >
                ≡
            </button>
            <button
                type="button"
                class="report-md-btn"
                title="Centrar"
                @click="run(() => editor!.chain().setTextAlign('center').run())"
            >
                ≡C
            </button>
            <button
                type="button"
                class="report-md-btn"
                title="Alinear derecha"
                @click="run(() => editor!.chain().setTextAlign('right').run())"
            >
                C≡
            </button>
            <button type="button" class="report-md-btn" title="Código inline" @click="run(() => editor!.chain().toggleCode().run())">
                &lt;/&gt;
            </button>
            <button
                type="button"
                class="report-md-btn"
                title="Bloque de código (SQL, etc.)"
                @click="run(() => editor!.chain().toggleCodeBlock({ language: 'sql' }).run())"
            >
                SQL
            </button>
            <button
                type="button"
                class="report-md-btn"
                title="Insertar tabla"
                @click="
                    run(() =>
                        editor!.chain().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
                    )
                "
            >
                Tabla
            </button>
        </div>
        <EditorContent :editor="editor" class="rich-text-editor__surface portal-form-panel min-h-[120px] px-3 py-2" />
    </div>
</template>

<style scoped>
.rich-text-editor__surface :deep(.ProseMirror) {
    min-height: 6rem;
}

.rich-text-editor__surface :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0.5rem 0;
}

.rich-text-editor__surface :deep(th),
.rich-text-editor__surface :deep(td) {
    border: 1px solid rgb(255 255 255 / 0.15);
    padding: 0.35rem 0.5rem;
}

.rich-text-editor__surface :deep(pre) {
    background: rgb(0 0 0 / 0.25);
    border-radius: 0.5rem;
    padding: 0.75rem;
    overflow-x: auto;
    font-size: 0.8rem;
}
</style>
