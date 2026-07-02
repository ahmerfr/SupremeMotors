<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Mail, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    newsletter: Object,
});

const breadcrumbs = [{ title: 'Newsletter', href: '/admin/newsletter' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/newsletter', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Newsletter" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Newsletter Subscribers" :subtitle="`${newsletter.total} subscribers`">
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

            <EmptyState v-if="!newsletter.data.length" message="No subscribers yet." :icon="Mail" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5 text-right">Subscribed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="s in newsletter.data" :key="s.id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-50 dark:bg-red-950">
                                        <Mail class="h-3.5 w-3.5 text-[#8e2527]" />
                                    </div>
                                    <span class="font-semibold text-zinc-900 dark:text-white">{{ s.email }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right text-zinc-500 dark:text-zinc-400">
                                {{ new Date(s.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination :links="newsletter.links" />
        </div>
    </AdminLayout>
</template>
