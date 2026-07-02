<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import axios from 'axios';
// Import Lucide icons
import { Edit, Trash } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    categories: Object,
});
</script>

<template>
    <Head title="Categories/Makes" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex justify-between mb-4 gap-2">
                <div style="width: 30%; margin-left: auto;">
                    <input
                        style="background-color: #241c1c"
                        type="text"
                        v-model="search"
                        @input="onSearch"
                        placeholder="Search by Title..."
                        class="w-full rounded-lg border p-2"
                    />
                </div>
                <button
                    @click="createNewCategory"
                    class="bg-[#782527] text-white rounded px-4 py-2"
                >
                    Create New
                </button>
            </div>
            <div class="overflow-x-auto rounded-lg shadow-md">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr>
                            <th class="border-b px-4 py-2">Title</th>
                            <th class="border-b px-4 py-2">Type</th> 
                            <th class="border-b px-4 py-2">Created At</th>
                            <th class="border-b px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="category in categories.data" :key="category.id">
                            <td class="flex items-center gap-2 border-b px-4 py-2">
                                <img
                                    v-if="category.image"
                                    :src="'/storage/'+category.image"
                                    alt="Category Image"
                                    class="h-10 w-10 rounded-full object-cover bg-white"
                                />
                                {{ category.cat_title }}
                            </td>
                            <td class="border-b px-4 py-2">
                                <span :class="badgeClass(category.type)" class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ category.type }}
                                </span>
                            </td>
                            <td class="border-b px-4 py-2">{{ new Date(category.created_at).toLocaleDateString() }}</td>
                            <td class="border-b px-4 py-2 text-left">
                                <button @click="updateCategory(category.id)" class="text-yellow-500 hover:text-yellow-700">
                                    <Edit class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :pageData="categories.links" :total="categories.total"/>
        </div>
    </AppLayout>
</template>

<script>
export default {
    data() {
        return {
            search: '',
            currentPage: this.$page.props.categories.current_page,
            breadcrumbs: [{ title: 'Categories/Makes', href: '/admin/categories' }],
        };
    },
    methods: {
        async onSearch() {
            try {
                const response = await axios.get(route('admin.categories.listing'), {
                    params: { keywords: this.search },
                });
                this.$page.props.categories = response.data;
            } catch (error) {
                console.error('Error fetching categories:', error);
                alert('Error fetching category data. Please try again.');
            }
        },
        createNewCategory() {
            window.location.href = '/admin/categories/create';
        },
        updateCategory(categoryId) {
            window.location.href = `/admin/categories/edit/${categoryId}`;
        },
        badgeClass(type) {
            switch (type.toLowerCase()) {
                case 'make':
                    return 'bg-[#782527] text-white';
                case 'category':
                    return 'bg-[#711527] text-white';
            }
        }
    },
};
</script>
<style scoped>
th{
    text-align: justify;
}
</style>