<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Newspaper, Pencil, Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    blogs: Object,
});

const breadcrumbs = [{ title: 'Blogs', href: '/admin/blogs' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/blogs', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Blogs" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Blog Posts" :subtitle="`${blogs.total} posts`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search posts…"
                            class="h-10 w-64 rounded-xl border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            @keyup.enter="search"
                        />
                    </div>
                    <Link
                        href="/admin/blogs/create"
                        class="flex h-10 items-center gap-2 rounded-xl bg-[#8e2527] px-4 text-sm font-medium text-white transition-colors hover:bg-[#a32c2f]"
                    >
                        <Plus class="h-4 w-4" /> New Post
                    </Link>
                </template>
            </PageHeader>

            <EmptyState v-if="!blogs.data.length" message="No blog posts yet — write your first one." :icon="Newspaper">
                <Link href="/admin/blogs/create" class="mt-2 rounded-xl bg-[#8e2527] px-4 py-2 text-sm font-bold text-white hover:bg-[#a32c2f]">
                    Create Post
                </Link>
            </EmptyState>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="b in blogs.data"
                    :key="b.id"
                    class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="relative h-40 bg-zinc-100 dark:bg-zinc-800">
                        <img v-if="b.cover_image" :src="`/storage/${b.cover_image}`" alt="" class="h-full w-full object-cover" />
                        <span
                            class="absolute left-3 top-3 rounded-md px-2 py-0.5 text-[11px] font-medium"
                            :class="b.publish_status === 'published'
                                ? 'bg-emerald-500 text-white'
                                : 'bg-amber-500 text-white'"
                        >{{ b.publish_status }}</span>
                    </div>
                    <div class="p-4">
                        <h3 class="line-clamp-2 font-bold text-zinc-900 dark:text-white">{{ b.title }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ b.short_description }}</p>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-xs font-medium text-zinc-400">
                                {{ new Date(b.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </span>
                            <Link
                                :href="`/admin/blogs/edit/${b.id}`"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-[#8e2527] hover:text-white"
                            >
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <AdminPagination :links="blogs.links" />
        </div>
    </AdminLayout>
</template>
