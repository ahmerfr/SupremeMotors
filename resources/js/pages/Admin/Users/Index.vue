<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import axios from 'axios';

const props = defineProps({
    auth: Object,
    users: Object,
});
</script>

<template>
    <Head title="Users" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="mb-4" style="width: 30%;margin-left: auto;">
                <input
                    style="background-color: #241c1c"
                    type="text"
                    v-model="search"
                    @input="onSearch"
                    placeholder="Search by name or email..."
                    class="w-full rounded-lg border p-2"
                />
            </div>
            <div class="overflow-x-auto rounded-lg shadow-md">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr>
                            <th class="border-b px-4 py-2">Name</th>
                            <th class="border-b px-4 py-2">Email</th>
                            <th class="border-b px-4 py-2">Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="flex items-center gap-2 border-b px-4 py-2">
                                <img
                                    v-if="user.profile_picture"
                                    :src="user.profile_picture"
                                    alt="Profile Picture"
                                    class="h-10 w-10 rounded-full object-cover"
                                />
                                {{ user.name }}
                            </td>
                            <td class="border-b px-4 py-2">{{ user.email }}</td>
                            <td class="border-b px-4 py-2">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :pageData="users.links" :total="users.total"/>

        </div>
    </AppLayout>
</template>

<script>
export default {
    data() {
        return {
            search: '',
            currentPage: this.$page.props.users.current_page, 
            breadcrumbs: [{ title: 'Users', href: '/admin/users' }],
        };
    },
    methods: {
        async onSearch() {
            try {
                const response = await axios.get(route('admin.users.listing'), {
                    params: { keywords: this.search },
                });
                this.$page.props.users = response.data;
            } catch (error) {
                console.error('Error fetching users:', error);
                alert('Error fetching user data. Please try again.');
            }
        },
    },
};
</script>
