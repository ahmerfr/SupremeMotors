<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MessageSquareText, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    query_form: Object,
});

const breadcrumbs = [{ title: 'Queries', href: '/admin/query-form' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/query-form', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Queries" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Query Form Submissions" :subtitle="`${query_form.total} queries`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search email…"
                            class="h-10 w-64 rounded-xl border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            @keyup.enter="search"
                        />
                    </div>
                </template>
            </PageHeader>

            <EmptyState v-if="!query_form.data.length" message="No queries yet." :icon="MessageSquareText" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3.5">Company</th>
                            <th class="px-5 py-3.5">Contact</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Phone</th>
                            <th class="px-5 py-3.5 text-right">Received</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="q in query_form.data" :key="q.id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3 font-semibold text-zinc-900 dark:text-white">{{ q.company || '—' }}</td>
                            <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">{{ q.contact_name || '—' }}</td>
                            <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">{{ q.email }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ q.phone || '—' }}</td>
                            <td class="px-5 py-3 text-right text-zinc-500 dark:text-zinc-400">
                                {{ new Date(q.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <Link
                                    :href="`/admin/query-form/view/${q.id}`"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-[#8e2527] hover:text-white dark:text-zinc-400"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination :links="query_form.links" />
        </div>
    </AdminLayout>
</template>
