<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Image, Upload, CheckCircle } from 'lucide-vue-next';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
// Props and other reactive data
const props = defineProps({
    auth: Object,
    categories: Array,
});

const product = ref({
    title: '',
    category_id: '',
    make_id: '',
    price: '',
    country: '',
    model: '',
    model_code: '',
    year: '',
    engine_cc: '',
    mileage_km: '',
    fuel: '',
    transmission: '',
    condition: '',
    color: '',
    steering: '',
    seats: '',
    doors: '',
    axles: '',
    load_capacity_kg: '',
    power_hp: '',
    running_hours: '',
    emission_standard: '',
    drive_type: '',
    front_image: null,
    other_images: [],
    product_details: '',
});

const attributeFields = [
    { key: 'model', label: 'Model', type: 'text' },
    { key: 'model_code', label: 'Model Code', type: 'text' },
    { key: 'year', label: 'Year', type: 'number' },
    { key: 'engine_cc', label: 'Engine (cc)', type: 'number' },
    { key: 'mileage_km', label: 'Mileage (km)', type: 'number' },
    { key: 'seats', label: 'Seats', type: 'number' },
    { key: 'doors', label: 'Doors', type: 'number' },
    { key: 'axles', label: 'Axles', type: 'number' },
    { key: 'load_capacity_kg', label: 'Load Capacity (kg)', type: 'number' },
    { key: 'power_hp', label: 'Power (HP)', type: 'number' },
    { key: 'running_hours', label: 'Running Hours', type: 'number' },
    { key: 'emission_standard', label: 'Emission', type: 'select', options: ['Euro 1', 'Euro 2', 'Euro 3', 'Euro 4', 'Euro 5', 'Euro 6'] },
    { key: 'fuel', label: 'Fuel', type: 'select', options: ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'LPG', 'CNG'] },
    { key: 'transmission', label: 'Transmission', type: 'select', options: ['Automatic', 'Manual', 'CVT', 'Semi-Automatic'] },
    { key: 'condition', label: 'Condition', type: 'select', options: ['Used', 'New'] },
    { key: 'steering', label: 'Steering', type: 'select', options: ['Right', 'Left'] },
    { key: 'drive_type', label: 'Drive Type', type: 'select', options: ['2WD', '4WD', 'AWD', 'FWD', 'RWD', '6x4', '8x4'] },
    { key: 'color', label: 'Color', type: 'text' },
];

const description_editor = ref(null);
const frontImagePreview = ref(null);
const otherImagesPreview = ref([]);
const errors = ref({});
const showSuccess = ref(false);
const isLoading = ref(false);

