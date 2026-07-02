<script setup>
import AdminPagination from '@/components/admin/AdminPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Search, Users } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

const props = defineProps({
    users: Object,
});

const breadcrumbs = [{ title: 'Users', href: '/admin/users' }];
const params = new URLSearchParams(window.location.search);
const keywords = ref(params.get('keywords') || '');
const activeRole = ref(params.get('role') || '');
const me = usePage().props.auth?.user ?? {};

const tabs = [
    { value: '', label: 'All' },
    { value: 'admin', label: 'Admins' },
    { value: 'editor', label: 'Editors' },
    { value: 'user', label: 'Customers' },
];

const roleStyles = {
    admin: 'bg-red-50 text-[#8e2527] dark:bg-red-950/60 dark:text-[#d96a6c]',
    editor: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    user: 'bg-zinc-100 text-zinc-600 dark:bg-white/[0.07] dark:text-zinc-300',
};

const reload = () => {
    const query = {};
    if (keywords.value) query.keywords = keywords.value;
    if (activeRole.value) query.role = activeRole.value;
    router.get('/admin/users', query, { preserveState: true });
};

const setTab = (value) => {
    activeRole.value = value;
    reload();
};

// Per-row saving/saved/error feedback for role changes
const rowState = reactive({});

const changeRole = async (user, event) => {
    const role = event.target.value;
    rowState[user.id] = { saving: true, error: null };
    try {
        await axios.patch(`/admin/users/${user.id}/role`, { role });
        user.role = role;
        rowState[user.id] = { saving: false, saved: true };
        setTimeout(() => (rowState[user.id] = {}), 1800);
    } catch (e) {
        event.target.value = user.role;
        rowState[user.id] = { saving: false, error: e.response?.data?.message || 'Failed to update role.' };
    }
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
        <div class="flex h-full flex-1 flex-col p-6 lg:px-10">
            <PageHeader title="Users & roles" :subtitle="`${users.total} accounts`">
                <template #actions>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" :stroke-width="1.5" />
                        <input
                            v-model="keywords"
                            type="text"
                            placeholder="Search name or email"
                            class="h-10 w-64 rounded-lg border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-white/10 dark:bg-[#161616] dark:text-white"
                            @keyup.enter="reload"
                        />
                    </div>
                </template>
            </PageHeader>

            <!-- Role tabs -->
            <div class="mb-5 flex w-max items-center gap-1 rounded-xl border border-zinc-200 bg-white p-1 dark:border-white/[0.07] dark:bg-[#161616]">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors duration-300"
                    :class="activeRole === tab.value
                        ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                        : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                    @click="setTab(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <EmptyState v-if="!users.data.length" message="No users found." :icon="Users" />

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)] dark:border-white/[0.07] dark:bg-[#161616]">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-[12px] font-medium text-zinc-400 dark:border-white/[0.07] dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3.5">User</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Role</th>
                            <th class="px-5 py-3.5">Change role</th>
                            <th class="px-5 py-3.5 text-right">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-white/[0.05]">
                        <tr v-for="u in users.data" :key="u.id" class="transition-colors duration-300 hover:bg-zinc-50 dark:hover:bg-white/[0.03]">
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
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-[11px] font-semibold text-white dark:bg-white dark:text-zinc-900"
                                    >{{ initials(u.name) }}</div>
                                    <span class="text-[15px] font-medium text-zinc-900 dark:text-white">{{ u.name }}</span>
                                    <span v-if="u.id === me.id" class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-500 dark:bg-white/[0.07] dark:text-zinc-400">You</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400">{{ u.email }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-md px-2 py-0.5 text-[12px] font-medium capitalize" :class="roleStyles[u.role] || roleStyles.user">
                                    {{ u.role === 'user' ? 'customer' : u.role }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div v-if="u.id !== me.id" class="flex items-center gap-2">
                                    <select
                                        :value="u.role"
                                        class="h-8 rounded-lg border border-zinc-200 bg-white px-2 text-[13px] text-zinc-700 focus:border-[#8e2527] focus:outline-none dark:border-white/10 dark:bg-[#121212] dark:text-zinc-200"
                                        :disabled="rowState[u.id]?.saving"
                                        @change="changeRole(u, $event)"
                                    >
                                        <option value="user">Customer</option>
                                        <option value="editor">Editor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <span v-if="rowState[u.id]?.saving" class="text-[12px] text-zinc-400">Saving…</span>
                                    <span v-else-if="rowState[u.id]?.saved" class="text-[12px] font-medium text-emerald-600 dark:text-emerald-400">Saved</span>
                                    <span v-else-if="rowState[u.id]?.error" class="text-[12px] text-red-500">{{ rowState[u.id].error }}</span>
                                </div>
                                <span v-else class="text-[12px] text-zinc-400 dark:text-zinc-500">Locked</span>
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
