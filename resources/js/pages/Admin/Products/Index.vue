<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Car, Pencil, Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    products: Object,
});

const breadcrumbs = [{ title: 'Products', href: '/admin/products' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/products', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};

const imageUrl = (path) =>
    path && path.startsWith('product_images') ? `/storage/${path}` : path;

const chips = (p) => [p.year, p.fuel, p.transmission, p.mileage_km ? `${Number(p.mileage_km).toLocaleString()} km` : null].filter(Boolean);
</script>

<template>
    <Head title="Products" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Products" :subtitle="`${products.total.toLocaleString()} products in catalogue`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search by title…"
                            class="h-10 w-64 rounded-xl border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            @keyup.enter="search"
                        />
                    </div>
                    <Link
                        href="/admin/products/create"
                        class="flex h-10 items-center gap-2 rounded-xl bg-[#8e2527] px-4 text-sm font-medium text-white transition-colors hover:bg-[#a32c2f]"
                    >
                        <Plus class="h-4 w-4" /> New Product
                    </Link>
                </template>
            </PageHeader>

            <EmptyState v-if="!products.data.length" message="No products found." :icon="Car" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-[11px] font-medium text-zinc-400 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3.5">Product</th>
                            <th class="px-5 py-3.5">Stock ID</th>
                            <th class="px-5 py-3.5">Specs</th>
                            <th class="px-5 py-3.5">Category / Make</th>
                            <th class="px-5 py-3.5 text-right">Price</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="p in products.data" :key="p.id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img :src="imageUrl(p.front_image)" alt="" class="h-11 w-16 shrink-0 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                                    <span class="max-w-64 truncate font-semibold text-zinc-900 dark:text-white">{{ p.title }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 font-gauge text-xs text-zinc-500 dark:text-zinc-400">{{ p.stock_code }}</td>
                            <td class="px-5 py-3">
                                <div class="flex max-w-56 flex-wrap gap-1">
                                    <span
                                        v-for="chip in chips(p)"
                                        :key="chip"
                                        class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                    >{{ chip }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">
                                {{ p.category?.cat_title || '—' }}
                                <span v-if="p.make" class="text-zinc-400 dark:text-zinc-500"> · {{ p.make.cat_title }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-gauge font-bold text-[#8e2527]">${{ Number(p.price).toLocaleString() }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="`/admin/products/edit/${p.id}`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-[#8e2527] hover:text-white dark:text-zinc-400"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination :links="products.links" />
        </div>
    </AdminLayout>
</template>
