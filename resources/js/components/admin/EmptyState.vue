<script setup>
import { Inbox } from 'lucide-vue-next';

defineProps({
    message: { type: String, default: 'Nothing here yet.' },
    icon: { type: [Object, Function], default: () => Inbox },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
        <component :is="icon" class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ message }}</p>
        <slot />
    </div>
</template>
