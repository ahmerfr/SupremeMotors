<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Folder, Pencil, Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    categories: Object,
});

const breadcrumbs = [{ title: 'Categories / Makes', href: '/admin/categories' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/categories', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Categories" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Categories & Makes" :subtitle="`${categories.total} entries`">
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
                        href="/admin/categories/create"
                        class="flex h-10 items-center gap-2 rounded-xl bg-[#8e2527] px-4 text-sm font-bold text-white transition-colors hover:bg-[#a32c2f]"
                    >
                        <Plus class="h-4 w-4" /> New Entry
                    </Link>
                </template>
            </PageHeader>

            <EmptyState v-if="!categories.data.length" message="No categories found." :icon="Folder" />

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div
                    v-for="c in categories.data"
                    :key="c.id"
                    class="group relative rounded-2xl border border-zinc-200 bg-white p-4 transition-all hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800">
                            <img v-if="c.image" :src="`/storage/${c.image}`" alt="" class="h-10 w-10 object-contain" />
                            <Folder v-else class="h-6 w-6 text-zinc-400" />
                        </div>
                        <div class="min-w-0">
                            <div class="truncate font-bold text-zinc-900 dark:text-white">{{ c.cat_title }}</div>
                            <span
                                class="mt-1 inline-block rounded-md px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                :class="c.type === 'make'
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                                    : 'bg-red-100 text-[#8e2527] dark:bg-red-950 dark:text-red-300'"
                            >{{ c.type }}</span>
                        </div>
                    </div>
                    <Link
                        :href="`/admin/categories/edit/${c.id}`"
                        class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-lg text-zinc-400 opacity-0 transition-all hover:bg-[#8e2527] hover:text-white group-hover:opacity-100"
                    >
                        <Pencil class="h-4 w-4" />
                    </Link>
                </div>
            </div>

            <AdminPagination :links="categories.links" />
        </div>
    </AdminLayout>
</template>
