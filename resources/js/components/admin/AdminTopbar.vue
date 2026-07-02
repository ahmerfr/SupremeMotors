<script setup>
import ThemeToggle from '@/components/admin/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps({
    breadcrumbs: { type: Array, default: () => [] },
});

const today = computed(() =>
    new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).format(new Date())
);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-[#fafafa]/90 backdrop-blur-lg dark:border-white/[0.07] dark:bg-[#101010]/90">
        <div class="flex h-14 items-center justify-between gap-4 px-6 lg:px-10">
            <nav class="flex min-w-0 items-center gap-1 text-[13px]">
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <ChevronRight v-if="i > 0" class="h-3.5 w-3.5 shrink-0 text-zinc-300 dark:text-zinc-600" :stroke-width="1.5" />
                    <Link
                        :href="crumb.href"
                        class="truncate transition-colors duration-300"
                        :class="i === breadcrumbs.length - 1
                            ? 'font-semibold text-zinc-900 dark:text-white'
                            : 'font-medium text-zinc-400 hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-200'"
                    >{{ crumb.title }}</Link>
                </template>
            </nav>

            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-[13px] font-medium text-zinc-400 md:block dark:text-zinc-500">{{ today }}</span>
                <a
                    href="/"
                    target="_blank"
                    class="flex h-8 items-center gap-1 rounded-lg px-2.5 text-[13px] font-medium text-zinc-500 transition-colors duration-300 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    Live site <ArrowUpRight class="h-3.5 w-3.5" :stroke-width="1.5" />
                </a>
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
