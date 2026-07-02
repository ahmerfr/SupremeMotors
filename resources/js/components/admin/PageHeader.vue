<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-[32px] font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1.5 text-[15px] text-zinc-500 dark:text-zinc-400">
                {{ subtitle }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
