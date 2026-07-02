<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import axios from 'axios';
// Import Lucide icons
import { Edit, Trash } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    products: Object,
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
                    @click="createNewProducts"
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
                            <th class="border-b px-4 py-2">Country</th>
                            <th class="border-b px-4 py-2">Category</th>
                            <th class="border-b px-4 py-2">Make</th>
                            <th class="border-b px-4 py-2">Created At</th>
                            <th class="border-b px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products.data" :key="product.id">
                            <td class="flex items-center gap-2 border-b px-4 py-2">
                                <img
                                    v-if="product.front_image"
                                    :src="product.front_image.startsWith('https') ? product.front_image : '/storage/' + product.front_image"
                                    alt="Product Image"
                                    class="h-10 w-10 rounded-full object-cover bg-white"
                                />
                                {{ product.title }}
                            </td>
                            <td class="border-b px-4 py-2">{{ product.country }}</td>
                            <td class="border-b px-4 py-2">{{ product.category.cat_title }}</td>
                            <td class="border-b px-4 py-2">{{ product.make.cat_title }}</td>
                            <td class="border-b px-4 py-2">{{ new Date(product.created_at).toLocaleDateString() }}</td>
                            <td class="border-b px-4 py-2 text-left">
                                <button @click="updateProducts(product.id)" class="text-yellow-500 hover:text-yellow-700">
                                    <Edit class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :pageData="products.links" :total="products.total"/>
        </div>
    </AppLayout>
</template>

<script>
export default {
    data() {
        return {
            search: '',
            currentPage: this.$page.props.products.current_page,
            breadcrumbs: [{ title: 'Products', href: '/admin/products' }],
        };
    },
    methods: {
        async onSearch() {
            try {
                const response = await axios.get(route('admin.products.listing'), {
                    params: { keywords: this.search },
                });
                this.$page.props.products = response.data;
            } catch (error) {
                console.error('Error fetching products:', error);
                alert('Error fetching product data. Please try again.');
            }
        },
        createNewProducts() {
            window.location.href = '/admin/products/create';
        },
        updateProducts(productId) {
            window.location.href = `/admin/products/edit/${productId}`;
        },
    },
};
</script>
<style scoped>
th{
    text-align: justify;
}
</style>