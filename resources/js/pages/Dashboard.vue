<script setup lang="ts">
import EmptyState from '@/components/admin/EmptyState.vue';
import LiveClock from '@/components/admin/LiveClock.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    recent_queries: Array,
    recent_products: Array,
    by_country: Array,
    top_makes: Array,
});

const breadcrumbs = [{ title: 'Dashboard', href: '/admin/dashboard' }];

const page = usePage();
const firstName = computed(() => (page.props.auth?.user?.name || 'there').split(' ')[0]);
const hour = new Date().getHours();
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

const imageUrl = (path: string) =>
    path && path.includes('product_images') ? `/storage/${path}` : path;

const metrics = computed(() => [
    { title: 'Your listings', value: props.stats.own_products, accent: true },
    { title: 'Total catalog', value: props.stats.total_products },
    { title: 'Customers', value: props.stats.users },
    { title: 'Subscribers', value: props.stats.newsletter },
    { title: 'Queries', value: props.stats.queries },
    { title: 'Contacts', value: props.stats.contacts },
    { title: 'Published blogs', value: props.stats.published_blogs },
]);

const maxCountry = computed(() => Math.max(...props.by_country.map((c: any) => c.count), 1));
const maxMake = computed(() => Math.max(...props.top_makes.map((m: any) => m.count), 1));
const countryTotal = computed(() => props.by_country.reduce((a: number, c: any) => a + c.count, 0));

