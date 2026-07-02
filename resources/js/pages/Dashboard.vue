<script setup lang="ts">
import EmptyState from '@/components/admin/EmptyState.vue';
import LiveClock from '@/components/admin/LiveClock.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Car, Globe, Mail, MessageSquareText, Newspaper, Package, Users } from 'lucide-vue-next';
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
const firstName = computed(() => (page.props.auth?.user?.name || 'Admin').split(' ')[0]);
const hour = new Date().getHours();
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

const imageUrl = (path: string) =>
    path && path.includes('product_images') ? `/storage/${path}` : path;

const metrics = computed(() => [
    { title: 'Total Catalog', value: props.stats.total_products, icon: Package },
    { title: 'Customers', value: props.stats.users, icon: Users },
    { title: 'Subscribers', value: props.stats.newsletter, icon: Mail },
    { title: 'Queries', value: props.stats.queries, icon: MessageSquareText },
    { title: 'Contacts', value: props.stats.contacts, icon: MessageSquareText },
    { title: 'Blogs Live', value: props.stats.published_blogs, icon: Newspaper },
]);

const maxCountry = computed(() => Math.max(...props.by_country.map((c: any) => c.count), 1));
const maxMake = computed(() => Math.max(...props.top_makes.map((m: any) => m.count), 1));
const countryTotal = computed(() => props.by_country.reduce((a: number, c: any) => a + c.count, 0));
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-[1400px] flex-1 px-6 py-8 lg:px-8">
            <!-- Hero band -->
            <div class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-gradient-to-br from-white via-white to-red-50/60 dark:border-white/[0.07] dark:from-zinc-900 dark:via-zinc-900/80 dark:to-[#8e2527]/20">
                <div class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-[#8e2527]/10 blur-3xl dark:bg-[#8e2527]/30"></div>
                <div class="relative grid gap-0 lg:grid-cols-[1fr_auto]">
                    <div class="flex flex-col justify-center px-8 py-8">
                        <div class="font-gauge text-[10px] font-bold uppercase tracking-[0.35em] text-[#8e2527] dark:text-[#e05b5e]">
                            Supreme Motors · Control Deck
                        </div>
                        <h1 class="mt-2 font-display text-[42px] font-black leading-none tracking-tight text-zinc-900 dark:text-white">
                            {{ greeting }}, {{ firstName }}.
                        </h1>
                        <p class="mt-3 max-w-md text-sm font-medium text-zinc-500 dark:text-zinc-400">
                            {{ stats.queries }} open queries and {{ stats.own_products }} live listings under your command.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <Link
                                href="/admin/products/create"
                                class="group flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-[#8e2527] to-[#b93a3d] px-5 font-display text-sm font-bold uppercase tracking-wider text-white shadow-[0_8px_24px_rgba(142,37,39,0.35)] transition-transform hover:-translate-y-0.5"
                            >
                                Add Vehicle <ArrowUpRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                            </Link>
                            <Link
                                href="/admin/query-form"
                                class="flex h-11 items-center rounded-xl border border-zinc-300 px-5 font-display text-sm font-bold uppercase tracking-wider text-zinc-700 transition-colors hover:border-[#8e2527] hover:text-[#8e2527] dark:border-white/15 dark:text-zinc-200 dark:hover:border-[#e05b5e] dark:hover:text-[#e05b5e]"
                            >
                                Review Queries
                            </Link>
                        </div>
                    </div>
                    <!-- Primary gauge -->
                    <div class="flex items-center gap-6 border-t border-zinc-200/80 px-8 py-6 lg:border-l lg:border-t-0 dark:border-white/[0.07]">
                        <div>
                            <div class="font-gauge text-[10px] font-bold uppercase tracking-[0.3em] text-zinc-400 dark:text-zinc-500">Your Listings</div>
                            <div class="font-display text-[64px] font-black leading-none text-[#8e2527] dark:text-[#e05b5e]">
                                {{ stats.own_products }}
                            </div>
                            <div class="font-gauge text-[10px] uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">on suprememotors.ltd</div>
                        </div>
                        <Car class="hidden h-16 w-16 text-zinc-200 xl:block dark:text-white/10" />
                    </div>
                </div>
            </div>

            <!-- World time instrument strip -->
            <div class="mt-6 overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-white/[0.07] dark:bg-zinc-900/60">
                <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-3 dark:border-white/[0.05]">
                    <span class="font-gauge text-[10px] font-bold uppercase tracking-[0.35em] text-zinc-400 dark:text-zinc-500">World Time</span>
                    <span class="flex items-center gap-1.5 font-gauge text-[9px] font-bold uppercase tracking-[0.2em] text-emerald-500">
                        <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span></span>
                        Live
                    </span>
                </div>
                <div class="grid divide-y divide-zinc-100 md:grid-cols-3 md:divide-x md:divide-y-0 dark:divide-white/[0.05]">
                    <LiveClock label="China" flag="🇨🇳" time-zone="Asia/Shanghai" />
                    <LiveClock label="Japan" flag="🇯🇵" time-zone="Asia/Tokyo" />
                    <LiveClock label="Pakistan" flag="🇵🇰" time-zone="Asia/Karachi" />
                </div>
            </div>

            <!-- Metric tiles -->
            <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div
                    v-for="m in metrics"
                    :key="m.title"
                    class="group rounded-2xl border border-zinc-200 bg-white p-4 transition-all hover:-translate-y-0.5 hover:border-[#8e2527]/40 hover:shadow-[0_8px_24px_rgba(142,37,39,0.12)] dark:border-white/[0.07] dark:bg-zinc-900/60 dark:hover:border-[#e05b5e]/40"
                >
                    <div class="flex items-center justify-between">
                        <component :is="m.icon" class="h-4 w-4 text-zinc-300 transition-colors group-hover:text-[#8e2527] dark:text-zinc-600 dark:group-hover:text-[#e05b5e]" />
                    </div>
                    <div class="mt-3 font-display font-black tabular-nums leading-none text-zinc-900 dark:text-white" :class="String(m.value).length > 5 ? 'text-xl' : 'text-3xl'">
                        {{ m.value.toLocaleString() }}
                    </div>
                    <div class="mt-1.5 font-gauge text-[9px] font-bold uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">{{ m.title }}</div>
                </div>
            </div>

            <!-- Feeds -->
            <div class="mt-6 grid gap-6 xl:grid-cols-5">
                <section class="rounded-3xl border border-zinc-200 bg-white xl:col-span-2 dark:border-white/[0.07] dark:bg-zinc-900/60">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-white/[0.05]">
                        <h2 class="font-display text-base font-bold uppercase tracking-wide text-zinc-900 dark:text-white">Incoming Queries</h2>
                        <Link href="/admin/query-form" class="font-gauge text-[10px] font-bold uppercase tracking-[0.2em] text-[#8e2527] hover:underline dark:text-[#e05b5e]">All →</Link>
                    </div>
                    <EmptyState v-if="!recent_queries.length" message="No queries yet." class="m-6" />
                    <ul v-else class="divide-y divide-zinc-100 dark:divide-white/[0.05]">
                        <li v-for="q in recent_queries" :key="q.id">
                            <Link :href="`/admin/query-form/view/${q.id}`" class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-red-50/50 dark:hover:bg-white/[0.03]">
                                <span class="h-8 w-[3px] shrink-0 rounded-full bg-gradient-to-b from-[#b93a3d] to-transparent"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ q.company || q.contact_name }}</div>
                                    <div class="truncate font-gauge text-[11px] text-zinc-400 dark:text-zinc-500">{{ q.email }}</div>
                                </div>
                                <span class="shrink-0 font-gauge text-[10px] uppercase tracking-wider text-zinc-400">
                                    {{ new Date(q.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-zinc-200 bg-white xl:col-span-3 dark:border-white/[0.07] dark:bg-zinc-900/60">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-white/[0.05]">
                        <h2 class="font-display text-base font-bold uppercase tracking-wide text-zinc-900 dark:text-white">Latest In Your Garage</h2>
                        <Link href="/admin/products" class="font-gauge text-[10px] font-bold uppercase tracking-[0.2em] text-[#8e2527] hover:underline dark:text-[#e05b5e]">All →</Link>
                    </div>
                    <EmptyState v-if="!recent_products.length" message="No products yet." class="m-6" />
                    <ul v-else class="divide-y divide-zinc-100 dark:divide-white/[0.05]">
                        <li v-for="p in recent_products" :key="p.id">
                            <Link :href="`/admin/products/edit/${p.id}`" class="flex items-center gap-4 px-6 py-3.5 transition-colors hover:bg-red-50/50 dark:hover:bg-white/[0.03]">
                                <img :src="imageUrl(p.front_image)" alt="" class="h-12 w-[72px] shrink-0 rounded-xl border border-zinc-200 object-cover dark:border-white/10" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ p.title }}</div>
                                    <div class="font-gauge text-[10px] uppercase tracking-[0.15em] text-zinc-400 dark:text-zinc-500">{{ p.stock_code }}</div>
                                </div>
                                <span class="shrink-0 font-gauge text-sm font-bold tabular-nums text-[#8e2527] dark:text-[#e05b5e]">
                                    ${{ Number(p.price).toLocaleString() }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Distribution -->
            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="rounded-3xl border border-zinc-200 bg-white p-6 dark:border-white/[0.07] dark:bg-zinc-900/60">
                    <h2 class="mb-5 flex items-center gap-2 font-display text-base font-bold uppercase tracking-wide text-zinc-900 dark:text-white">
                        <Globe class="h-4 w-4 text-[#8e2527] dark:text-[#e05b5e]" /> Catalog by Country
                    </h2>
                    <div class="space-y-4">
                        <div v-for="c in by_country" :key="c.country">
                            <div class="mb-1.5 flex items-baseline justify-between">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ c.country }}</span>
                                <span class="font-gauge text-[11px] tabular-nums text-zinc-400">
                                    {{ c.count.toLocaleString() }} · {{ Math.round((c.count / countryTotal) * 100) }}%
                                </span>
                            </div>
                            <div class="h-[6px] overflow-hidden rounded-full bg-zinc-100 dark:bg-white/[0.06]">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#8e2527] to-[#e05b5e]" :style="{ width: `${(c.count / maxCountry) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-zinc-200 bg-white p-6 dark:border-white/[0.07] dark:bg-zinc-900/60">
                    <h2 class="mb-5 flex items-center gap-2 font-display text-base font-bold uppercase tracking-wide text-zinc-900 dark:text-white">
                        <Car class="h-4 w-4 text-[#8e2527] dark:text-[#e05b5e]" /> Top Makes
                    </h2>
                    <div class="space-y-4">
                        <div v-for="(m, i) in top_makes" :key="m.cat_title">
                            <div class="mb-1.5 flex items-baseline justify-between">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                    <span class="mr-2 font-gauge text-[10px] text-zinc-300 dark:text-zinc-600">0{{ i + 1 }}</span>{{ m.cat_title }}
                                </span>
                                <span class="font-gauge text-[11px] tabular-nums text-zinc-400">{{ m.count.toLocaleString() }}</span>
                            </div>
                            <div class="h-[6px] overflow-hidden rounded-full bg-zinc-100 dark:bg-white/[0.06]">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#8e2527] to-[#e05b5e]" :style="{ width: `${(m.count / maxMake) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
