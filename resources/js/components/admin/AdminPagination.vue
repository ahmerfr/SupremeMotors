<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap items-center justify-center gap-1">
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="min-w-9 rounded-lg px-3 py-2 text-center text-sm font-semibold transition-colors"
                :class="link.active
                    ? 'bg-[#8e2527] text-white'
                    : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                v-html="link.label"
            />
            <span
                v-else
                class="min-w-9 px-3 py-2 text-center text-sm text-zinc-400 dark:text-zinc-600"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
