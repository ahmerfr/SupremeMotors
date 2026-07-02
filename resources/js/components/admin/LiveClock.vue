<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

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

const time = computed(() =>
    new Intl.DateTimeFormat('en-GB', {
        timeZone: props.timeZone,
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
    }).format(now.value)
);

const date = computed(() =>
    new Intl.DateTimeFormat('en-GB', {
        timeZone: props.timeZone,
        weekday: 'short', day: 'numeric', month: 'short',
    }).format(now.value)
);

const offset = computed(() => {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: props.timeZone, timeZoneName: 'shortOffset',
    }).formatToParts(now.value);
    return parts.find((p) => p.type === 'timeZoneName')?.value ?? '';
});
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="absolute inset-x-0 top-0 h-1 bg-[#8e2527]"></div>
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                {{ flag }} {{ label }}
            </span>
            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                {{ offset }}
            </span>
        </div>
        <div class="mt-2 font-mono text-4xl font-black tabular-nums tracking-tight text-zinc-900 dark:text-white">
            {{ time }}
        </div>
        <div class="mt-1 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ date }}</div>
    </div>
</template>
