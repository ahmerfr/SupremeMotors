<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Search, Users } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    users: Object,
});

const breadcrumbs = [{ title: 'Users', href: '/admin/users' }];
const keywords = ref(new URLSearchParams(window.location.search).get('keywords') || '');

const search = () => {
    router.get('/admin/users', keywords.value ? { keywords: keywords.value } : {}, { preserveState: true });
};

const initials = (name) =>
    (name || '?')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
</script>

<template>
    <Head title="Users" />
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col p-6">
            <PageHeader title="Users" :subtitle="`${users.total} registered customers`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search name or email…"
                            class="h-10 w-64 rounded-xl border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            @keyup.enter="search"
                        />
                    </div>
                </template>
            </PageHeader>

            <EmptyState v-if="!users.data.length" message="No users found." :icon="Users" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-[11px] font-medium text-zinc-400 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3.5">User</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Verified</th>
                            <th class="px-5 py-3.5 text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="u in users.data" :key="u.id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="u.profile_picture"
                                        :src="u.profile_picture"
                                        alt=""
                                        class="h-9 w-9 shrink-0 rounded-full object-cover"
                                        referrerpolicy="no-referrer"
                                    />
                                    <div
                                        v-else
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#8e2527] text-xs font-black text-white"
                                    >{{ initials(u.name) }}</div>
                                    <span class="font-semibold text-zinc-900 dark:text-white">{{ u.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">{{ u.email }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="u.email_verified_at
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                                >{{ u.email_verified_at ? 'Verified' : 'Pending' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right text-zinc-500 dark:text-zinc-400">
                                {{ new Date(u.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdminPagination :links="users.links" />
        </div>
    </AdminLayout>
</template>
