<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { Car, Folder, Gauge, LogOut, Mail, MessageSquareText, Newspaper, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const user = computed(() => page.props.auth?.user ?? {});

const groups = [
    {
        label: 'Overview',
        items: [{ title: 'Dashboard', href: '/admin/dashboard', icon: Gauge }],
    },
    {
        label: 'Catalog',
        items: [
            { title: 'Products', href: '/admin/products', icon: Car },
            { title: 'Categories & Makes', href: '/admin/categories', icon: Folder },
        ],
    },
    {
        label: 'Inbox',
        items: [
            { title: 'Queries', href: '/admin/query-form', icon: MessageSquareText },
            { title: 'Newsletter', href: '/admin/newsletter', icon: Mail },
        ],
    },
    {
        label: 'Content',
        items: [
            { title: 'Blogs', href: '/admin/blogs', icon: Newspaper },
            { title: 'Users', href: '/admin/users', icon: Users },
        ],
    },
];

const isActive = (href) => current.value === href || current.value.startsWith(href + '/');

const initials = computed(() =>
    (user.value.name || '?')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
);
</script>

<template>
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-[264px] flex-col overflow-hidden lg:flex">
        <!-- Layered cockpit background -->
        <div class="absolute inset-0 bg-[#0c0a09]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(142,37,39,0.28),transparent_55%)]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:100%_44px]"></div>
        <div class="absolute inset-y-0 right-0 w-px bg-gradient-to-b from-[#8e2527]/60 via-white/10 to-transparent"></div>

        <div class="relative flex h-full flex-col">
            <!-- Brand -->
            <Link href="/admin/dashboard" class="flex items-center gap-3 px-6 pb-6 pt-7">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#b93a3d] to-[#6d1b1d] shadow-[0_0_24px_rgba(142,37,39,0.5)]">
                    <span class="font-display text-lg font-black italic text-white">S</span>
                </div>
                <div class="leading-tight">
                    <div class="font-display text-[15px] font-800 font-bold uppercase tracking-[0.08em] text-white">Supreme<span class="text-[#e05b5e]">Motors</span></div>
                    <div class="font-gauge text-[9px] uppercase tracking-[0.35em] text-zinc-500">Control Deck</div>
                </div>
            </Link>

            <!-- Nav -->
            <nav class="flex-1 space-y-6 overflow-y-auto px-4 pb-4">
                <div v-for="group in groups" :key="group.label">
                    <div class="mb-2 px-3 font-gauge text-[9px] font-bold uppercase tracking-[0.3em] text-zinc-600">
                        {{ group.label }}
                    </div>
                    <div class="space-y-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition-all duration-200"
                            :class="isActive(item.href)
                                ? 'bg-gradient-to-r from-[#8e2527]/90 to-[#8e2527]/40 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.15),0_4px_16px_rgba(142,37,39,0.35)]'
                                : 'text-zinc-400 hover:bg-white/[0.05] hover:text-white'"
                        >
                            <span
                                class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-full transition-all duration-200"
                                :class="isActive(item.href) ? 'bg-[#ff6b6e] shadow-[0_0_8px_rgba(255,107,110,0.8)]' : 'bg-transparent group-hover:bg-zinc-600'"
                            ></span>
                            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" :class="isActive(item.href) ? 'text-white' : 'text-zinc-500 group-hover:text-zinc-300'" />
                            {{ item.title }}
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User -->
            <div class="border-t border-white/[0.06] p-4">
                <div class="flex items-center gap-3 rounded-xl bg-white/[0.04] p-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-zinc-700 to-zinc-800 font-display text-xs font-bold text-white ring-1 ring-white/10">
                        {{ initials }}
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="truncate text-[13px] font-bold text-white">{{ user.name }}</div>
                        <div class="font-gauge text-[9px] uppercase tracking-[0.2em] text-[#e05b5e]">Administrator</div>
                    </div>
                    <Link href="/logout" method="post" as="button" class="text-zinc-500 transition-colors hover:text-[#e05b5e]" title="Log out">
                        <LogOut class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>
