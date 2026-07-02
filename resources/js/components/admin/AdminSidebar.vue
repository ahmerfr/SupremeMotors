<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { Car, Folder, Gauge, LogOut, Mail, MessageSquareText, Newspaper, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const user = computed(() => page.props.auth?.user ?? {});

const groups = [
    {
        label: null,
        items: [{ title: 'Dashboard', href: '/admin/dashboard', icon: Gauge }],
    },
    {
        label: 'Catalog',
        items: [
            { title: 'Products', href: '/admin/products', icon: Car },
            { title: 'Categories & makes', href: '/admin/categories', icon: Folder },
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
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-[248px] flex-col border-r border-zinc-200 bg-white lg:flex dark:border-white/[0.07] dark:bg-[#121212]">
        <!-- Brand -->
        <Link href="/admin/dashboard" class="flex items-center gap-3 px-5 pb-5 pt-6">
            <img src="/assets/images/site-logo.png" alt="Supreme Motors" class="h-9 w-auto object-contain" />
            <span class="text-sm font-semibold text-zinc-400 dark:text-zinc-500">Admin</span>
        </Link>

        <!-- Nav -->
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4 pt-1">
            <div v-for="(group, gi) in groups" :key="gi">
                <div v-if="group.label" class="mb-1.5 px-3 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                    {{ group.label }}
                </div>
                <div class="space-y-0.5">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="relative flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                        :class="isActive(item.href)
                            ? 'bg-zinc-100 text-zinc-900 dark:bg-white/[0.07] dark:text-white'
                            : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/[0.04] dark:hover:text-white'"
                    >
                        <span
                            v-if="isActive(item.href)"
                            class="absolute left-0 top-1/2 h-4 w-0.5 -translate-y-1/2 rounded-full bg-[#8e2527]"
                        ></span>
                        <component :is="item.icon" class="h-4 w-4 shrink-0" :stroke-width="1.5" />
                        {{ item.title }}
                    </Link>
                </div>
            </div>
        </nav>

        <!-- User -->
        <div class="border-t border-zinc-100 p-3 dark:border-white/[0.05]">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[11px] font-semibold text-zinc-600 dark:bg-white/[0.08] dark:text-zinc-300">
                    {{ initials }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ user.name }}</div>
                    <div class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ user.email }}</div>
                </div>
                <Link href="/logout" method="post" as="button" class="rounded-md p-1.5 text-zinc-400 transition-colors duration-300 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/[0.06] dark:hover:text-zinc-200" title="Log out">
                    <LogOut class="h-4 w-4" :stroke-width="1.5" />
                </Link>
            </div>
        </div>
    </aside>
</template>
