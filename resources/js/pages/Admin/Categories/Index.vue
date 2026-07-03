<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Folder, Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: Object,
    type: { type: String, default: 'category' },
});

const isMake = computed(() => props.type === 'make');
const label = computed(() => (isMake.value ? 'Makes' : 'Categories'));
const base = computed(() => (isMake.value ? '/admin/makes' : '/admin/categories'));

const breadcrumbs = [{ title: label.value, href: base.value }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get(base.value, keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};
</script>

<template>
    <Head :title="label" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader :title="label" :subtitle="`${categories.total} entries`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search…"
                            class="h-10 w-56 rounded-xl border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            @keyup.enter="search"
                        />
                    </div>
                    <Link
                        :href="`${base}/create`"
                        class="flex h-10 items-center gap-2 rounded-xl bg-[#8e2527] px-4 text-sm font-medium text-white transition-colors hover:bg-[#a32c2f]"
                    >
                        <Plus class="h-4 w-4" /> {{ isMake ? 'New Make' : 'New Category' }}
                    </Link>
                </template>
            </PageHeader>

            <EmptyState v-if="!categories.data.length" :message="`No ${label.toLowerCase()} found.`" :icon="Folder" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-[11px] font-medium text-zinc-400 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3.5">{{ isMake ? 'Make' : 'Category' }}</th>
                            <th v-if="!isMake" class="px-5 py-3.5">Parent</th>
                            <th class="px-5 py-3.5 text-right">Products</th>
                            <th class="px-5 py-3.5">Added</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="c in categories.data" :key="c.id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                                        <img v-if="c.image" :src="`/storage/${c.image}`" alt="" class="h-7 w-7 object-contain" />
                                        <Folder v-else class="h-4 w-4 text-zinc-400" />
                                    </div>
                                    <span class="font-semibold text-zinc-900 dark:text-white">{{ c.cat_title }}</span>
                                </div>
                            </td>
                            <td v-if="!isMake" class="px-5 py-3">
                                <span
                                    v-if="c.parent"
                                    class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                >{{ c.parent.cat_title }}</span>
                                <span v-else class="rounded-md bg-red-100 px-1.5 py-0.5 text-[11px] font-semibold text-[#8e2527] dark:bg-red-950 dark:text-red-300">Top level</span>
                            </td>
                            <td class="px-5 py-3 text-right font-gauge text-zinc-600 dark:text-zinc-300">{{ Number(c.products_count || 0).toLocaleString() }}</td>
                            <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400">{{ c.created_at ? new Date(c.created_at).toLocaleDateString() : '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="`${base}/edit/${c.id}`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-[#8e2527] hover:text-white dark:text-zinc-400"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination :links="categories.links" />
        </div>
    </AdminLayout>
</template>
