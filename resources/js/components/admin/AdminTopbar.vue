<script setup>
import ThemeToggle from '@/components/admin/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    breadcrumbs: { type: Array, default: () => [] },
});

const today = computed(() =>
    new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-[#faf9f7]/85 backdrop-blur-xl dark:border-white/[0.06] dark:bg-[#0c0a09]/85">
        <div class="flex h-16 items-center justify-between gap-4 px-6 lg:px-8">
            <!-- Breadcrumb trail -->
            <nav class="flex min-w-0 items-center gap-1.5 font-gauge text-[11px] uppercase tracking-[0.2em]">
                <Link href="/admin/dashboard" class="shrink-0 text-zinc-400 transition-colors hover:text-[#8e2527] dark:text-zinc-500">Admin</Link>
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <ChevronRight class="h-3 w-3 shrink-0 text-zinc-300 dark:text-zinc-700" />
                    <Link
                        :href="crumb.href"
                        class="truncate transition-colors"
                        :class="i === breadcrumbs.length - 1
                            ? 'font-bold text-zinc-900 dark:text-white'
                            : 'text-zinc-400 hover:text-[#8e2527] dark:text-zinc-500'"
                    >{{ crumb.title }}</Link>
                </template>
            </nav>

            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden font-gauge text-[11px] uppercase tracking-[0.15em] text-zinc-400 md:block dark:text-zinc-500">
                    {{ today }}
                </span>
                <span class="hidden h-4 w-px bg-zinc-200 md:block dark:bg-white/10"></span>
                <a
                    href="/"
                    target="_blank"
                    class="flex h-9 items-center gap-1.5 rounded-lg border border-zinc-200 px-3 font-gauge text-[10px] font-bold uppercase tracking-[0.15em] text-zinc-500 transition-colors hover:border-[#8e2527] hover:text-[#8e2527] dark:border-white/10 dark:text-zinc-400 dark:hover:border-[#e05b5e] dark:hover:text-[#e05b5e]"
                >
                    Live Site <ExternalLink class="h-3 w-3" />
                </a>
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
