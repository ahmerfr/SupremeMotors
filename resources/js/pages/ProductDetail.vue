<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
  auth: Object,
  product_detail: Object,
  similar_products: Object
});

const activeTab = ref('description');
const activeImageIndex = ref(0);
const isZoomed = ref(false);
const mousePosition = ref({ x: 0, y: 0 });


const truncatedTitle = computed(() => {
  return props.product_detail.title.length > 40
    ? props.product_detail.title.substring(0, 40) + '...'
    : props.product_detail.title;
});


const imageSrc = computed(() => {
    if (activeImageIndex.value === -1 || !props.product_detail.other_images?.length) {
      const front = props.product_detail.front_image;
      return front.startsWith('product_images') ? '/storage/' + front : front;
    }

    const other = props.product_detail.other_images[activeImageIndex.value];
    return other?.startsWith('product_images') ? '/storage/' + other : other;
  }
);

const whatsappMessage = computed(() => {
  const baseURL = window.location.origin;
  const productURL = `${baseURL}${window.location.pathname}`;
  return encodeURIComponent(`Hello, I'm interested in getting a quote for ${props.product_detail.title}. Product link: ${productURL}`);
});

const switchTab = (tab) => {
  activeTab.value = tab;
};

const changeImage = (index) => {
  activeImageIndex.value = index;
  isZoomed.value = false;
};

const toggleZoom = () => {
  isZoomed.value = !isZoomed.value;
};

const handleMouseMove = (event) => {
  if (isZoomed.value) {
    const rect = event.target.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    mousePosition.value = { x, y };
  }
};

onMounted(() => {
  // Preload images for smoother transitions
  if (props.product_detail?.other_images?.length) {
    props.product_detail.other_images.forEach(img => {
      const image = new Image();
      image.src = img.startsWith('http') ? img : `/storage/${img}`;
    });
  }

  // Preload front image
  if (props.product_detail?.front_image) {
    const front = props.product_detail.front_image;
    const frontImg = new Image();
    frontImg.src = front.startsWith('http') ? front : `/storage/${front}`;
  }
});
</script>

