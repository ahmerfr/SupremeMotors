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
    <div class="min-h-screen bg-[#faf9f7] font-body text-zinc-900 antialiased dark:bg-[#0c0a09] dark:text-zinc-100">
        <!-- Ambient background for content area -->
        <div class="pointer-events-none fixed inset-0 hidden dark:block">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(142,37,39,0.07),transparent_50%)]"></div>
        </div>

        <AdminSidebar />

        <!-- Mobile icon rail -->
        <nav class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-zinc-200 bg-white/95 py-2 backdrop-blur-xl lg:hidden dark:border-white/[0.06] dark:bg-[#0c0a09]/95">
            <Link
                v-for="item in mobileNav"
                :key="item.href"
                :href="item.href"
                class="flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-[#8e2527] dark:hover:bg-white/5"
            >
                <component :is="item.icon" class="h-5 w-5" />
            </Link>
        </nav>

        <div class="relative flex min-h-screen flex-col pb-16 lg:pb-0 lg:pl-[264px]">
            <AdminTopbar :breadcrumbs="breadcrumbs" />
            <main class="flex flex-1 flex-col">
                <slot />
            </main>
        </div>
    </div>
</template>
