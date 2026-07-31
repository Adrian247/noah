<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    isProcessing?: boolean;
    /** Atenúa el dibujo en fondos claros */
    subdued?: boolean;
    /** Tinte ámbar/industrial para pantalla de login */
    warm?: boolean;
    /** Paleta para tema claro del portal */
    light?: boolean;
}>();

const canvasWrapper = ref<HTMLDivElement | null>(null);
const neuralCanvas = ref<HTMLCanvasElement | null>(null);

let ctx: CanvasRenderingContext2D | null = null;
let particles: Array<{
    x: number;
    y: number;
    vx: number;
    vy: number;
    baseRadius: number;
    seed: number;
}> = [];
let animationId = 0;
let width = 0;
let height = 0;
let resizeObserver: ResizeObserver | null = null;
let visibilityHandler: (() => void) | null = null;
const mouse = { x: -1000, y: -1000, radius: 120 };
let time = 0;
let transitionLevel = 0;
let running = false;

const colorIdle = { r: 79, g: 70, b: 229 };
const colorActive = { r: 99, g: 102, b: 241 };
const colorWarmIdle = { r: 180, g: 120, b: 40 };
const colorWarmActive = { r: 251, g: 191, b: 36 };
const colorLightIdle = { r: 146, g: 64, b: 14 };
const colorLightActive = { r: 217, g: 119, b: 6 };

function palette() {
    if (props.light) {
        return { idle: colorLightIdle, active: colorLightActive };
    }
    if (props.warm) {
        return { idle: colorWarmIdle, active: colorWarmActive };
    }
    return { idle: colorIdle, active: colorActive };
}

function intensityFactor() {
    if (props.light) {
        return 0.42;
    }
    return props.subdued ? 0.72 : 1;
}

function initCanvas() {
    const wrapper = canvasWrapper.value;
    const canvas = neuralCanvas.value;
    if (!wrapper || !canvas) {
        return false;
    }

    const w = wrapper.clientWidth;
    const h = wrapper.clientHeight;
    if (w < 8 || h < 8) {
        return false;
    }

    const dpr = window.devicePixelRatio || 1;
    width = w;
    height = h;

    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    ctx = canvas.getContext('2d');
    if (!ctx) {
        return false;
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    createParticles();
    return true;
}

function createParticles() {
    particles = [];
    const densityDivisor = props.subdued ? 28_000 : 12_000;
    const maxParticles = props.subdued ? 48 : 96;
    const numParticles = Math.min(maxParticles, Math.max(24, Math.floor((width * height) / densityDivisor)));
    for (let i = 0; i < numParticles; i++) {
        particles.push({
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.8,
            vy: (Math.random() - 0.5) * 0.8,
            baseRadius: Math.random() * 1.5 + 0.5,
            seed: Math.random() * 100,
        });
    }
}

function onMouseMove(e: MouseEvent) {
    const wrapper = canvasWrapper.value;
    if (!wrapper) {
        return;
    }
    const rect = wrapper.getBoundingClientRect();
    mouse.x = e.clientX - rect.left;
    mouse.y = e.clientY - rect.top;
}

function onMouseLeave() {
    mouse.x = -1000;
    mouse.y = -1000;
}

function animate() {
    if (!ctx || !running) {
        return;
    }
    animationId = requestAnimationFrame(animate);
    time += 0.01;
    ctx.clearRect(0, 0, width, height);

    const { idle, active } = palette();
    const targetLevel = props.isProcessing ? 1 : 0;
    transitionLevel += (targetLevel - transitionLevel) * 0.05;

    const r = idle.r + (active.r - idle.r) * transitionLevel;
    const g = idle.g + (active.g - idle.g) * transitionLevel;
    const b = idle.b + (active.b - idle.b) * transitionLevel;
    const currentColor = `${r}, ${g}, ${b}`;
    const speedMultiplier = 1 + transitionLevel * 3.5;
    const connectionDistance = 120 + transitionLevel * 40;
    const drawFactor = intensityFactor();
    const drawConnections = !props.subdued;

    for (let i = 0; i < particles.length; i++) {
        const p = particles[i];
        const organicX = Math.sin(time + p.seed) * 0.3;
        const organicY = Math.cos(time + p.seed) * 0.3;

        p.x += (p.vx + organicX) * speedMultiplier;
        p.y += (p.vy + organicY) * speedMultiplier;

        const dx = mouse.x - p.x;
        const dy = mouse.y - p.y;
        const distToMouse = Math.sqrt(dx * dx + dy * dy);
        if (distToMouse < mouse.radius) {
            const force = (mouse.radius - distToMouse) / mouse.radius;
            const angle = Math.atan2(dy, dx);
            p.x -= Math.cos(angle) * force * 2.5;
            p.y -= Math.sin(angle) * force * 2.5;
        }

        if (p.x < 0 || p.x > width) {
            p.vx *= -1;
        }
        if (p.y < 0 || p.y > height) {
            p.vy *= -1;
        }
        p.x = Math.max(0, Math.min(width, p.x));
        p.y = Math.max(0, Math.min(height, p.y));

        ctx.beginPath();
        ctx.arc(p.x, p.y, p.baseRadius, 0, Math.PI * 2);
        const nodeOpacity = (0.28 + transitionLevel * 0.45) * drawFactor;
        ctx.fillStyle = `rgba(${currentColor}, ${nodeOpacity})`;
        ctx.fill();
    }

    if (drawConnections) {
        ctx.lineWidth = 1;
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const p1 = particles[i];
                const p2 = particles[j];
                const dx = p1.x - p2.x;
                const dy = p1.y - p2.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < connectionDistance) {
                    const lineOpacity =
                        (1 - dist / connectionDistance) * (0.2 + transitionLevel * 0.35) * drawFactor;
                    ctx.beginPath();
                    ctx.strokeStyle = `rgba(${currentColor}, ${lineOpacity})`;
                    ctx.moveTo(p1.x, p1.y);
                    ctx.lineTo(p2.x, p2.y);
                    ctx.stroke();
                }
            }
        }
    }
}

