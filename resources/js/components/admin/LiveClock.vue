<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    timeZone: { type: String, required: true },
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

const meta = computed(() => {
    const date = new Intl.DateTimeFormat('en-GB', { timeZone: props.timeZone, weekday: 'short', day: 'numeric', month: 'short' }).format(now.value);
    const parts = new Intl.DateTimeFormat('en-GB', { timeZone: props.timeZone, timeZoneName: 'shortOffset' }).formatToParts(now.value);
    const offset = parts.find((p) => p.type === 'timeZoneName')?.value ?? '';
    return `${date}, ${offset}`;
});

const hourDeg = computed(() => ((zoneParts.value.h % 12) + zoneParts.value.m / 60) * 30);
const minDeg = computed(() => (zoneParts.value.m + zoneParts.value.s / 60) * 6);
const secDeg = computed(() => zoneParts.value.s * 6);
</script>

<template>
    <div class="flex flex-1 items-center gap-4 px-6 py-5">
        <!-- Dial -->
        <div class="relative h-14 w-14 shrink-0 rounded-full border border-zinc-200 bg-white dark:border-white/10 dark:bg-white/[0.04]">
            <div
                v-for="i in 4"
                :key="i"
                class="absolute left-1/2 top-1/2 h-14 w-px"
                :style="{ transform: `translate(-50%,-50%) rotate(${i * 90}deg)` }"
            >
                <span class="absolute left-0 top-[3px] h-[4px] w-px bg-zinc-300 dark:bg-zinc-600"></span>
            </div>
            <div class="absolute left-1/2 top-1/2 h-[13px] w-[2px] rounded-full bg-zinc-700 dark:bg-zinc-300" :style="{ transform: `translate(-50%,-100%) rotate(${hourDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-[19px] w-[1.5px] rounded-full bg-zinc-500 dark:bg-zinc-400" :style="{ transform: `translate(-50%,-100%) rotate(${minDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-[21px] w-px rounded-full bg-[#8e2527]" :style="{ transform: `translate(-50%,-100%) rotate(${secDeg}deg)`, transformOrigin: '50% 100%' }"></div>
            <div class="absolute left-1/2 top-1/2 h-1 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-700 dark:bg-zinc-300"></div>
        </div>

        <div class="min-w-0">
            <div class="text-[13px] font-medium text-zinc-500 dark:text-zinc-400">{{ label }}</div>
            <div class="mt-0.5 font-gauge text-[26px] font-medium leading-none tabular-nums text-zinc-900 dark:text-white">
                {{ time }}
            </div>
            <div class="mt-1 text-[12px] text-zinc-400 dark:text-zinc-500">{{ meta }}</div>
        </div>
    </div>
</template>
