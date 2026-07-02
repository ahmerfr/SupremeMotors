<script setup>
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import { Link } from '@inertiajs/vue3';
import { Car, Folder, Gauge, Mail, MessageSquareText, Newspaper, Users } from 'lucide-vue-next';

defineProps({
    breadcrumbs: { type: Array, default: () => [] },
});

const mobileNav = [
    { href: '/admin/dashboard', icon: Gauge },
    { href: '/admin/products', icon: Car },
    { href: '/admin/categories', icon: Folder },
    { href: '/admin/query-form', icon: MessageSquareText },
    { href: '/admin/newsletter', icon: Mail },
    { href: '/admin/blogs', icon: Newspaper },
    { href: '/admin/users', icon: Users },
];
</script>

<template>
    <div class="min-h-screen bg-[#fafafa] font-body text-zinc-900 antialiased dark:bg-[#101010] dark:text-zinc-100">
        <AdminSidebar />

        <!-- Mobile icon rail -->
        <nav class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-zinc-200 bg-white/95 py-2 backdrop-blur-lg lg:hidden dark:border-white/[0.07] dark:bg-[#121212]/95">
            <Link
                v-for="item in mobileNav"
                :key="item.href"
                :href="item.href"
                class="flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 transition-colors duration-300 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/[0.06]"
            >
                <component :is="item.icon" class="h-5 w-5" :stroke-width="1.5" />
            </Link>
        </nav>

        <div class="relative flex min-h-screen flex-col pb-16 lg:pb-0 lg:pl-[284px]">
            <AdminTopbar :breadcrumbs="breadcrumbs" />
            <main class="flex flex-1 flex-col">
                <slot />
            </main>
        </div>
    </div>
</template>
