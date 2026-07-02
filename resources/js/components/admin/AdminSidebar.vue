<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { Car, Folder, Gauge, LogOut, Mail, MessageSquareText, Newspaper, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const user = computed(() => page.props.auth?.user ?? {});

const role = computed(() => user.value.role);

const allGroups = [
    {
        label: null,
        items: [{ title: 'Dashboard', href: '/admin/dashboard', icon: Gauge, roles: ['admin', 'editor'] }],
    },
    {
        label: 'Catalog',
        items: [
            { title: 'Products', href: '/admin/products', icon: Car, roles: ['admin', 'editor'] },
            { title: 'Categories & makes', href: '/admin/categories', icon: Folder, roles: ['admin', 'editor'] },
        ],
    },
    {
        label: 'Inbox',
        items: [
            { title: 'Queries', href: '/admin/query-form', icon: MessageSquareText, roles: ['admin'] },
            { title: 'Newsletter', href: '/admin/newsletter', icon: Mail, roles: ['admin'] },
        ],
    },
    {
        label: 'Content',
        items: [
            { title: 'Blogs', href: '/admin/blogs', icon: Newspaper, roles: ['admin', 'editor'] },
            { title: 'Users', href: '/admin/users', icon: Users, roles: ['admin'] },
        ],
    },
];

const groups = computed(() =>
    allGroups
        .map((g) => ({ ...g, items: g.items.filter((i) => i.roles.includes(role.value)) }))
        .filter((g) => g.items.length)
);

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
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-[284px] flex-col border-r border-zinc-200 bg-white lg:flex dark:border-white/[0.07] dark:bg-[#141414]">
        <!-- Brand -->
        <Link href="/admin/dashboard" class="flex items-center gap-3.5 border-b border-zinc-100 px-6 pb-6 pt-7 dark:border-white/[0.06]">
            <img src="/assets/images/site-logo.png" alt="Supreme Motors" class="h-12 w-auto object-contain" />
            <div class="leading-tight">
                <div class="text-[16px] font-semibold tracking-tight text-zinc-900 dark:text-white">Supreme Motors</div>
                <div class="text-[13px] text-zinc-400 dark:text-zinc-500">Admin panel</div>
            </div>
        </Link>

        <!-- Nav -->
        <nav class="flex-1 space-y-7 overflow-y-auto px-4 pb-5 pt-6">
            <div v-for="(group, gi) in groups" :key="gi">
                <div v-if="group.label" class="mb-2 px-3.5 text-[12px] font-semibold text-zinc-400 dark:text-zinc-500">
                    {{ group.label }}
                </div>
                <div class="space-y-1">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-[15px] transition-all duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                        :class="isActive(item.href)
                            ? 'bg-zinc-900 font-semibold text-white shadow-[0_2px_8px_rgba(0,0,0,0.12)] dark:bg-white dark:text-zinc-900'
                            : 'font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/[0.05] dark:hover:text-white'"
                    >
                        <component
                            :is="item.icon"
                            class="h-[19px] w-[19px] shrink-0 transition-colors duration-300"
                            :class="isActive(item.href) ? 'text-[#d96a6c] dark:text-[#8e2527]' : 'text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300'"
                            :stroke-width="1.75"
                        />
                        {{ item.title }}
                    </Link>
                </div>
            </div>
        </nav>

        <!-- User -->
        <div class="border-t border-zinc-100 p-4 dark:border-white/[0.06]">
            <div class="flex items-center gap-3 rounded-xl px-2 py-1.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-[13px] font-semibold text-white dark:bg-white dark:text-zinc-900">
                    {{ initials }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-[15px] font-semibold text-zinc-900 dark:text-white">{{ user.name }}</div>
                    <div class="truncate text-[13px] text-zinc-400 dark:text-zinc-500">{{ user.email }}</div>
                </div>
                <Link href="/logout" method="post" as="button" class="rounded-lg p-2 text-zinc-400 transition-colors duration-300 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/[0.06] dark:hover:text-zinc-200" title="Log out">
                    <LogOut class="h-[18px] w-[18px]" :stroke-width="1.75" />
                </Link>
            </div>
        </div>
    </aside>
</template>
