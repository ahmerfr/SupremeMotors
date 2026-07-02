<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
const props = defineProps({
  categories: Object,
  auth: Object,
  makes: Object,
  featured_products_china: Object,
  featured_products_japan: Object,
  // featured_products_thailand : Object,
});

</script>

<template>

  <Head title="Home" />

  <div class="flex flex-col min-h-screen">
    <FrontLayout>
      <div class="relative h-screen bg-gradient-to-b from-gray-900 to-black overflow-hidden">
        <!-- Background with enhanced parallax effect -->
        <div 
          class="absolute inset-0 bg-cover bg-center transform transition-transform duration-700 ease-out hover:scale-105"
          :style="{
            backgroundImage: `url('https://images.unsplash.com/photo-1494976388531-d1058494cdd8?q=80&w=2070')`,
            filter: 'brightness(0.6) contrast(1.1)'
          }"
          ref="parallaxBg">
        </div>
    
        <!-- Brand color overlay with gradient -->
        <div class="absolute inset-0 bg-gradient-to-tr from-[#8e2527]/40 via-black/60 to-[#8e2527]/30">
          <!-- Enhanced geometric pattern with brand color -->
          <div class="absolute inset-0 opacity-15" 
               :style="{ backgroundImage: `url('${geometricPatternSvg}')` }">
          </div>
        </div>
    
        <!-- Animated particles overlay -->
        <div class="absolute inset-0 opacity-30" ref="particlesContainer"></div>
    
        <!-- Content container with improved layout -->
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="text-center text-white max-w-6xl px-6 z-10">
            <!-- Logo element (optional) -->
            <div 
              class="mb-6 transform transition-all duration-1000"
              :class="[logoVisible ? 'scale-100 opacity-100' : 'scale-0 opacity-0']">
              <svg width="80" height="80" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <circle cx="50" cy="50" r="45" fill="none" stroke="#f7c548" stroke-width="2"/>
                <path d="M25,65 L75,65 C80,65 85,60 85,55 L85,50 C85,45 80,40 75,40 L25,40 C20,40 15,45 15,50 L15,55 C15,60 20,65 25,65 Z" fill="#8e2527"/>
                <rect x="20" y="65" width="60" height="5" rx="2" fill="#f7c548"/>
                <rect x="35" y="30" width="30" height="10" rx="5" fill="#8e2527"/>
                <circle cx="30" cy="55" r="7" fill="#333"/>
                <circle cx="30" cy="55" r="3" fill="#f7c548"/>
                <circle cx="70" cy="55" r="7" fill="#333"/>
                <circle cx="70" cy="55" r="3" fill="#f7c548"/>
              </svg>
            </div>
    
            <!-- Enhanced main heading with 3D effect -->
            <h1 class="text-5xl md:text-7xl font-bold mb-6 relative">
              <span 
                v-for="(word, index) in titleWords" 
                :key="index"
                class="inline-block transform transition-all duration-700"
                :class="[
                  titleVisible[index] ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                  word === 'AUTO' ? 'text-[#f7c548] mx-2' : '',
                  'perspective-text hover:text-[#f7c548]'
                ]">
                {{ word }}
              </span>
            </h1>
            
            <!-- Dynamic tagline with enhanced animation -->
            <p 
              class="text-xl md:text-3xl mb-12 font-light relative overflow-hidden"
              :class="{ 'fade-out': isTaglineFading }">
              <span 
                class="absolute inset-0 bg-gradient-to-r from-transparent via-[#8e2527]/20 to-transparent"
                :class="{ 'tagline-shine-active': isTaglineShining }">
              </span>
              <span class="tagline-text">{{ currentTagline }}</span>
            </p>
    
            <!-- Interactive vehicle carousel -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-12 max-w-3xl mx-auto">
              <div 
                v-for="(vehicle, index) in vehicles" 
                :key="index"
                class="transform transition-all duration-500 bg-white/10 backdrop-blur-sm p-3 rounded-lg hover:bg-[#8e2527]/30 hover:scale-105 cursor-pointer"
                :class="[vehiclesVisible[index] ? 'opacity-100' : 'opacity-0']"
                @click="selectVehicle(index)">
                <div class="text-sm md:text-base font-semibold text-center">{{ vehicle.name }}</div>
                <div class="h-14 flex items-center justify-center" v-html="vehicle.svg"></div>
              </div>
            </div>
            
            <!-- Enhanced CTA button with animated gradient border -->
            <div class="flex flex-wrap justify-center gap-4">
              <Link 
                href="/inventory" 
                class="px-8 py-4 bg-[#8e2527] text-white rounded-md text-lg font-semibold hover:bg-[#7a1e20] shadow-lg hover:shadow-xl transition duration-300 group relative outline-none button-glow">
                <span class="relative z-10">Browse Inventory</span>
                <span class="inline-block transition-transform duration-300 group-hover:translate-x-2 relative z-10">→</span>
                <span class="absolute inset-0 rounded-md bg-gradient-to-r from-[#f7c548] via-[#8e2527] to-[#f7c548] animate-gradient-border opacity-0 group-hover:opacity-100 transition-opacity duration-500"></span>
            </Link>
            </div>
          </div>
        </div>
        
        <!-- Enhanced bottom stats bar -->
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-r from-black/80 via-[#8e2527]/40 to-black/80 backdrop-blur-sm py-4">
          <div class="container mx-auto flex justify-center items-center divide-x divide-[#8e2527]/30">
            <div 
              v-for="(stat, index) in stats"
              :key="index"
              class="px-4 md:px-8 text-center transition-all duration-700"
              :class="[statsVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']"
              :style="{ transitionDelay: `${index * 300}ms` }">
              <div class="text-[#f7c548] text-xl md:text-2xl font-bold" v-html="stat.value"></div>
              <div class="text-white text-sm">{{ stat.label }}</div>
            </div>
          </div>
        </div>
      </div>

      <main class="flex-grow">


        <!-- Category cards section -->
        <section class="py-16 bg-gradient-to-b from-gray-100 to-white">
          <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-4xl font-bold text-[#1e4066] mb-4 text-center">Explore Our Categories</h2>
            <p class="text-gray-600 text-lg text-center mb-12 max-w-3xl mx-auto">Find exactly what you need from our
              extensive collection of construction equipment and parts</p>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              <div 
                v-for="category in categories" 
                :key="category._id"
                class="bg-white rounded-xl shadow-md overflow-hidden group transition-all duration-300 hover:shadow-xl transform hover:-translate-y-2 hover:rotate-1"
              >
                <Link :href="route('inventory.index', { category: category._id })" class="block h-full flex flex-col">
                  <div class="p-6 flex flex-col flex-grow">
                    <div class="flex items-center mb-4">
                      <div
                        class="h-20 w-20 rounded-full flex items-center justify-center bg-gradient-to-br from-[#1e4066]/10 to-[#1e4066]/20 text-[#1e4066] group-hover:from-[#8e2527]/90 group-hover:to-[#8e2527] group-hover:text-white transition-colors duration-300"
                      >
                        <img 
                          :src="'/storage/' + category.image" 
                          loading="lazy"
                          alt="Make logo" 
                          class="h-14 w-14 transition-all duration-300 group-hover:scale-110" 
                          style="object-fit: contain;"
                        >
                      </div>
                      <h3
                        class="text-xl font-semibold text-gray-800 ml-3 group-hover:text-[#8e2527] transition-colors duration-300"
                      >
                        {{ category.cat_title }}
                      </h3>
                    </div>
        
                    <p class="text-gray-500 mb-4 flex items-center">
                      <span class="bg-gray-100 px-2 py-1 rounded-md mr-2 text-sm font-medium">{{ category.products_count.toLocaleString() }}</span>
                      stock available
                    </p>
        
                    <div class="mt-auto flex justify-between items-center">
                      <span
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#1e4066] border border-[#1e4066] rounded-full group-hover:bg-[#8e2527] group-hover:border-[#8e2527] group-hover:text-white transition-all duration-300"
                      >
                        View Stock
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                      </span>
                    </div>
                  </div>
                </Link>
              </div>
            </div>
          </div>
        </section>
        <!-- Makes cards section -->
        <section class="py-16 bg-gradient-to-b from-gray-100 to-white">
          <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-4xl font-bold text-[#1e4066] mb-4 text-center">Browse By Makes</h2>
            <p class="text-gray-600 text-lg text-center mb-12 max-w-3xl mx-auto">Find parts and equipment for your
              specific vehicle make and model</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div 
                  v-for="make in makes" 
                  :key="make._id"
                  class="bg-white rounded-xl shadow-md overflow-hidden group transition-all duration-300 hover:shadow-xl transform hover:-translate-y-2 hover:rotate-1"
                >
                  <Link :href="route('inventory.index', { make: make._id })" class="block h-full flex flex-col">
                    <div class="p-6 flex flex-col flex-grow">
                      <div class="flex items-center mb-4">
                        <div
                          class="h-20 w-20 rounded-full flex items-center justify-center bg-gradient-to-br from-[#1e4066]/10 to-[#1e4066]/20 text-[#1e4066] group-hover:from-[#8e2527]/90 group-hover:to-[#8e2527] group-hover:text-white transition-colors duration-300"
                        >
                          <img 
                            :src="'/storage/' + make.image" 
                            loading="lazy"
                            alt="Make logo" 
                            class="h-14 w-14 transition-all duration-300 group-hover:scale-110" 
                            style="object-fit: contain;"
                          >
                        </div>
                        <h3
                          class="text-xl font-semibold text-gray-800 ml-3 group-hover:text-[#8e2527] transition-colors duration-300"
                        >
                          {{ make.cat_title }}
                        </h3>
                      </div>
          
                      <p class="text-gray-500 mb-4 flex items-center">
                        <span class="bg-gray-100 px-2 py-1 rounded-md mr-2 text-sm font-medium">{{ make.products_count.toLocaleString() }}</span>
                        stock available
                      </p>
          
                      <div class="mt-auto flex justify-between items-center">
                        <span
                          class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#1e4066] border border-[#1e4066] rounded-full group-hover:bg-[#8e2527] group-hover:border-[#8e2527] group-hover:text-white transition-all duration-300"
                        >
                          View Stock
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                          </svg>
                        </span>
                      </div>
                    </div>
                  </Link>
                </div>
              </div>
          </div>
        </section>


        <section class="py-16 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
              <div>
                <h2 class="text-4xl font-bold text-[#1e4066] mb-3">Featured Products - China</h2>
                <p class="text-gray-600 max-w-2xl">Discover our collection of premium construction equipment and parts
                  from China</p>
              </div>
              <Link :href="route('inventory.index', { country: 'China' })"
                class="mt-4 md:mt-0 text-[#8e2527] font-semibold hover:text-[#7a1e20] transition-colors duration-300 flex items-center">
              View All China Products
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5 ml-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
              </svg>
              </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div v-for="product in featured_products_china" :key="product._id"
                class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 product-card">
                <div class="h-64 bg-gray-200 relative overflow-hidden product-image-container">
                  <div class="absolute top-4 left-4 z-10 flex gap-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#1e4066] text-white rounded-full">
                      {{ product.category.cat_title }}
                    </span>
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#8e2527] text-white rounded-full">
                      China
                    </span>
                  </div>
                  <div class="absolute bottom-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">

                    <img src="/assets/images/site-logo.png" loading="lazy" alt="Logo" class="h-8 w-auto" />
                  </div>
                  <div
                    class="h-full w-full bg-cover bg-center transform transition-transform duration-500 product-image"
                    :style="{ backgroundImage: `url(${(product.front_image).includes('product_images') ? '/storage/' + product.front_image : product.front_image})` }">
                  </div>
                  <div
                    class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 transition-opacity duration-300 product-overlay">
                  </div>
                </div>
                <div class="p-6">
                  <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-semibold text-gray-800 hover:text-[#8e2527] transition-colors duration-300">
                      {{ product.title.length > 40 ? product.title.substring(0, 40) + '...' : product.title }}</h3>
                    <span v-if="['tcv', 'suprememotors', 'electricvehicles'].some(substring => product.website.includes(substring))" class="text-lg font-bold text-[#8e2527]">${{ product.price }}</span>
                  </div>
                  <div class="flex items-center text-sm text-gray-500 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                      stroke="currentColor" class="w-4 h-4 mr-1">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    Made in China
                  </div>
                  <p class="text-gray-700 mb-4 text-sm line-clamp-3"
                    v-html="truncateHTML(product.product_details, 180)"></p>
                  <Link v-if="product.id" :href="route('inventory.product-detail', { id: product.id })"
                    class="w-full block text-center py-3 border border-[#1e4066] text-[#1e4066] rounded-md font-medium hover:bg-[#8e2527] hover:border-[#8e2527] hover:text-white transition-all duration-300">
                  View Details
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="py-16 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
              <div>
                <h2 class="text-4xl font-bold text-[#1e4066] mb-3">Featured Products - Japan</h2>
                <p class="text-gray-600 max-w-2xl">Discover our collection of premium vehicles
                  from Japan</p>
              </div>
              <Link :href="route('inventory.index', { country: 'Japan' })"
                class="mt-4 md:mt-0 text-[#8e2527] font-semibold hover:text-[#7a1e20] transition-colors duration-300 flex items-center">
              View All Japan Products
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5 ml-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
              </svg>
              </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div v-for="product in featured_products_japan" :key="product._id"
                class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 product-card">
                <div class="h-64 bg-gray-200 relative overflow-hidden product-image-container">
                  <div class="absolute top-4 left-4 z-10 flex gap-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#1e4066] text-white rounded-full">
                      {{ product.category.cat_title }}
                    </span>
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#8e2527] text-white rounded-full">
                      Japan
                    </span>
                  </div>
                  <div class="absolute top-4 right-4 z-10 flex gap-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#1e4066] text-white rounded-full">
                      {{ product.make.cat_title }}
                    </span>
                  </div>
                  <div class="absolute bottom-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                    <img src="/assets/images/site-logo.png" loading="lazy" alt="Logo" class="h-8 w-auto" />
                  </div>
                  <div
                    class="h-full w-full bg-cover bg-center transform transition-transform duration-500 product-image"
                    :style="{ backgroundImage: `url(${(product.front_image).includes('product_images') ? '/storage/' + product.front_image : product.front_image})` }">
                  </div>
                  <!-- class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 transition-opacity duration-300 product-overlay"> -->
                </div>
                <div class="p-6">
                  <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-semibold text-gray-800 hover:text-[#8e2527] transition-colors duration-300">
                      {{ product.title.length > 40 ? product.title.substring(0, 40) + '...' : product.title }}</h3>
                    <span v-if="['tcv', 'suprememotors', 'electricvehicles'].some(substring => product.website.includes(substring))" class="text-lg font-bold text-[#8e2527]">
                      ${{ product.price.toLocaleString() }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-500 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                      stroke="currentColor" class="w-4 h-4 mr-1">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    Made in Japan
                  </div>
                  <p class="text-gray-700 mb-4 text-sm line-clamp-3"
                    v-html="truncateHTML(product.product_details, 180)"></p>
                  <Link v-if="product.id" :href="route('inventory.product-detail', { id: product.id })"
                    class="w-full block text-center py-3 border border-[#1e4066] text-[#1e4066] rounded-md font-medium hover:bg-[#8e2527] hover:border-[#8e2527] hover:text-white transition-all duration-300">
                  View Details
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- <section class="py-16 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
              <div>
                <h2 class="text-4xl font-bold text-[#1e4066] mb-3">Featured Products - Thailand</h2>
                <p class="text-gray-600 max-w-2xl">Discover our collection of premium vehicles
                  from Thailand</p>
              </div>
              <Link :href="route('inventory.index', { country: 'Thailand' })"
                class="mt-4 md:mt-0 text-[#8e2527] font-semibold hover:text-[#7a1e20] transition-colors duration-300 flex items-center">
              View All Thailand Products
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5 ml-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
              </svg>
              </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div v-for="product in featured_products_thailand" :key="product._id"
                class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 product-card">
                <div class="h-64 bg-gray-200 relative overflow-hidden product-image-container">
                  <div class="absolute top-4 left-4 z-10 flex gap-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#1e4066] text-white rounded-full">
                      {{ product.category.cat_title }}
                    </span>
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#8e2527] text-white rounded-full">
                      Thailand
                    </span>
                  </div>
                  <div class="absolute top-4 right-4 z-10 flex gap-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold bg-[#1e4066] text-white rounded-full">
                      {{ product.make.cat_title }}
                    </span>
                  </div>
                  <div class="absolute bottom-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                    <img src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                  </div>
                  <div
                    class="h-full w-full bg-cover bg-center transform transition-transform duration-500 product-image"
                    :style="{ backgroundImage: `url(${(product.front_image).includes('product_images') ? '/storage/' + product.front_image : product.front_image})` }">
                  </div>
                </div>
                <div class="p-6">
                  <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-semibold text-gray-800 hover:text-[#8e2527] transition-colors duration-300">
                      {{ product.title.length > 40 ? product.title.substring(0, 40) + '...' : product.title }}</h3>
                  </div>
                  <div class="flex items-center text-sm text-gray-500 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                      stroke="currentColor" class="w-4 h-4 mr-1">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    Made in Thailand
                  </div>
                  <p class="text-gray-700 mb-4 text-sm line-clamp-3"
                    v-html="truncateHTML(product.product_details, 180)"></p>
                  <Link v-if="product.id" :href="route('inventory.product-detail', { id: product.id })"
                    class="w-full block text-center py-3 border border-[#1e4066] text-[#1e4066] rounded-md font-medium hover:bg-[#8e2527] hover:border-[#8e2527] hover:text-white transition-all duration-300">
                  View Details
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section> -->

        <!-- CTA Section -->
        <section class="py-16 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div class="mb-8 lg:mb-0">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Ready to Upgrade Your Equipment?</h2>
                <p class="text-white/80 text-lg max-w-2xl">Contact our expert team today for personalized advice and
                  solutions for your construction needs.</p>
              </div>
              <div class="flex flex-col sm:flex-row gap-4">
                <Link :href="route('inventory.index')"
                  class="px-8 py-4 bg-white text-[#1e4066] rounded-md text-lg font-semibold hover:bg-gray-100 shadow-lg hover:shadow-xl transition duration-300 text-center">
                View Catalog
                </Link>
                <Link :href="route('contact-us')"
                  class="px-8 py-4 bg-[#8e2527] text-white rounded-md text-lg font-semibold hover:bg-[#7a1e20] shadow-lg hover:shadow-xl transition duration-300 text-center">
                Contact Us
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
    </FrontLayout>
  </div>
</template>

<style scoped>
/* Perspective text effect */
.perspective-text {
  text-shadow: 1px 1px 1px rgba(0,0,0,0.5),
               2px 2px 0 rgba(0,0,0,0.2);
  position: relative;
}

.perspective-text::after {
  content: '';
  position: absolute;
  bottom: -5px;
  left: 0;
  width: 100%;
  height: 2px;
  background: linear-gradient(to right, transparent, #f7c548, transparent);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.perspective-text:hover::after {
  transform: scaleX(1);
}

/* Tagline animations */
.fade-out {
  opacity: 0.3;
}

.tagline-shine-active {
  left: 200%;
  opacity: 1;
  transition: left 1s ease;
}

/* Particle animation */
.particle {
  position: absolute;
  background-color: #f7c548;
  border-radius: 50%;
  animation: float linear infinite;
}

@keyframes float {
  0% {
    transform: translateY(0) translateX(0);
  }
  25% {
    transform: translateY(-20px) translateX(10px);
  }
  50% {
    transform: translateY(-40px) translateX(-10px);
  }
  75% {
    transform: translateY(-20px) translateX(10px);
  }
  100% {
    transform: translateY(0) translateX(0);
  }
}

/* Button glow animation */
.button-glow {
  animation: gentle-pulse 2s infinite;
}

@keyframes gentle-pulse {
  0% { box-shadow: 0 0 0 0 rgba(142, 37, 39, 0.7); }
  70% { box-shadow: 0 0 0 10px rgba(142, 37, 39, 0); }
  100% { box-shadow: 0 0 0 0 rgba(142, 37, 39, 0); }
}

/* Animated gradient border */
.animate-gradient-border {
  background-size: 200% 200%;
  animation: gradient-shift 3s ease infinite;
}

@keyframes gradient-shift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

/* Star rating animation */
.star-rating {
  opacity: 0.3;
  transition: opacity 0.3s ease, transform 0.3s ease;
  display: inline-block;
  transform: scale(0.8);
}

.star-rating.active {
  opacity: 1;
  transform: scale(1);
}

.bg-\[\#8e2527\] {
  animation: gentle-pulse 2s infinite;
}

.product-image-container:hover .product-image {
  transform: scale(1.1);
}

.product-image-container:hover .product-overlay {
  opacity: 1;
}

.product-image-container:hover .product-actions {
  transform: translateY(0);
}

@keyframes slide-up {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-slide-up {
  animation: slide-up 0.8s forwards;
}

.animation-delay-300 {
  animation-delay: 0.3s;
}

.animation-delay-600 {
  animation-delay: 0.6s;
}

/* Added hover effects for product cards */
.product-card:hover .product-image {
  transform: scale(1.05);
}

.product-card:hover .product-overlay {
  opacity: 1;
}
</style>
<script>
export default {
  data() {
    return {
      // Animation states
      logoVisible: false,
      titleVisible: [false, false, false],
      vehiclesVisible: [false, false, false],
      statsVisible: false,
      isTaglineFading: false,
      isTaglineShining: false,
      
      // Content
      titleWords: ['SUPREME', 'AUTO', 'COLLECTION'],
      taglines: [
        "Drive Excellence. Live Extraordinary.",
        "Luxury Redefined. Performance Unleashed.",
        "Where Dreams Meet the Road.",
        "Precision Engineering, Exceptional Service.",
        "Your Journey Begins With Us."
      ],
      currentTagline: "Drive Excellence. Live Extraordinary.",
      currentTaglineIndex: 0,
      
      // Vehicle cards data
     vehicles: [
        
        {
          name: 'Heavy Machinery',
          description: 'Industrial-grade equipment for construction and heavy-duty applications.',
          svg: `<svg width="60" height="30" viewBox="0 0 100 50" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <rect x="10" y="25" width="80" height="15" fill="#f7c548" fill-opacity="0.8"/>
                <rect x="15" y="10" width="30" height="15" fill="#f7c548" fill-opacity="0.6"/>
                <rect x="65" y="15" width="25" height="10" fill="#f7c548" fill-opacity="0.7"/>
                <circle cx="25" cy="40" r="7" fill="#333"/>
                <circle cx="45" cy="40" r="7" fill="#333"/>
                <circle cx="75" cy="40" r="7" fill="#333"/>
              </svg>`
        },
        {
          name: 'Trucks',
          description: 'Reliable trucks for hauling and delivery with various capacity options.',
          svg: `<svg width="60" height="30" viewBox="0 0 100 50" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <rect x="5" y="20" width="45" height="15" fill="#f7c548" fill-opacity="0.8"/>
                <rect x="50" y="20" width="45" height="20" fill="#f7c548" fill-opacity="0.6"/>
                <circle cx="20" cy="35" r="6" fill="#333"/>
                <circle cx="40" cy="35" r="6" fill="#333"/>
                <circle cx="70" cy="40" r="6" fill="#333"/>
                <circle cx="90" cy="40" r="6" fill="#333"/>
              </svg>`
        },
        {
          name: 'Electric Vehicles',
          description: 'Eco-friendly, zero-emission cars with cutting-edge technology.',
          svg: `<svg width="60" height="20" viewBox="0 0 100 30" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <path d="M5,20 L20,10 L80,10 L95,20 L95,25 L5,25 Z" fill="#f7c548" fill-opacity="0.8"/>
                <rect x="15" y="15" width="70" height="10" fill="#f7c548" fill-opacity="0.6"/>
                <circle cx="25" cy="25" r="5" fill="#333"/>
                <circle cx="75" cy="25" r="5" fill="#333"/>
                <path d="M45,5 L55,15 M55,5 L45,15" stroke="#f7c548" stroke-width="2"/>
              </svg>`
        },
        {
          name: 'Luxury Sedans',
          description: 'Premium comfort and elegance for business travel and special occasions.',
          svg: `<svg width="60" height="20" viewBox="0 0 100 30" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <path d="M5,20 L20,10 L80,10 L95,20 L95,25 L5,25 Z" fill="#f7c548" fill-opacity="0.8"/>
                <rect x="15" y="15" width="70" height="10" fill="#f7c548" fill-opacity="0.6"/>
                <circle cx="25" cy="25" r="5" fill="#333"/>
                <circle cx="75" cy="25" r="5" fill="#333"/>
              </svg>`
        },
        {
          name: 'Premium SUVs',
          description: 'Spacious and versatile vehicles with luxury amenities for family trips.',
          svg: `<svg width="60" height="30" viewBox="0 0 100 50" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <path d="M10,35 L20,15 L80,15 L90,35 L90,40 L10,40 Z" fill="#f7c548" fill-opacity="0.8"/>
                <rect x="20" y="15" width="60" height="25" fill="#f7c548" fill-opacity="0.6"/>
                <circle cx="25" cy="40" r="5" fill="#333"/>
                <circle cx="75" cy="40" r="5" fill="#333"/>
              </svg>`
        },
        {
          name: 'Sports Cars',
          description: 'High-performance vehicles designed for speed and thrilling driving experience.',
          svg: `<svg width="70" height="20" viewBox="0 0 100 30" xmlns="http://www.w3.org/2000/svg" class="mx-auto">
                <path d="M5,20 L25,12 L75,12 L95,20 L95,25 L5,25 Z" fill="#f7c548" fill-opacity="0.8"/>
                <path d="M25,12 L30,5 L70,5 L75,12" fill="#f7c548" fill-opacity="0.6"/>
                <circle cx="25" cy="25" r="5" fill="#333"/>
                <circle cx="75" cy="25" r="5" fill="#333"/>
              </svg>`
        },
      ],
      
      // Stats data
      stats: [
        { 
          value: '<span class="counter" data-target="200000">0</span>',
          label: 'Vehicles in Stock' 
        },
        { 
          value: `<span class="star-rating">★</span>
                  <span class="star-rating">★</span>
                  <span class="star-rating">★</span>
                  <span class="star-rating">★</span>
                  <span class="star-rating">★</span>`,
          label: 'Customer Service' 
        },
        { 
          value: '24/7', 
          label: 'Support Available' 
        }
      ],
      
      // SVG pattern
      geometricPatternSvg: 'data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cdefs%3E%3Cpattern id=%22dealershipPattern%22 x=%220%22 y=%220%22 width=%2260%22 height=%2260%22 patternUnits=%22userSpaceOnUse%22%3E%3Cline x1=%220%22 y1=%2215%22 x2=%2260%22 y2=%2215%22 stroke=%22%238e2527%22 stroke-width=%221%22 stroke-opacity=%220.4%22 /%3E%3Cline x1=%220%22 y1=%2245%22 x2=%2260%22 y2=%2245%22 stroke=%22%238e2527%22 stroke-width=%221%22 stroke-opacity=%220.4%22 /%3E%3Cline x1=%2215%22 y1=%220%22 x2=%2215%22 y2=%2260%22 stroke=%22white%22 stroke-width=%220.8%22 stroke-opacity=%220.3%22 /%3E%3Cline x1=%2245%22 y1=%220%22 x2=%2245%22 y2=%2260%22 stroke=%22white%22 stroke-width=%220.8%22 stroke-opacity=%220.3%22 /%3E%3Ccircle cx=%2215%22 cy=%2215%22 r=%223%22 fill=%22%238e2527%22 fill-opacity=%220.6%22 /%3E%3Ccircle cx=%2245%22 cy=%2215%22 r=%223%22 fill=%22%238e2527%22 fill-opacity=%220.6%22 /%3E%3Ccircle cx=%2215%22 cy=%2245%22 r=%223%22 fill=%22%238e2527%22 fill-opacity=%220.6%22 /%3E%3Ccircle cx=%2245%22 cy=%2245%22 r=%223%22 fill=%22%238e2527%22 fill-opacity=%220.6%22 /%3E%3Cpath d=%22M30,30 L34,26 L38,30 L34,34 Z%22 fill=%22white%22 fill-opacity=%220.7%22 /%3E%3C/pattern%3E%3C/defs%3E%3Crect x=%220%22 y=%220%22 width=%2260%22 height=%2260%22 fill=%22url(%23dealershipPattern)%22 /%3E%3C/svg%3E',
      
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

    initAnimations() {
      // Logo animation
      setTimeout(() => {
        this.logoVisible = true;
      }, 300);
      
      // Title animations
      this.titleWords.forEach((_, i) => {
        setTimeout(() => {
          this.titleVisible[i] = true; // Direct assignment instead of $set
        }, i * 200 + 500);
      });
      
      // Vehicle cards animations
      this.vehicles.forEach((_, i) => {
        setTimeout(() => {
          this.vehiclesVisible[i] = true; // Direct assignment instead of $set
        }, i * 150 + 1000);
      });
      
      // Stats animations
      setTimeout(() => {
        this.statsVisible = true;
      }, 1200);
    },
    
    initParallax() {
      // Enhanced parallax effect
      if (this.$refs.parallaxBg) {
        document.addEventListener('mousemove', (e) => {
          const x = e.clientX / window.innerWidth;
          const y = e.clientY / window.innerHeight;
          this.$refs.parallaxBg.style.transform = `translate(${-x * 30}px, ${-y * 30}px) scale(1.15)`;
        });
      }
    },
    
    createParticles() {
      // Create animated particles
      if (this.$refs.particlesContainer) {
        for (let i = 0; i < 30; i++) {
          const particle = document.createElement('div');
          particle.classList.add('particle');
          
          // Random position, size and animation delay
          const size = Math.random() * 5 + 2;
          particle.style.width = `${size}px`;
          particle.style.height = `${size}px`;
          particle.style.left = `${Math.random() * 100}%`;
          particle.style.top = `${Math.random() * 100}%`;
          particle.style.animationDelay = `${Math.random() * 5}s`;
          particle.style.animationDuration = `${Math.random() * 10 + 10}s`;
          particle.style.opacity = Math.random() * 0.5;
          
          this.$refs.particlesContainer.appendChild(particle);
        }
      }
    },
    
    initCounters() {
      // Animated counter for stats
      const counters = document.querySelectorAll('.counter');
      counters.forEach(counter => {
        const target = +counter.dataset.target;
        const duration = 2000; // 2 seconds
        const increment = target / (duration / 16); // Update every 16ms (60fps)
        let current = 0;
        
        const updateCounter = () => {
          current += increment;
          if (current < target) {
            counter.textContent = Math.ceil(current).toLocaleString();
            requestAnimationFrame(updateCounter);
          } else {
            counter.textContent = target.toLocaleString() + '+';
          }
        };
        
        setTimeout(() => {
          updateCounter();
        }, 1500);
      });
    },
    
    startTaglineRotation() {
      // Tagline rotation
      setInterval(() => {
        this.isTaglineFading = true;
        
        setTimeout(() => {
          this.currentTaglineIndex = (this.currentTaglineIndex + 1) % this.taglines.length;
          this.currentTagline = this.taglines[this.currentTaglineIndex];
          this.isTaglineFading = false;
          this.isTaglineShining = true;
          
          setTimeout(() => {
            this.isTaglineShining = false;
          }, 1000);
        }, 500);
      }, 5000);
    },
    
    animateStars() {
      // Star rating animation
      const stars = document.querySelectorAll('.star-rating');
      stars.forEach((star, i) => {
        setTimeout(() => {
          star.classList.add('active');
        }, 1800 + (i * 150));
      });
    }
  },
  mounted() {
    this.initAnimations();
    this.initParallax();
    this.createParticles();
    this.initCounters();
    this.startTaglineRotation();
    this.animateStars();
  },
}
</script>