const panel = 'rounded-2xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)] dark:border-white/[0.07] dark:bg-[#161616]';
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-[1240px] flex-1 px-6 py-10 lg:px-10">
            <!-- Header -->
            <div class="admin-reveal flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-[28px] font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ greeting }}, {{ firstName }}
                    </h1>
                    <p class="mt-1 text-[14px] text-zinc-500 dark:text-zinc-400">
                        You have {{ stats.queries }} open queries and {{ stats.own_products }} live listings.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/admin/query-form"
                        class="flex h-9 items-center rounded-lg border border-zinc-200 px-4 text-[13px] font-medium text-zinc-700 transition-colors duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-zinc-100 active:scale-[0.98] dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/[0.06]"
                    >
                        Review queries
                    </Link>
                    <Link
                        href="/admin/products/create"
                        class="flex h-9 items-center rounded-lg bg-[#8e2527] px-4 text-[13px] font-medium text-white transition-all duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-[#7a1f21] active:scale-[0.98]"
                    >
                        Add vehicle
                    </Link>
                </div>
            </div>

            <!-- World time -->
            <div class="admin-reveal mt-8 grid divide-y divide-zinc-100 md:grid-cols-3 md:divide-x md:divide-y-0 dark:divide-white/[0.06]" :class="panel" style="animation-delay: 60ms">
                <LiveClock label="China" time-zone="Asia/Shanghai" />
                <LiveClock label="Japan" time-zone="Asia/Tokyo" />
                <LiveClock label="Pakistan" time-zone="Asia/Karachi" />
            </div>

            <!-- Metrics -->
            <div class="admin-reveal mt-4 grid grid-cols-2 divide-zinc-100 md:grid-cols-4 xl:grid-cols-7 xl:divide-x dark:divide-white/[0.06]" :class="panel" style="animation-delay: 120ms">
                <div v-for="m in metrics" :key="m.title" class="px-6 py-5">
                    <div
                        class="font-gauge font-medium leading-none tabular-nums"
                        :class="[
                            m.accent ? 'text-[#8e2527] dark:text-[#d96a6c]' : 'text-zinc-900 dark:text-white',
                            String(m.value.toLocaleString()).length > 6 ? 'text-[20px]' : 'text-[24px]',
                        ]"
                    >
                        {{ m.value.toLocaleString() }}
                    </div>
                    <div class="mt-1.5 text-[12px] text-zinc-500 dark:text-zinc-400">{{ m.title }}</div>
                </div>
            </div>

            <!-- Feeds -->
            <div class="admin-reveal mt-4 grid gap-4 xl:grid-cols-2" style="animation-delay: 180ms">
                <section :class="panel">
                    <div class="flex items-center justify-between px-6 pb-1 pt-5">
                        <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">Recent queries</h2>
                        <Link href="/admin/query-form" class="text-[13px] font-medium text-zinc-400 transition-colors duration-300 hover:text-[#8e2527] dark:text-zinc-500 dark:hover:text-[#d96a6c]">View all</Link>
                    </div>
                    <EmptyState v-if="!recent_queries.length" message="No queries yet." class="m-6" />
                    <ul v-else class="divide-y divide-zinc-100 px-3 pb-3 pt-1 dark:divide-white/[0.05]">
                        <li v-for="q in recent_queries" :key="q.id">
                            <Link :href="`/admin/query-form/view/${q.id}`" class="flex items-center gap-3 rounded-lg px-3 py-3 transition-colors duration-300 hover:bg-zinc-50 dark:hover:bg-white/[0.03]">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[14px] font-medium text-zinc-900 dark:text-white">{{ q.company || q.contact_name }}</div>
                                    <div class="truncate text-[13px] text-zinc-400 dark:text-zinc-500">{{ q.email }}</div>
                                </div>
                                <span class="shrink-0 text-[12px] text-zinc-400 dark:text-zinc-500">
                                    {{ new Date(q.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section :class="panel">
                    <div class="flex items-center justify-between px-6 pb-1 pt-5">
                        <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">Recent listings</h2>
                        <Link href="/admin/products" class="text-[13px] font-medium text-zinc-400 transition-colors duration-300 hover:text-[#8e2527] dark:text-zinc-500 dark:hover:text-[#d96a6c]">View all</Link>
                    </div>
                    <EmptyState v-if="!recent_products.length" message="No products yet." class="m-6" />
                    <ul v-else class="divide-y divide-zinc-100 px-3 pb-3 pt-1 dark:divide-white/[0.05]">
                        <li v-for="p in recent_products" :key="p.id">
                            <Link :href="`/admin/products/edit/${p.id}`" class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors duration-300 hover:bg-zinc-50 dark:hover:bg-white/[0.03]">
                                <img :src="imageUrl(p.front_image)" alt="" class="h-10 w-14 shrink-0 rounded-md object-cover" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[14px] font-medium text-zinc-900 dark:text-white">{{ p.title }}</div>
                                    <div class="font-gauge text-[12px] text-zinc-400 dark:text-zinc-500">{{ p.stock_code }}</div>
                                </div>
                                <span class="shrink-0 font-gauge text-[13px] font-medium tabular-nums text-zinc-700 dark:text-zinc-300">
                                    ${{ Number(p.price).toLocaleString() }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Distribution -->
            <div class="admin-reveal mt-4 grid gap-4 xl:grid-cols-2" style="animation-delay: 240ms">
                <section class="p-6" :class="panel">
                    <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">Catalog by country</h2>
                    <div class="mt-5 space-y-4">
                        <div v-for="c in by_country" :key="c.country">
                            <div class="mb-1.5 flex items-baseline justify-between">
                                <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ c.country }}</span>
                                <span class="font-gauge text-[12px] tabular-nums text-zinc-400 dark:text-zinc-500">
                                    {{ c.count.toLocaleString() }} ({{ Math.round((c.count / countryTotal) * 100) }}%)
                                </span>
                            </div>
                            <div class="h-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/[0.07]">
                                <div class="h-full rounded-full bg-[#8e2527]/80" :style="{ width: `${(c.count / maxCountry) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="p-6" :class="panel">
                    <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">Top makes</h2>
                    <div class="mt-5 space-y-4">
                        <div v-for="m in top_makes" :key="m.cat_title">
                            <div class="mb-1.5 flex items-baseline justify-between">
                                <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ m.cat_title }}</span>
                                <span class="font-gauge text-[12px] tabular-nums text-zinc-400 dark:text-zinc-500">{{ m.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/[0.07]">
                                <div class="h-full rounded-full bg-[#8e2527]/80" :style="{ width: `${(m.count / maxMake) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
