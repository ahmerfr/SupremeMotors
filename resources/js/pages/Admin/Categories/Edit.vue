<script setup>
import PageHeader from '@/components/admin/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle, Image as ImageIcon, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    category: Object,
});

const breadcrumbs = [
    { title: 'Categories/Makes', href: '/admin/categories' },
    { title: 'Edit', href: `/admin/categories/edit/${props.category.id}` },
];

const category = ref({
    title: props.category.cat_title || '',
    type: props.category.type || '',
    image: null,
});
const imagePreview = ref(props.category.image ? `/storage/${props.category.image}` : null);
const errors = ref({});
const showSuccess = ref(false);
const isLoading = ref(false);

const previewImage = (event) => {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = () => (imagePreview.value = reader.result);
        reader.readAsDataURL(file);
        category.value.image = file;
        errors.value.image = null;
    }
};

const submitForm = async () => {
    isLoading.value = true;
    const formData = new FormData();
    formData.append('id', props.category.id);
    formData.append('title', category.value.title);
    formData.append('type', category.value.type);
    if (category.value.image) formData.append('image', category.value.image);

    try {
        await axios.post(route('admin.categories.update'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        errors.value = {};
        showSuccess.value = true;
        setTimeout(() => (window.location.href = route('admin.categories.index')), 1500);
    } catch (error) {
        if (error.response?.status === 422) errors.value = error.response.data.errors;
    } finally {
        isLoading.value = false;
    }
};

const inputClass =
    'mt-1 h-11 w-full rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white';
</script>

<template>
    <Head title="Categories - Edit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-6">
            <PageHeader :title="`Edit: ${props.category.cat_title}`" subtitle="Update category or make details" />

            <div v-if="showSuccess" class="mb-6 flex items-center gap-2 rounded-2xl bg-emerald-50 p-4 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                <CheckCircle class="h-5 w-5" /> Updated successfully — redirecting…
            </div>

            <form v-else class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="submitForm">
                <div>
                    <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Title</label>
                    <input v-model="category.title" type="text" :class="[inputClass, errors.title && 'border-red-500']" />
                    <p v-if="errors.title" class="mt-1 text-sm text-red-500">{{ errors.title[0] }}</p>
                </div>

                <div>
                    <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Type</label>
                    <select v-model="category.type" :class="[inputClass, errors.type && 'border-red-500']">
                        <option value="" disabled>Select type</option>
                        <option value="category">Category</option>
                        <option value="make">Make</option>
                    </select>
                    <p v-if="errors.type" class="mt-1 text-sm text-red-500">{{ errors.type[0] }}</p>
                </div>

                <div>
                    <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Logo / Image</label>
                    <label
                        class="mt-1 flex h-40 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 transition-colors hover:border-[#8e2527] dark:border-zinc-700"
                    >
                        <img v-if="imagePreview" :src="imagePreview" alt="" class="h-full w-full rounded-xl object-contain p-2" />
                        <template v-else>
                            <ImageIcon class="h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                            <span class="text-sm text-zinc-500">Click to upload</span>
                        </template>
                        <input type="file" accept="image/*" class="hidden" @change="previewImage" />
                    </label>
                    <p v-if="errors.image" class="mt-1 text-sm text-red-500">{{ errors.image[0] }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="isLoading"
                    class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#8e2527] font-bold text-white transition-colors hover:bg-[#a32c2f] disabled:opacity-60"
                >
                    <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                    Save Changes
                </button>
            </form>
        </div>
    </AppLayout>
</template>
