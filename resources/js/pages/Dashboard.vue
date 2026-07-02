<script setup lang="ts">
import EmptyState from '@/components/admin/EmptyState.vue';
import LiveClock from '@/components/admin/LiveClock.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Car, Globe, Mail, MessageSquareText, Newspaper, Package, Users } from 'lucide-vue-next';

defineProps({
    stats: Object,
    recent_queries: Array,
    recent_products: Array,
    by_country: Array,
    top_makes: Array,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
];

const imageUrl = (path: string) =>
    path && path.includes('product_images') ? `/storage/${path}` : path;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <PageHeader title="Command Center" subtitle="Live overview of Supreme Motors" />

            <!-- Live clocks -->
            <div class="grid gap-4 md:grid-cols-3">
                <LiveClock label="China" flag="🇨🇳" time-zone="Asia/Shanghai" />
                <LiveClock label="Japan" flag="🇯🇵" time-zone="Asia/Tokyo" />
                <LiveClock label="Pakistan" flag="🇵🇰" time-zone="Asia/Karachi" />
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
                <StatCard title="Your Products" :value="stats.own_products" :icon="Car" accent />
                <StatCard title="Total Catalog" :value="stats.total_products" :icon="Package" />
                <StatCard title="Users" :value="stats.users" :icon="Users" />
                <StatCard title="Subscribers" :value="stats.newsletter" :icon="Mail" />
                <StatCard title="Queries" :value="stats.queries" :icon="MessageSquareText" />
                <StatCard title="Contacts" :value="stats.contacts" :icon="MessageSquareText" />
                <StatCard title="Blogs Live" :value="stats.published_blogs" :icon="Newspaper" />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <!-- Recent queries -->
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Latest Queries</h2>
                        <Link href="/admin/query-form" class="text-xs font-bold uppercase tracking-wider text-[#8e2527] hover:underline">View all</Link>
                    </div>
                    <EmptyState v-if="!recent_queries.length" message="No queries yet." />
                    <ul v-else class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <li v-for="q in recent_queries" :key="q.id" class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <Link :href="`/admin/query-form/view/${q.id}`" class="block truncate font-semibold text-zinc-900 hover:text-[#8e2527] dark:text-white">
                                    {{ q.company || q.contact_name }}
                                </Link>
                                <span class="block truncate text-sm text-zinc-500 dark:text-zinc-400">{{ q.email }}</span>
                            </div>
                            <span class="shrink-0 text-xs font-medium text-zinc-400">
                                {{ new Date(q.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }}
                            </span>
                        </li>
                    </ul>
                </section>

                <!-- Recent products -->
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Your Latest Products</h2>
                        <Link href="/admin/products" class="text-xs font-bold uppercase tracking-wider text-[#8e2527] hover:underline">View all</Link>
                    </div>
                    <EmptyState v-if="!recent_products.length" message="No products yet." />
                    <ul v-else class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <li v-for="p in recent_products" :key="p.id" class="flex items-center gap-3 py-3">
                            <img
                                :src="imageUrl(p.front_image)"
                                alt=""
                                class="h-11 w-16 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700"
                            />
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/products/edit/${p.id}`" class="block truncate font-semibold text-zinc-900 hover:text-[#8e2527] dark:text-white">
                                    {{ p.title }}
                                </Link>
                                <span class="font-mono text-xs text-zinc-400">{{ p.stock_code }}</span>
                            </div>
                            <span class="shrink-0 font-mono text-sm font-bold text-[#8e2527]">
                                ${{ Number(p.price).toLocaleString() }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <!-- Products by country -->
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-zinc-900 dark:text-white">
                        <Globe class="h-4 w-4 text-[#8e2527]" /> Catalog by Country
                    </h2>
                    <div class="space-y-3">
                        <div v-for="c in by_country" :key="c.country">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ c.country }}</span>
                                <span class="font-mono tabular-nums text-zinc-500 dark:text-zinc-400">{{ c.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    class="h-full rounded-full bg-[#8e2527]"
                                    :style="{ width: `${(c.count / Math.max(...by_country.map(x => x.count))) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Top makes -->
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-zinc-900 dark:text-white">
                        <Car class="h-4 w-4 text-[#8e2527]" /> Top Makes
                    </h2>
                    <div class="space-y-3">
                        <div v-for="m in top_makes" :key="m.cat_title">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ m.cat_title }}</span>
                                <span class="font-mono tabular-nums text-zinc-500 dark:text-zinc-400">{{ m.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    class="h-full rounded-full bg-[#8e2527]"
                                    :style="{ width: `${(m.count / Math.max(...top_makes.map(x => x.count))) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