// Initialize the description editor
const init_description_editor = () => {
    const desc_editor = new Quill('#description', {
        modules: {
            toolbar: [
                [{ header: [1, 2, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
            ],
        },
        theme: 'snow',
    });
    description_editor.value = desc_editor;
};

onMounted(() => {
    init_description_editor();
});

const previewFrontImage = (event) => {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = () => {
            frontImagePreview.value = reader.result;
        };
        reader.readAsDataURL(file);
        product.value.front_image = file;
        errors.value.front_image = null;
    }
};

const previewOtherImages = (event) => {
    const files = Array.from(event.target.files);
    files.forEach((file) => {
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = () => {
                otherImagesPreview.value.push(reader.result);
                product.value.other_images.push(file);
                errors.value.other_images = null;
            };
            reader.readAsDataURL(file);
        }
    });
};

const removeOtherImage = (index) => {
    otherImagesPreview.value.splice(index, 1);
    product.value.other_images.splice(index, 1);
};

const submitForm = async () => {
    isLoading.value = true;
    product.value.product_details = description_editor.value.getSemanticHTML();

    const formData = new FormData();
    formData.append('title', product.value.title);
    formData.append('category_id', product.value.category_id);
    formData.append('make_id', product.value.make_id);
    formData.append('price', product.value.price);
    formData.append('country', product.value.country);
    attributeFields.forEach(({ key }) => {
        if (product.value[key] !== '' && product.value[key] !== null) {
            formData.append(key, product.value[key]);
        }
    });
    formData.append('front_image', product.value.front_image);
    product.value.other_images.forEach((image, index) => {
        formData.append(`other_images[${index}]`, image);
    });
    formData.append('product_details', product.value.product_details);

    try {
        await axios.post(route('admin.products.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        errors.value = {};
        showSuccess.value = true; // Show success message
        isLoading.value = false;
        setTimeout(() => {
            window.location.href = route('admin.products.index');
        }, 2000);
    } catch (error) {
        isLoading.value = false;
        showSuccess.value = false;
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else {
            console.error('Error submitting form:', error);
        }
    }
};

const categoriesList = props.categories.filter(item => item.type === 'category');
const makesList = props.categories.filter(item => item.type === 'make');
</script>


<template>
    <Head title="Products - Create" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen flex justify-center p-4">
            <div class="w-full max-w-6xl">
                <h2 class="text-3xl font-black text-center text-zinc-900 dark:text-white mb-8 tracking-tight">
                    Create New Product
                </h2>

                <!-- Success Message -->
                <div
                    v-if="showSuccess"
                    class="mb-6 flex items-center justify-center space-x-2 text-green-500 text-lg font-medium animate-fade-in"
                >
                    <CheckCircle class="w-6 h-6" />
                    <span>Product created successfully!</span>
                </div>

                <form @submit.prevent="submitForm" v-if="!showSuccess">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label for="title" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                    Title
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    v-model="product.title"
                                    class="mt-1 p-3 w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                    :class="{ 'border-red-500': errors.title }"
                                />
                                <p v-if="errors.title" class="mt-1 text-sm text-red-500">
                                    {{ errors.title[0] }}
                                </p>
                            </div>

                            <!-- Product Details Input (Quill Editor) -->
                            <div>
                                <label for="product_details" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-2">
                                    Product Details
                                </label>
                                <div id="description" style="height: 250px;"></div>
                                <p v-if="errors.product_details" class="mt-1 text-sm text-red-500">
                                    {{ errors.product_details[0] }}
                                </p>
                            </div>

                            <!-- Category Dropdown -->
                            <div>
                                <label for="category_id" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                    Category
                                </label>
                                <select
                                    id="category_id"
                                    v-model="product.category_id"
                                    class="mt-1 p-3 w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                    :class="{ 'border-red-500': errors.category_id }"
                                >
                                    <option value="" disabled>Select Category</option>
                                    <option v-for="category in categoriesList" :key="category.id" :value="category.id">
                                        {{ category.cat_title }}
                                    </option>
                                </select>
                                <p v-if="errors.category_id" class="mt-1 text-sm text-red-500">
                                    {{ errors.category_id[0] }}
                                </p>
                            </div>

                            <!-- Make Dropdown -->
                            <div>
                                <label for="make_id" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                    Make
                                </label>
                                <select
                                    id="make_id"
                                    v-model="product.make_id"
                                    class="mt-1 p-3 w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                    :class="{ 'border-red-500': errors.make_id }"
                                >
                                    <option value="" disabled>Select Make</option>
                                    <option v-for="make in makesList" :key="make.id" :value="make.id">
                                        {{ make.cat_title }}
                                    </option>
                                </select>
                                <p v-if="errors.make_id" class="mt-1 text-sm text-red-500">
                                    {{ errors.make_id[0] }}
                                </p>
                            </div>

                            <!-- Price Input -->
                            <div>
                                <label for="price" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                    Price (USD)
                                </label>
                                <input
                                    type="number"
                                    id="price"
                                    v-model="product.price"
                                    class="mt-1 p-3 w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                    :class="{ 'border-red-500': errors.price }"
                                />
                                <p v-if="errors.price" class="mt-1 text-sm text-red-500">
                                    {{ errors.price[0] }}
                                </p>
                            </div>

                            <!-- Country Dropdown -->
                            <div>
                                <label for="country" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                    Country
                                </label>
                                <select
                                    id="country"
                                    v-model="product.country"
                                    class="mt-1 p-3 w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                    :class="{ 'border-red-500': errors.country }"
                                >
                                    <option value="" disabled>Select Country</option>
                                    <option value="China">China</option>
                                    <option value="Japan">Japan</option>
                                </select>
                                <p v-if="errors.country" class="mt-1 text-sm text-red-500">
                                    {{ errors.country[0] }}
                                </p>
                            </div>

                            <!-- Vehicle Attributes -->
                            <div>
                                <label class="block text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-2">Vehicle Attributes</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="field in attributeFields" :key="field.key">
                                        <label :for="field.key" class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                            {{ field.label }}
                                        </label>
                                        <select
                                            v-if="field.type === 'select'"
                                            :id="field.key"
                                            v-model="product[field.key]"
                                            class="mt-1 p-2 w-full rounded-lg border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                            :class="{ 'border-red-500': errors[field.key] }"
                                        >
                                            <option value="">—</option>
                                            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                                        </select>
                                        <input
                                            v-else
                                            :type="field.type"
                                            :id="field.key"
                                            v-model="product[field.key]"
                                            class="mt-1 p-2 w-full rounded-lg border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300"
                                            :class="{ 'border-red-500': errors[field.key] }"
                                        />
                                        <p v-if="errors[field.key]" class="mt-1 text-xs text-red-500">
                                            {{ errors[field.key][0] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Images -->
                        <div class="space-y-6">
                            <!-- Front Image Input -->
                            <div>
                                <label for="front_image" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-2">
                                    Front Image
                                </label>
                                <div class="flex flex-col space-y-4">
                                    <div class="flex justify-center">
                                        <div
                                            class="relative w-full h-48 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 overflow-hidden transition duration-300 hover:shadow-xl"
                                            :class="{ 'border-dashed': !frontImagePreview, 'border-red-500': errors.front_image }"
                                        >
                                            <img
                                                v-if="frontImagePreview"
                                                :src="frontImagePreview"
                                                alt="Front Image Preview"
                                                class="w-full h-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="flex items-center justify-center h-full text-zinc-400 dark:text-zinc-500"
                                            >
                                                <Image class="w-10 h-10 mr-2" />
                                                <span>No image selected</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center space-x-4">
                                        <input
                                            type="file"
                                            id="front_image"
                                            @change="previewFrontImage"
                                            accept="image/*"
                                            class="hidden"
                                        />
                                        <label
                                            for="front_image"
                                            class="cursor-pointer flex items-center space-x-2 px-4 py-2 bg-[#782527] text-white rounded-lg hover:bg-[#6c1d1d] transition duration-300 shadow-md hover:shadow-lg"
                                        >
                                            <Upload class="w-5 h-5" />
                                            <span>Set Front Image</span>
                                        </label>
                                    </div>
                                    <p v-if="errors.front_image" class="mt-1 text-sm text-red-500">
                                        {{ errors.front_image[0] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Other Images Input (Gallery) -->
                            <div>
                                <label for="other_images" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-2">
                                    Other Images
                                </label>
                                <div class="flex flex-col space-y-4">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div
                                            v-for="(image, index) in otherImagesPreview"
                                            :key="index"
                                            class="relative w-full h-32 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950 overflow-hidden transition duration-300 hover:shadow-xl"
                                        >
                                            <img
                                                :src="image"
                                                :alt="'Other Image ' + (index + 1)"
                                                class="w-full h-full object-cover"
                                            />
                                            <button
                                                type="button"
                                                @click="removeOtherImage(index)"
                                                class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600"
                                            >
                                                ×
                                            </button>
                                        </div>
                                        <div
                                            class="flex items-center justify-center w-full h-32 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700"
                                            :class="{ 'border-red-500': errors.other_images }"
                                        >
                                            <input
                                                type="file"
                                                id="other_images"
                                                @change="previewOtherImages"
                                                accept="image/*"
                                                multiple
                                                class="hidden"
                                            />
                                            <label
                                                for="other_images"
                                                class="cursor-pointer flex items-center space-x-2 text-zinc-500 dark:text-zinc-400"
                                            >
                                                <Upload class="w-6 h-6" />
                                                <span>Add Images</span>
                                            </label>
                                        </div>
                                    </div>
                                    <p v-if="errors.other_images" class="mt-1 text-sm text-red-500">
                                        {{ errors.other_images[0] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Submit Button Overlay -->
                    <div class="mt-6 flex justify-center">
                        <button
                            :disabled="isLoading"
                            type="submit"
                            class="w-full max-w-md p-3 bg-[#782527] text-white rounded-lg shadow-md hover:bg-[#6c1d1d] focus:outline-none focus:ring-2 focus:ring-[#782527] transition duration-300"
                        >
                            Create Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>



<style>
.ql-container {
    font-size: 16px;
}

.ql-toolbar.ql-snow {
    border: none !important;
    padding: 8px;
    background-color: #782527;
    color: white;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.ql-toolbar.ql-snow + .ql-container.ql-snow {
    border-top: 0;
    border: none !important;
    background-color: #111827;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}

.ql-editor {
    scrollbar-width: thin;
    scrollbar-color: #191919 #3e3c3c;
}

.ql-snow .ql-picker {
    color: #ffffff !important;
}

.ql-snow .ql-stroke {
    stroke: #ffffff !important;
}

.ql-snow .ql-picker.ql-expanded .ql-picker-options {
    display: block;
    margin-top: -1px;
    top: 100%;
    z-index: 1;
    background-color: #1b1d1e;
    border: none;
    border-radius: 12px;
}
</style>

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