function startAnimation() {
    if (running) {
        return;
    }
    if (!initCanvas()) {
        return;
    }
    running = true;
    animate();
}

function onWindowResize() {
    if (initCanvas() && !running) {
        startAnimation();
    }
}

function setupResizeObserver() {
    if (!canvasWrapper.value || typeof ResizeObserver === 'undefined') {
        return;
    }
    resizeObserver = new ResizeObserver(() => {
        if (initCanvas() && !running) {
            startAnimation();
        }
    });
    resizeObserver.observe(canvasWrapper.value);
}

function stopAnimation() {
    running = false;
    cancelAnimationFrame(animationId);
}

function onVisibilityChange() {
    if (document.hidden) {
        stopAnimation();
        return;
    }
    startAnimation();
}

onMounted(() => {
    window.addEventListener('resize', onWindowResize);
    visibilityHandler = onVisibilityChange;
    document.addEventListener('visibilitychange', visibilityHandler);
    canvasWrapper.value?.addEventListener('mousemove', onMouseMove);
    canvasWrapper.value?.addEventListener('mouseleave', onMouseLeave);
    setupResizeObserver();
    requestAnimationFrame(() => {
        startAnimation();
        if (!running) {
            setTimeout(startAnimation, 200);
        }
    });
});

onBeforeUnmount(() => {
    stopAnimation();
    window.removeEventListener('resize', onWindowResize);
    if (visibilityHandler) {
        document.removeEventListener('visibilitychange', visibilityHandler);
    }
    resizeObserver?.disconnect();
    cancelAnimationFrame(animationId);
});

watch(
    () => props.isProcessing,
    () => {
        /* transition handled in animate loop */
    },
);

watch(
    () => [props.subdued, props.warm, props.light],
    () => {
        /* intensityFactor / palette read props each frame */
    },
);
</script>

<template>
    <div ref="canvasWrapper" class="bio-neural-container" aria-hidden="true">
        <canvas ref="neuralCanvas" />
    </div>
</template>

<style scoped>
.bio-neural-container {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
    pointer-events: auto;
    background: transparent;
}

canvas {
    display: block;
}
</style>
