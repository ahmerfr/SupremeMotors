<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-[34px] font-black uppercase leading-none tracking-tight text-zinc-900 dark:text-white">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-2 font-gauge text-[11px] font-bold uppercase tracking-[0.25em] text-zinc-400 dark:text-zinc-500">
                {{ subtitle }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
