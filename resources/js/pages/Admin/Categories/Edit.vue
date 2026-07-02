<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';
import { Image, Upload, CheckCircle } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    category: Object,
});

const breadcrumbs = [
    { title: 'Categories/Makes', href: '/admin/categories' },
    { title: 'Edit', href: '/admin/categories/edit' }
];

const category = ref({
    id: props.category?.id || null,
    title: props.category?.cat_title || '',
    type: props.category?.type || '',
    image: null,
});

const imagePreview = ref(props.category?.image ? `/storage/${props.category.image}` : null);
const errors = ref({});
const showSuccess = ref(false);

// Watch for changes in props.category to update form fields
watch(() => props.category, (newCategory) => {
    category.value.title = newCategory?.cat_title || '';
    category.value.type = newCategory?.type || '';
    imagePreview.value = newCategory?.image ? `/storage/${newCategory.image}` : null;
}, { immediate: true });

const previewImage = (event) => {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = () => {
            imagePreview.value = reader.result;
        };
        reader.readAsDataURL(file);
        category.value.image = file;
        errors.value.image = null;
    }
};

const submitForm = async () => {
    const formData = new FormData();
    formData.append('id', category.value.id);
    formData.append('title', category.value.title);
    formData.append('type', category.value.type);
    if (category.value.image instanceof File) {
        formData.append('image', category.value.image);
    }
    try {
        await axios.post(route('admin.categories.update'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        errors.value = {};
        showSuccess.value = true;
        setTimeout(() => {
            window.location.href = route('admin.categories.index');
        }, 2000);
    } catch (error) {
        showSuccess.value = false;
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else {
            console.error('Error updating category:', error);
        }
    }
};
</script>

<template>
    <Head title="Categories/Makes - Edit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-black flex justify-center p-6">
            <div class="w-full max-w-2xl">
                <h2 class="text-3xl font-extrabold text-center text-white mb-8 tracking-tight">
                    Edit Category/Makes
                </h2>

                <!-- Success Message -->
                <div
                    v-if="showSuccess"
                    class="mb-6 flex items-center justify-center space-x-2 text-green-500 text-lg font-medium animate-fade-in"
                >
                    <CheckCircle class="w-6 h-6" />
                    <span>Category updated successfully!</span>
                </div>

                <form @submit.prevent="submitForm" class="space-y-6" v-if="!showSuccess">
                    <!-- Title Input -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-300">
                            Title
                        </label>
                        <input
                            type="text"
                            id="title"
                            v-model="category.title"
                            class="mt-1 p-3 w-full bg-gray-900 text-white border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#782527] transition duration-300"
                            :class="{ 'border-red-500': errors.title }"
                        />
                        <p v-if="errors.title" class="mt-1 text-sm text-red-500">
                            {{ errors.title[0] }}
                        </p>
                    </div>

                    <!-- Type Input -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-300">
                            Type
                        </label>
                        <select
                            id="type"
                            v-model="category.type"
                            class="mt-1 p-3 w-full bg-gray-900 text-white border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#782527] transition duration-300"
                            :class="{ 'border-red-500': errors.type }"
                        >
                            <option value="" disabled>Select Type</option>
                            <option value="category">Category</option>
                            <option value="make">Make</option>
                        </select>
                        <p v-if="errors.type" class="mt-1 text-sm text-red-500">
                            {{ errors.type[0] }}
                        </p>
                    </div>

                    <!-- Image Input with Set Banner Button -->
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-300 mb-2">
                            Banner Image
                        </label>
                        <div class="mt-1 flex flex-col space-y-4">
                            <!-- Image Preview -->
                            <div class="flex justify-center">
                                <div
                                    class="relative w-full max-w-md h-48 bg-gray-900 border border-gray-700 rounded-lg overflow-hidden transition duration-300 hover:shadow-xl"
                                    :class="{ 'border-dashed': !imagePreview, 'border-red-500': errors.image }"
                                >
                                    <img
                                        v-if="imagePreview"
                                        :src="imagePreview"
                                        alt="Banner Preview"
                                        class="w-full h-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex items-center justify-center h-full text-gray-400"
                                    >
                                        <Image class="w-10 h-10 mr-2" />
                                        <span>No image selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-center space-x-4">
                                <input
                                    type="file"
                                    id="image"
                                    @change="previewImage"
                                    accept="image/*"
                                    class="hidden"
                                />
                                <label
                                    for="image"
                                    class="cursor-pointer flex items-center space-x-2 px-4 py-2 bg-[#782527] text-white rounded-lg hover:bg-[#6c1d1d] transition duration-300 shadow-md hover:shadow-lg"
                                >
                                    <Upload class="w-5 h-5" />
                                    <span>Update Banner</span>
                                </label>
                            </div>
                            <p v-if="errors.image" class="mt-1 text-sm text-red-500">
                                {{ errors.image[0] }}
                            </p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full p-3 bg-[#782527] text-white rounded-lg shadow-md hover:bg-[#6c1d1d] focus:outline-none focus:ring-2 focus:ring-[#782527] transition duration-300"
                        >
                            Update Category/Makes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
form {
    background: transparent;
    padding: 0;
    border-radius: 0;
    box-shadow: none;
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>