<template>

  <Head :title="truncatedTitle" />

  <div class="flex flex-col min-h-screen">
    <FrontLayout>
      <main class="flex-grow">
        <!-- Breadcrumb Hero Section -->
        <section class="py-20 bg-gradient-to-r from-[#225282] to-[#1e4066] text-white relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-repeat"
              style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')">
            </div>
          </div>

          <div class="max-w-7xl mx-auto px-6 py-10 relative z-10">
            <div class="flex flex-col">
              <h1 class="text-3xl md:text-5xl font-bold mb-4 tracking-tight">{{ truncatedTitle }}</h1>
              <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                  <li class="inline-flex items-center">
                    <Link href="/" class="inline-flex items-center text-white hover:text-gray-200 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z">
                      </path>
                    </svg>
                    Home
                    </Link>
                  </li>
                  <li>
                    <div class="flex items-center">
                      <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                          d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                          clip-rule="evenodd"></path>
                      </svg>
                      <Link :href="route('inventory.index')"
                        class="ml-1 md:ml-2 text-white hover:text-gray-200 transition-colors">Shop</Link>
                    </div>
                  </li>
                  <li>
                    <div class="flex items-center">
                      <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                          d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                          clip-rule="evenodd"></path>
                      </svg>
                      <span class="ml-1 md:ml-2 text-white">{{ truncatedTitle }}</span>
                    </div>
                  </li>
                </ol>
              </nav>
            </div>
          </div>
        </section>

        <!-- Product Detail Section -->
        <section class="py-16 bg-gray-50">
          <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
              <!-- Product Images -->
              <div class="space-y-6">
                <!-- Main Image Container -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden aspect-video relative group cursor-pointer"
                  @click="toggleZoom" @mousemove="handleMouseMove">
                  <!-- Logo Overlay -->
                  <div class="absolute top-4 left-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                    <img src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                  </div>

                  <!-- Image Display -->
                  <div class="w-full h-full relative overflow-hidden"
                    :class="{ 'cursor-zoom-out': isZoomed, 'cursor-zoom-in': !isZoomed }">
                    <img
                      :src="imageSrc"
                      :alt="product_detail.title"
                      class="w-full h-full object-cover object-center transition-all duration-300"
                      :class="{ 'scale-150': isZoomed }"
                      :style="isZoomed ? `transform-origin: ${mousePosition.x}% ${mousePosition.y}%` : ''" />

                    <!-- Hover Overlay -->
                    <div
                      class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300 flex items-center justify-center">
                      <div
                        class="opacity-0 group-hover:opacity-100 transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                          xmlns="http://www.w3.org/2000/svg">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m4-6v6"></path>
                        </svg>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Thumbnail Gallery -->
                <div class="grid grid-cols-5 gap-3">
                  <div
                    class="aspect-square cursor-pointer rounded-md overflow-hidden border-2 transition-all transform hover:scale-105"
                    :class="activeImageIndex === -1 ? 'border-[#8a2527] shadow-md' : 'border-transparent hover:border-gray-300'"
                    @click="changeImage(-1)">
                    <img  v-if="product_detail.front_image || product_detail.other_images.length"
                    :src="(product_detail.front_image).startsWith('product_images') ? '/storage/' + product_detail.front_image : product_detail.front_image"
                     :alt="product_detail.title"
                      class="w-full h-full object-cover object-center" />
                  </div>
                  <div v-for="(image, index) in product_detail.other_images" :key="index" v-if="product_detail.front_image || product_detail.other_images.length"
                    class="aspect-square cursor-pointer rounded-md overflow-hidden border-2 transition-all transform hover:scale-105"
                    :class="activeImageIndex === index ? 'border-[#8a2527] shadow-md' : 'border-transparent hover:border-gray-300'"
                    @click="changeImage(index)">
                    <img  
                    :src="(image).startsWith('product_images') ? '/storage/' + image : image"
                    :alt="`${product_detail.title} - Image ${index + 1}`"
                      class="w-full h-full object-cover object-center" />
                  </div>
                </div>
              </div>

              <!-- Product Info -->
              <div class="space-y-8">
                <div>
                  <h1 class="text-3xl font-bold text-gray-900">{{ product_detail.title }}</h1>
                  
                  <div class="mt-4 flex items-center">
                    <div class="flex items-center bg-[#225282] text-white px-3 py-1 rounded-md">
                      <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                      </svg>
                      <span>Made in {{ product_detail.country }}</span>
                    </div>
                  </div>
                
                  <span
                    v-if="['tcv', 'suprememotors', 'electricvehicles'].some(substring => product_detail.website.includes(substring))"
                    class="block mt-4 text-4xl font-extrabold tracking-wide text-[#8e2527]"
                  >
                    ${{ product_detail.price.toLocaleString('en-US') }}
                  </span>
                </div>
                <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-100">
                  <div>
                    <div class="text-sm text-gray-500 mb-2">Get a quotation now:</div>
                    <div class="flex flex-col sm:flex-row gap-4">
                      <a :href="`https://wa.me/85291294007?text=${whatsappMessage}`" target="_blank"
                        rel="noopener noreferrer"
                        class="px-6 py-3 bg-[#25D366] hover:bg-[#20bd5a] text-white font-medium rounded-md transition-colors duration-300 flex items-center justify-center shadow-md hover:shadow-lg">
                        <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                          viewBox="0 0 24 24">
                          <path
                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                        </svg>
                        WhatsApp Quote
                      </a>
                    </div>
                  </div>
                </div>

                <!-- Product Tabs -->
                <div class="mt-10">
                  <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200">
                      <nav class="flex -mb-px">
                        <button @click="switchTab('description')"
                          class="py-4 px-6 border-b-2 font-medium text-sm transition-colors flex-1"
                          :class="activeTab === 'description' ? 'border-[#8a2527] text-[#8a2527] bg-gray-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                          Description
                        </button>
                      </nav>
                    </div>

                    <div class="p-6">
                      <div class="prose max-w-none animate-fade-in">
                        <h3 class="text-xl font-semibold mb-4">Product Overview</h3>
                        <div v-html="product_detail.product_details" class="text-black text-dark"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Related Products Section -->
        <section class="py-16 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 relative">
              Similar Products
              <span class="absolute bottom-0 left-0 w-20 h-1 bg-[#8a2527]"></span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
              <!-- Product Card -->
              <div v-for="sp in similar_products" :key="sp.id"
                class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                <div class="aspect-video bg-gray-100 overflow-hidden relative">
                  <img v-if="sp.front_image || sp.other_images.length" :src="(sp.front_image).startsWith('product_images') ? '/storage/' + sp.front_image : sp.front_image"
                    alt="Product Image"
                    class="w-full h-full object-cover object-center transition-transform hover:scale-105" />

                  <!-- Category & Country Tags -->
                  <div class="absolute bottom-2 left-2 flex gap-2">
                    <span class="bg-[#225282] text-white text-xs px-2 py-1 rounded-full">{{ sp.category.cat_title
                      }}</span>
                    <span class="bg-[#8a2527] text-white text-xs px-2 py-1 rounded-full">{{ sp.country }}</span>
                  </div>
                  <div class="absolute top-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                    <img src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                  </div>
                </div>

                <div class="p-4">
                  <h3 class="font-semibold text-lg mb-2 text-gray-900 hover:text-[#8a2527] transition-colors">
                    {{ sp.title.length > 40 ? sp.title.substring(0, 40) + '...' : sp.title }}
                  </h3>
                  <p class="text-gray-700 mb-4 text-sm line-clamp-3" v-html="truncateHTML(sp.product_details, 80)"></p>
                  <div class="flex justify-between items-center">
                    <Link :href="route('inventory.product-detail', { id: sp.id })"
                      class="bg-[#225282] hover:bg-[#1b4169] text-white text-sm font-medium px-4 py-2 rounded transition-colors">
                    View Details
                    </Link>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </section>
        <!-- Call to Action Section -->
        <section class="py-16 bg-gradient-to-r from-[#8a2527] to-[#a52a2c] text-white">
          <div class="max-w-7xl mx-auto px-6 flex flex-col lg:flex-row items-center justify-between">
            <div class="mb-8 lg:mb-0 text-center lg:text-left">
              <h2 class="text-3xl font-bold mb-4">Need Custom Configuration?</h2>
              <p class="text-lg opacity-90 max-w-2xl">Our team of experts is ready to help you find the perfect truck
                for your specific requirements.</p>
            </div>
            <div>
              <Link :href="route('contact-us')"
                class="px-8 py-4 bg-white text-[#8a2527] hover:bg-gray-100 font-bold rounded-md shadow-lg transition-colors duration-300">
              Contact Us Today
              </Link>
            </div>
          </div>
        </section>
      </main>
    </FrontLayout>
  </div>
</template>

<script>
export default {
  data() {
    return {

    }
  },
  methods: {
    truncateHTML(htmlString, maxLength) {
      if (!htmlString) return '';
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = htmlString;
      const textContent = tempDiv.textContent || tempDiv.innerText;
      // if (textContent.length <= maxLength) {
      return htmlString;
      // }
      // return textContent.substring(0, maxLength) + '...';
    },
  }
}
</script>