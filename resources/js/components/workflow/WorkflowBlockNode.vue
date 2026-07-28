<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { roleCatalogLabel, type BlockNode } from '@/lib/workflowBlockModel';

const props = defineProps<{
    data: BlockNode;
}>();

const node = () => props.data ?? ({ kind: 'end', label: '?', id: '' } as BlockNode);

function blockClass(kind: BlockNode['kind']): string {
    if (kind === 'routine') {
        return 'wf-node wf-node--routine wf-node--anchor';
    }
    if (kind === 'end') {
        return 'wf-node wf-node--end';
    }
    return 'wf-node wf-node--role';
}
</script>

<template>
    <div class="workflow-block-node">
        <template v-if="node().kind === 'routine'">
            <Handle id="in-forward" type="target" :position="Position.Left" class="wf-handle" />
            <Handle id="in-reject" type="target" :position="Position.Bottom" class="wf-handle wf-handle--reject" />
            <Handle id="out-forward" type="source" :position="Position.Right" class="wf-handle wf-handle--routine" />
            <div :class="blockClass('routine')">
                <p class="wf-node__badge">INICIO</p>
                <p class="wf-node__title">{{ node().label || 'Rutina' }}</p>
                <p class="wf-node__meta">Técnico ejecuta en campo</p>
            </div>
        </template>
        <template v-else-if="node().kind === 'role'">
            <Handle id="in-forward" type="target" :position="Position.Left" class="wf-handle" />
            <div :class="blockClass('role')">
                <p class="wf-node__title">{{ node().label }}</p>
                <p class="wf-node__meta">{{ roleCatalogLabel(node().assigned_role ?? '') }}</p>
            </div>
            <Handle id="out-forward" type="source" :position="Position.Right" class="wf-handle" />
            <Handle id="out-reject" type="source" :position="Position.Bottom" class="wf-handle wf-handle--reject" />
        </template>
        <template v-else>
            <Handle id="in-forward" type="target" :position="Position.Left" class="wf-handle" />
            <div :class="blockClass('end')">
                <p class="wf-node__title">{{ node().label || 'Fin' }}</p>
            </div>
        </template>
    </div>
</template>

<style scoped>
.workflow-block-node {
    position: relative;
}

.wf-handle {
    width: 8px;
    height: 8px;
    background: #64748b;
}

.wf-node {
    min-width: 9rem;
    border-radius: 0.5rem;
    border: 1px solid #94a3b8;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    background: #fff;
    color: #0f172a;
}

.wf-node--routine {
    border-color: #6366f1;
    background: #eef2ff;
    min-width: 10.5rem;
    box-shadow: 0 0 0 2px rgb(99 102 241 / 0.35);
}

.wf-node--anchor {
    cursor: default;
}

.wf-node__badge {
    margin-bottom: 0.25rem;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #4338ca;
}

.wf-handle--reject {
    background: #b45309;
}

.wf-handle--routine {
    background: #6366f1;
    width: 10px;
    height: 10px;
}

.wf-node--role {
    border-color: #0ea5e9;
}

.wf-node--end {
    border-color: #64748b;
}

.wf-node__title {
    font-weight: 600;
}

.wf-node__meta {
    font-size: 10px;
    opacity: 0.75;
}
</style>
