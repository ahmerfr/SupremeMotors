<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: [Object, Function], default: null },
    accent: { type: Boolean, default: false },
});

const formatted = (v) => (typeof v === 'number' ? v.toLocaleString() : v);
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-2xl border p-5 transition-all hover:-translate-y-0.5 hover:shadow-lg"
        :class="accent
            ? 'border-[#8e2527] bg-[#8e2527] text-white'
            : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900'"
    >
        <div class="flex items-start justify-between">
            <span
                class="text-xs font-bold uppercase tracking-widest"
                :class="accent ? 'text-red-100' : 'text-zinc-500 dark:text-zinc-400'"
            >{{ title }}</span>
            <component
                :is="icon"
                v-if="icon"
                class="h-5 w-5"
                :class="accent ? 'text-red-200' : 'text-[#8e2527]'"
            />
        </div>
        <div
            class="mt-3 font-black tabular-nums tracking-tight"
            :class="[
                accent ? 'text-white' : 'text-zinc-900 dark:text-white',
                String(formatted(value)).length > 6 ? 'text-2xl' : 'text-4xl',
            ]"
        >
            {{ formatted(value) }}
        </div>
    </div>
</template>
