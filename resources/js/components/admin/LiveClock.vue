<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    timeZone: { type: String, required: true },
    flag: { type: String, default: '' },
});

const now = ref(new Date());
let timer = null;

onMounted(() => {
    timer = setInterval(() => (now.value = new Date()), 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const zoneParts = computed(() => {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: props.timeZone,
        hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false,
    }).formatToParts(now.value);
    const get = (t) => Number(parts.find((p) => p.type === t)?.value ?? 0);
    return { h: get('hour') % 24, m: get('minute'), s: get('second') };
});

const time = computed(() =>
    new Intl.DateTimeFormat('en-GB', {
        timeZone: props.timeZone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
    }).format(now.value)
);

const date = computed(() =>
    new Intl.DateTimeFormat('en-GB', { timeZone: props.timeZone, weekday: 'short', day: 'numeric', month: 'short' }).format(now.value)
);

const offset = computed(() => {
    const parts = new Intl.DateTimeFormat('en-GB', { timeZone: props.timeZone, timeZoneName: 'shortOffset' }).formatToParts(now.value);
    return parts.find((p) => p.type === 'timeZoneName')?.value ?? '';
});

const hourDeg = computed(() => ((zoneParts.value.h % 12) + zoneParts.value.m / 60) * 30);
const minDeg = computed(() => (zoneParts.value.m + zoneParts.value.s / 60) * 6);
const secDeg = computed(() => zoneParts.value.s * 6);
const isNight = computed(() => zoneParts.value.h < 6 || zoneParts.value.h >= 19);
</script>

<template>
    <div class="flex flex-1 items-center gap-5 px-6 py-5">
        <!-- Analog gauge -->
        <div class="relative h-[74px] w-[74px] shrink-0 rounded-full border border-zinc-200 bg-gradient-to-br from-white to-zinc-100 shadow-inner dark:border-white/10 dark:from-zinc-900 dark:to-black">
            <!-- tick marks -->
            <div
                v-for="i in 12"
                :key="i"
                class="absolute left-1/2 top-1/2 h-[74px] w-px"
                :style="{ transform: `translate(-50%,-50%) rotate(${i * 30}deg)` }"
            >
                <span class="absolute left-0 top-[3px] h-[6px] w-px" :class="i % 3 === 0 ? 'bg-[#8e2527] dark:bg-[#e05b5e]' : 'bg-zinc-300 dark:bg-zinc-700'"></span>
            </div>
            <!-- hands -->
            <div class="absolute left-1/2 top-1/2 h-[18px] w-[2.5px] origin-bottom rounded-full bg-zinc-800 dark:bg-zinc-200" :style="{ transform: `translate(-50%,-100%) rotate(${hourDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-[26px] w-[2px] origin-bottom rounded-full bg-zinc-600 dark:bg-zinc-400" :style="{ transform: `translate(-50%,-100%) rotate(${minDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-[29px] w-px origin-bottom rounded-full bg-[#e05b5e]" :style="{ transform: `translate(-50%,-100%) rotate(${secDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-[5px] w-[5px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#8e2527] ring-2 ring-white dark:ring-zinc-900"></div>
        </div>

        <!-- Digital -->
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="font-gauge text-[10px] font-bold uppercase tracking-[0.3em] text-zinc-400 dark:text-zinc-500">{{ flag }} {{ label }}</span>
                <span class="font-gauge text-[9px] text-zinc-300 dark:text-zinc-600">{{ offset }}</span>
                <span class="text-[9px]">{{ isNight ? '🌙' : '☀️' }}</span>
            </div>
            <div class="mt-0.5 font-gauge text-[32px] font-bold leading-none tabular-nums tracking-tight text-zinc-900 dark:text-white">
                {{ time }}
            </div>
            <div class="mt-1 font-gauge text-[10px] uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">{{ date }}</div>
        </div>
    </div>
</template>
