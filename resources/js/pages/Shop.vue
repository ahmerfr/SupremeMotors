<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';

const props = defineProps({
  auth: Object,
  featured_products: Object,
  products: Object,
  categories: Object,
  makes: Object
});

const viewMode = ref('grid');
const isFiltersOpen = ref(false);


// Mobile filter toggle
function toggleFilters() {
  isFiltersOpen.value = !isFiltersOpen.value;
}
</script>

<template>

  <Head title="Inventory - Supreme Motors" />

  <div class="flex flex-col min-h-screen">
    <FrontLayout>
      <main class="flex-grow">
        <!-- Breadcrumb Hero Section -->
        <section class="py-20 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-repeat"
              style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')">
            </div>
          </div>

          <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col items-center text-center">
              <h1 class="text-4xl md:text-6xl font-bold mb-6">Premium Vehicles</h1>
              <p class="text-xl md:text-2xl max-w-3xl mb-8">Explore our collection of luxury and performance vehicles
                crafted for excellence</p>
              <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                  <li class="inline-flex items-center">
                    <Link href="/" class="inline-flex items-center text-white hover:text-gray-200">
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
                      <span class="text-white ml-1 md:ml-2">Inventory</span>
                    </div>
                  </li>
                </ol>
              </nav>
            </div>
          </div>
        </section>

        <!-- Inventory Section -->
        <section class="py-12 bg-gray-50">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Mobile Filter Toggle -->
            <div class="lg:hidden mb-6">
              <button @click="toggleFilters"
                class="w-full flex items-center justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                {{ isFiltersOpen ? 'Hide Filters' : 'Show Filters' }}
              </button>
            </div>

            <!-- Search Bar -->
            <div class="vehicle-filter bg-white rounded-lg shadow p-4 mb-8">
              <div class="mb-4">
                <div class="relative flex items-center">
                  <input 
                    v-model="filters.searchQuery" 
                    type="text" 
                    placeholder="Search for vehicles..." 
                    class="w-full rounded-full pl-12 pr-4 py-3 focus:ring-blue-500 focus:border-blue-500 border-gray-300 shadow-sm"
                  >
                  <div class="absolute left-4">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>
                </div>
              </div>
          
              <!-- Filter Dropdowns -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Make Dropdown -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Make</label>
                  <select 
                    v-model="filters.make" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">All Makes</option>
                    <option v-for="make in makes" :key="make._id" :value="make._id">{{ make.cat_title }} ({{ make.products_count }})</option>
                  </select>
                </div>
          
                <!-- Registration Year From -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Registration Year From</label>
                  <select 
                    v-model="filters.year.from" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">Any Year</option>
                    <option v-for="year in yearOptions" :key="'from-'+year" :value="year">{{ year }}</option>
                  </select>
                </div>
          
                <!-- Registration Year To -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Registration Year To</label>
                  <select 
                    v-model="filters.year.to" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">Any Year</option>
                    <option v-for="year in yearOptions" :key="'to-'+year" :value="year">{{ year }}</option>
                  </select>
                </div>
              </div>
          
              <!-- Car Price Range and Mileage Dropdowns -->
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <!-- Price From -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Price From</label>
                  <select 
                    v-model="filters.price.min" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">No Min</option>
                    <option v-for="price in priceMinOptions" :key="'min-'+price" :value="price">{{ formatPrice(price) }}</option>
                  </select>
                </div>
          
                <!-- Price To -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Price To</label>
                  <select 
                    v-model="filters.price.max" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">No Max</option>
                    <option v-for="price in priceMaxOptions" :key="'max-'+price" :value="price">{{ formatPrice(price) }}</option>
                  </select>
                </div>
          
                <!-- Mileage From -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Mileage From</label>
                  <select 
                    v-model="filters.mileage.min" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">No Min</option>
                    <option v-for="mileage in mileageMinOptions" :key="'min-'+mileage" :value="mileage">{{ formatMileage(mileage) }}</option>
                  </select>
                </div>
          
                <!-- Mileage To -->
                <div class="filter-dropdown">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Mileage To</label>
                  <select 
                    v-model="filters.mileage.max" 
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                  >
                    <option value="">No Max</option>
                    <option v-for="mileage in mileageMaxOptions" :key="'max-'+mileage" :value="mileage">{{ formatMileage(mileage) }}</option>
                  </select>
                </div>
              </div>
          
              <!-- Body Style Dropdown -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Body Style</label>
                <select 
                  v-model="filters.bodyStyle" 
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2"
                >
                  <option value="">All Body Styles</option>
                  <option v-for="style in bodyStyles" :key="style.value" :value="style.value">{{ style.label }}</option>
                </select>
              </div>
          
              <!-- Filter Actions -->
              <div class="flex justify-between items-center">
                <button 
                  @click="resetFilters" 
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-lg transition duration-200"
                  :disabled="isLoading"
                >
                  Reset Filters
                </button>
                
                
                <button 
                  @click="fetchSearchResults" 
                  class="px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition duration-200"
                  :disabled="isLoading"
                >
                  Apply Filters
                </button>
              </div>
            </div>

            <!-- Main Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

              <!-- Filters Sidebar -->
              <div class="lg:col-span-1" :class="{ 'hidden lg:block': !isFiltersOpen }">
                <div class="sticky top-8 space-y-6 bg-white p-6 rounded-lg shadow">

                  <!-- Filter Header -->
                  <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">Filters</h2>
                    <button @click="clearFilters" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                      Clear all
                    </button>
                  </div>

                  <!-- Category Filter -->
                  <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Category</h3>
                    <div class="space-y-2">
                      <div
                        v-for="category in categories"
                        :key="category.cat_title"
                        @click="selectedCategory = category._id; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedCategory === category._id }"
                      >
                        <img loading="lazy" :src="'/storage/' + category.image" alt="Category" class="w-8 h-8 object-cover rounded" />
                        <span class="ml-3 text-sm text-gray-700">{{ category.cat_title }}</span>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full ml-auto">
                          {{ category.products_count }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Make Filter -->
                  <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Make</h3>
                    <div class="space-y-2">
                      <div
                        v-for="make in makes"
                        :key="make.cat_title"
                        @click="selectedMake = make._id; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedMake === make._id }"
                      >
                        <img loading="lazy" :src="'/storage/'+make.image" alt="Make" class="w-8 h-8 object-cover rounded" style="object-fit:contain" />
                        <span class="ml-3 text-sm text-gray-700">{{ make.cat_title }}</span>
                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full ml-auto">
                          {{ make.products_count }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Body Style Filter -->
                  <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Body Style</h3>
                    <div class="space-y-2">
                      <div
                        v-for="style in bodyStyles"
                        :key="style.label"
                        @click="selectedBodyStyle = style.value; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedBodyStyle === style.value }"
                      >
                      <img loading="lazy" :src="'/assets/images/' + style.icon" alt="Category" class="w-8 h-8 object-cover rounded" />
                      <span class="ml-3 text-sm text-gray-700">{{ style.label }}</span>
                      </div>
                    </div>
                  </div>


                  <!-- Price Filter -->
                  <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Price</h3>
                    <div class="space-y-2">
                      <div
                        v-for="range in priceRanges"
                        :key="range.value"
                        @click="selectedPriceRange = range.value; applyFilters()"
                        class="cursor-pointer p-2 rounded-lg transition hover:bg-gray-100 text-sm text-gray-700 flex items-center gap-2"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedPriceRange === range.value }"
                      >
                        <span class="text-primary-600 text-lg font-semibold">$</span>
                        <span class="text-black text-md">{{ range.label }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Country Filter -->
                  <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-md font-medium text-gray-900 mb-3">Country</h3>
                    <div class="space-y-2">
                      <div
                        @click="selectedCountry = 'China'; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedCountry === 'China' }"
                      >
                        <img loading="lazy" src="https://flagcdn.com/w40/cn.png" alt="China" class="w-8 h-6 object-cover" />
                        <span class="ml-3 text-sm text-gray-700">China</span>
                      </div>
                      <div
                        @click="selectedCountry = 'Japan'; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedCountry === 'Japan' }"
                      >
                        <img loading="lazy" src="https://flagcdn.com/w40/jp.png" alt="Japan" class="w-8 h-6 object-cover" />
                        <span class="ml-3 text-sm text-gray-700">Japan</span>
                      </div>
                      <!-- <div
                        @click="selectedCountry = 'Thailand'; applyFilters()"
                        class="flex items-center cursor-pointer p-2 rounded-lg transition hover:bg-gray-100"
                        :class="{ 'bg-blue-50 border border-blue-500': selectedCountry === 'Thailand' }"
                      >
                        <img src="https://flagcdn.com/w40/th.png" alt="Thailand" class="w-8 h-6 object-cover" />
                        <span class="ml-3 text-sm text-gray-700">Thailand</span>
                      </div> -->
                    </div>
                  </div>

                </div>
              </div>


              <!-- Products Grid -->
              <div class="lg:col-span-3">
                <!-- Sort and View Options -->
                <div class="flex flex-wrap items-center justify-between mb-6 pb-4 border-b border-gray-200">
                  <div class="w-full sm:w-auto mb-3 sm:mb-0">
                    <p class="text-sm text-gray-500">
                      Showing <span class="font-medium text-gray-900">{{ products.data.length }}</span> results
                    </p>
                  </div>

                  <div class="w-full sm:w-auto flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <div class="inline-flex rounded-md shadow-sm">
                      <button @click="viewMode = 'grid'" type="button" :class="[
                        viewMode === 'grid'
                          ? 'bg-blue-600 text-white'
                          : 'bg-white text-gray-700 hover:bg-gray-50',
                        'relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 text-sm font-medium focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500'
                      ]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                      </button>
                      <button @click="viewMode = 'list'" type="button" :class="[
                        viewMode === 'list'
                          ? 'bg-blue-600 text-white'
                          : 'bg-white text-gray-700 hover:bg-gray-50',
                        'relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 text-sm font-medium focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500'
                      ]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- No Results Message -->
                <!-- Loading State -->
                <div v-if="isLoading" class="flex items-center justify-center bg-white rounded-lg shadow-md p-8">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span class="text-gray-700 text-sm">Loading...</span>
                </div>

                <!-- No Results State -->
                <div v-else-if="products.data.length === 0" class="bg-white rounded-lg shadow-md p-8 text-center">
                  <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <h3 class="mt-2 text-lg font-medium text-gray-900">No vehicles found</h3>
                  <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                  <div class="mt-6">
                    <button @click="clearFilters"
                      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                      Clear filters
                    </button>
                  </div>
                </div>


                <!-- Grid View -->
                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  <div v-for="product in products.data" :key="product._id"
                    class="group bg-white rounded-lg shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                    <!-- Product Image with Overlay -->
                    <div class="relative h-52 w-full bg-gray-200 overflow-hidden">
                      <img loading="lazy" :src="(product.front_image).includes('product_images') ? '/storage/' + product.front_image : product.front_image" :alt="product.title" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                      <!-- Category Badge -->
                      <div class="absolute bottom-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                        <img loading="lazy" src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                      </div>
                      <div class="absolute top-3 left-3 transition-opacity duration-300">
                        <span
                          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
                          {{ product.category.cat_title }}
                        </span>
                        <span v-if="product.make"
                          class="ml-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
                          {{ product.make.cat_title }}
                        </span>
                      </div>

                      <!-- Country Badge -->
                      <div class="absolute top-3 right-3">
                        <div
                          class="bg-white/90 backdrop-blur-sm rounded-full px-2 py-1 shadow-md flex items-center gap-1">
                          <span class="text-xs font-medium text-gray-800">{{ product.country }}</span>
                        </div>
                      </div>

                      <!-- Gradient Overlay -->

                    </div>

                    <!-- Product Info -->
                    <div class="p-4">
                      <div class="mb-2">
                        <h3 class="text-xl font-bold truncate" :title="product.title">
                          {{ product.title.length > 40 ? product.title.substring(0, 40) + '...' : product.title }}
                        </h3>
                        <span v-if="['tcv', 'suprememotors', 'electricvehicles'].some(substring => product.website.includes(substring))" class="text-md font-bold text-[#8e2527]">${{ product.price.toLocaleString() }}</span>
                      </div>


                      <p class="text-gray-700 mb-4 text-sm line-clamp-3"
                        v-html="truncateHTML(product.product_details, 180)"></p>


                      <div class="mt-3 flex justify-between items-center">
                        <a :href="route('inventory.product-detail', { id: product.id })" target="_blank"
                          class="inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          <span>View Details</span>
                        </a>


                      </div>
                    </div>
                  </div>
                </div>

                <!-- List View -->
                <div v-else class="space-y-4">
                  <div v-for="product in products.data" :key="product._id"
                    class="group bg-white rounded-lg shadow-md overflow-hidden flex flex-col md:flex-row transition-all duration-300 hover:shadow-xl border border-transparent hover:border-blue-100">
                    <!-- Product Image -->
                    <div class="relative h-56 md:h-auto md:w-1/3 bg-gray-200 overflow-hidden">
                      <img loading="lazy" :src="(product.front_image).includes('product_images') ? '/storage/' + product.front_image : product.front_image" :alt="product.title"
                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        style="aspect-ratio: 2 / 1;">
                      <!-- Overlay with badges -->
                      <div class="absolute inset-0 bg-gradient-to-br from-black/20 to-transparent">
                        <div class="absolute top-3 left-3">
                          <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-600 text-white shadow-sm">
                            {{ product.category.cat_title }}
                          </span>
                          <span v-if="product.make"
                            class="ml-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-600 text-white">
                            {{ product.make.cat_title }}
                          </span>
                        </div>
                      
                        <div class="absolute bottom-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                          <img loading="lazy" src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                        </div>
                      </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-6 flex-1 flex flex-col">
                      <div class="flex-1">
                        <!-- Title and Country -->
                        <div class="flex justify-between items-start mb-2">
                          <h3 class="text-xl font-bold pr-2" :title="product.title">
                            {{ product.title.length > 60 ? product.title.substring(0, 60) + '...' : product.title }}
                          </h3>

                          <div
                            class="bg-gray-100 rounded-full px-3 py-1 inline-flex items-center gap-1 text-xs font-medium text-gray-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                              stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ product.country }}
                          </div>
                        </div>

                        <span v-if="['tcv', 'suprememotors', 'electricvehicles'].some(substring => product.website.includes(substring))" class="text-lg font-bold text-[#8e2527]">${{ product.price.toLocaleString() }}</span>
                        <!-- Description -->
                        <p class="text-gray-700 mb-4 text-sm line-clamp-3"
                          v-html="truncateHTML(product.product_details, 180)"></p>


                      </div>

                      <div class="mt-6 flex justify-between items-center">
                        <div class="flex space-x-2">
                          <a :href="route('inventory.product-detail', { id: product.id })" target="_blank"
                            class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                              stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View Details
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex flex-col space-y-4">
                  <div class="flex flex-col sm:flex-row items-center justify-between border-t border-gray-200 pt-6">
                    <!-- Mobile pagination controls -->
                    <div class="flex flex-1 justify-between sm:hidden w-full">
                      <button 
                        @click="goToPage(products.current_page - 1)" 
                        :disabled="!products.prev_page_url"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium"
                        :class="products.prev_page_url ? 'text-gray-500 hover:bg-gray-50' : 'text-gray-300 cursor-not-allowed'">
                        Previous
                      </button>
                      <button 
                        @click="goToPage(products.current_page + 1)" 
                        :disabled="!products.next_page_url"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium"
                        :class="products.next_page_url ? 'text-gray-500 hover:bg-gray-50' : 'text-gray-300 cursor-not-allowed'">
                        Next
                      </button>
                    </div>

                    <!-- Desktop pagination layout -->
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between w-full">
                      <div>
                        <p class="text-sm text-gray-700">
                          Showing
                          <span class="font-medium">{{ products.from }}</span>
                          to
                          <span class="font-medium">{{ products.to }}</span>
                          of
                          <span class="font-medium">{{ products.total }}</span>
                          products
                        </p>
                      </div>

                      <div v-if="products.last_page > 1" class="pagination-controls">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                          <!-- Previous page button -->
                          <button 
                            @click="goToPage(products.current_page - 1)" 
                            :disabled="!products.prev_page_url"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium"
                            :class="products.prev_page_url ? 'text-gray-500 hover:bg-gray-50' : 'text-gray-300 cursor-not-allowed'">
                            Previous
                          </button>

                          <!-- First page -->
                          <button 
                            @click="goToPage(1)"
                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                            :class="products.current_page === 1
                              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'">
                            1
                          </button>

                          <!-- Ellipsis before middle pages -->
                          <span v-if="products.current_page > 3"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                            ...
                          </span>

                          <!-- Dynamic middle pages -->
                          <template v-for="page in middlePages" :key="page">
                            <button 
                              @click="goToPage(page)"
                              class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                              :class="products.current_page === page
                                ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'">
                              {{ page }}
                            </button>
                          </template>

                          <!-- Ellipsis after middle pages -->
                          <span v-if="products.current_page < products.last_page - 2"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                            ...
                          </span>

                          <!-- Last page -->
                          <button 
                            v-if="products.last_page > 1"
                            @click="goToPage(products.last_page)"
                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                            :class="products.current_page === products.last_page
                              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'">
                            {{ products.last_page }}
                          </button>

                          <!-- Next page button -->
                          <button 
                            @click="goToPage(products.current_page + 1)" 
                            :disabled="!products.next_page_url"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium"
                            :class="products.next_page_url ? 'text-gray-500 hover:bg-gray-50' : 'text-gray-300 cursor-not-allowed'">
                            Next
                          </button>
                        </nav>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </section>

        <!-- Featured Products Section -->
        <section class="py-12 bg-gradient-to-b from-white to-gray-100">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
              <h2 class="text-3xl font-bold text-gray-900 mb-4">Featured Vehicles</h2>
              <p class="text-lg text-gray-600 max-w-2xl mx-auto">Discover our handpicked selection of exceptional
                vehicles that represent the pinnacle of design, performance, and luxury.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div v-for="fp in featured_products"
                class="group bg-white rounded-xl overflow-hidden shadow-lg transition-all duration-300 hover:-translate-y-2 hover:shadow-xl">
                <div class="relative">
                  <img loading="lazy" :src="(fp.front_image).includes('product_images') ? '/storage/' + fp.front_image : fp.front_image" alt="Vehicle image"
                    class="w-full h-64 object-cover transition-transform duration-700 group-hover:scale-105">
                  <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
                    <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                      <span class="inline-block bg-blue-600 text-white rounded-full px-3 py-1 text-xs font-semibold">{{
                        fp.country }}</span>
                      <span class="inline-block bg-gray-800 text-white rounded-full px-3 py-1 text-xs font-semibold">{{
                        fp.category.cat_title }}</span>
                    </div>
                    <div class="absolute top-4 right-4 z-10 bg-black bg-opacity-80 p-2 rounded-md shadow-md">
                      <img loading="lazy" src="/assets/images/site-logo.png" alt="Logo" class="h-8 w-auto" />
                    </div>
                    <div class="p-6 text-white w-full">
                      <h3 class="text-xl font-bold truncate">{{ fp.title.length > 40 ? fp.title.substring(0, 40) + '...'
                        : fp.title }}</h3>
                    </div>
                  </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                  <span v-if="['tcv','suprememotors', 'electricvehicles'].some(substring => fp.website.includes(substring))" class="text-lg font-bold text-[#8e2527]">${{ fp.price.toLocaleString() }}</span>
                  <p class="text-gray-700 mb-4 text-sm line-clamp-3" v-html="truncateHTML(fp.product_details, 120)"></p>

                  <!-- Action buttons -->
                  <div class="flex justify-between items-center">
                    <a :href="route('inventory.product-detail', { id: fp.id })" target="_blank"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors shadow-md flex items-center gap-1">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                        </path>
                      </svg>
                      View Details
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
        <!-- CTA Section -->
        <section class="py-12 bg-blue-800">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
              <h2 class="text-3xl font-bold text-white mb-4">Ready to Experience Supreme Motors?</h2>
              <p class="text-lg text-blue-100 max-w-2xl mx-auto mb-8">Contact us today to discover the perfect vehicle
                for your lifestyle.</p>
              <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#"
                  class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md">
                  Contact Us
                </a>
              </div>
            </div>
          </div>
        </section>
      </main>
    </FrontLayout>
  </div>
</template>
<style scoped>
.pagination-controls .active {
  animation: pulse-blue 2s infinite;
}

@keyframes pulse-blue {
  0% {
    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5);
  }

  70% {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0);
  }

  100% {
    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
  }
}
</style>
<script>
import axios from 'axios';

export default {
  data() {
    return {
      perPage: 10,
      selectedCategory: null,
      selectedMake: null,
      selectedCountry: null,
      searchQuery: '',
      debounceTimeout: null,
      filters: {
        searchQuery: '',
        make: '',
        price: {
          min: '',
          max: ''
        },
        year: {
          from: '',
          to: ''
        },
        mileage: {
          min: '',
          max: ''
        },
        bodyStyle: '',
        search : false,
      },
      priceRanges: [
        { label: 'Under $500', value: 'under-500' },
        { label: '$500 - $1,000', value: '500-1000' },
        { label: '$1,000 - $2,000', value: '1000-2000' },
        { label: '$2,000 - $5,000', value: '2000-5000' },
        { label: '$5,000 - $10,000', value: '5000-10000' },
        { label: '$10,000 - $20,000', value: '10000-20000' },
        { label: 'Over $20,000', value: 'over-20000' },
      ],
      selectedPriceRange: null,
      selectedBodyStyle: null,
      bodyStyles: [
      {
        label: 'Van / Minivan',
        value: 'Van / Minivan',
        icon : 'mini-van.png',
      },
      {
        label: 'Hardtop',
        value: 'Hardtop',
        icon : 'hardtop.png',
      },
      {
        label: 'Mpv',
        value: 'Mpv',
        icon : 'mpv.png',
      },
      {
        label: 'Van',
        value: 'Van',
        icon : 'van.png',
      },
      {
        label: 'Roadster',
        value: 'Roadster',
        icon : 'roadster.png',
      },
      {
        label: 'Targa',
        value: 'Targa',
        icon : 'targa.png',
      },
      {
        label: 'Cabriolet',
        value: 'Cabriolet',
        icon : 'cabriolet.png',
      },
      {
        label: 'Mini Vehicle',
        value: 'Mini Vehicle',
        icon : 'mini-vehicle.png',
      },
      {
        label: 'Convertible',
        value: 'Convertible',
        icon : 'covertible.png',
      },
      {
        label: 'Truck',
        value: 'Truck',
        icon : 'trucks.png',
      },
      {
        label: 'Bus',
        value: 'Bus',
        icon : 'buses.png',
      },
      {
        label: 'Sedan',
        value: 'Sedan',
        
        icon : 'sedan.png',
      },
      {
        label: 'Wagon',
        value: 'Wagon',
        icon : 'van-car.png',  
      },
      {
        label: 'SUV',
        value: 'SUV',
        icon : 'suv-car.png',
      },
      {
        label: 'Coupe',
        value: 'Coupe',
        icon : 'sports-car.png',
      },
      {
        label: 'Hatchback',
        value: 'Hatchback',
        icon : 'hatch-back.png',
      }
    ],// Add loading state


    isLoading: false,
    };
  },
  computed: {
    yearOptions() {
      const currentYear = new Date().getFullYear();
      const years = [];
      for (let year = currentYear; year >= 1970; year--) {
        years.push(year);
      }
      return years;
    },
    priceMinOptions() {
      return [500, 1000, 2000, 5000, 10000, 15000, 20000, 30000, 40000, 50000];
    },
    priceMaxOptions() {
      return [1000, 2000, 5000, 10000, 15000, 20000, 30000, 40000, 50000, 75000, 100000, 150000, 200000];
    },
    mileageMinOptions() {
      return [1000, 5000, 10000, 20000, 30000, 40000, 50000, 75000, 100000];
    },
    mileageMaxOptions() {
      return [5000, 10000, 20000, 30000, 40000, 50000, 75000, 100000, 150000, 200000, 250000];
    },
    middlePages() {
      const pages = [];

      // Show a window of 5 pages centered on the current page when possible
      let start = Math.max(2, this.products.current_page - 2);
      let end = Math.min(this.products.last_page - 1, this.products.current_page + 2);

      // Adjust the window if we're near the beginning or end
      if (this.products.current_page <= 3) {
        end = Math.min(5, this.products.last_page - 1);
      }

      if (this.products.current_page >= this.products.last_page - 2) {
        start = Math.max(2, this.products.last_page - 4);
      }

      // Add the pages to our array
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      return pages;
    }
  },

  methods: {


    debounceSearch() {
      clearTimeout(this.debounceTimeout);
      this.debounceTimeout = setTimeout(() => {
        this.applyFilters();
      }, 500);
    },
    
    formatPrice(price) {
      return '$' + price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },
    
    formatMileage(mileage) {
      return mileage.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' km';
    },
    
    resetFilters() {
      this.filters = {
        searchQuery: '',
        make: '',
        price: {
          min: '',
          max: ''
        },
        year: {
          from: '',
          to: ''
        },
        mileage: {
          min: '',
          max: ''
        },
        bodyStyle: ''
      };
      this.fetchSearchResults();
    },

    fetchSearchResults() {
      // Set loading state to true before making API call
      this.isLoading = true;
      
      this.filters.search = true; // Set search mode to true
      // Prepare parameters for API call - sending directly as query params
      const params = {
        type: 'search',
      };
      
      if (this.filters.searchQuery.trim()) {
        params.search = this.filters.searchQuery.trim();
      }
      
      if (this.filters.make) {
        params.make = this.filters.make;
      }
      
      if (this.filters.price.min && this.filters.price.max) {
        // Convert to price range format expected by backend
        console.log("Price Range:", this.filters.price.min, this.filters.price.max);
        params.price_min = this.filters.price.min;
        params.price_max = this.filters.price.max;
      }
      
      if (this.filters.year.from) {
        params.year_from = this.filters.year.from;
      }
      
      if (this.filters.year.to) {
        params.year_to = this.filters.year.to;
      }
      
      if (this.filters.mileage.min) {
        params.mileage_min = this.filters.mileage.min;
      }
      
      if (this.filters.mileage.max) {
        params.mileage_max = this.filters.mileage.max;
      }
      
      if (this.filters.bodyStyle) {
        params.body_style = this.filters.bodyStyle;
      }
      
      // Make API call to fetch filtered results
      axios
        .get(route('inventory.listing'), { params })
        .then(response => {
          this.$page.props.products = response.data;
          console.log("Filtered Results:", response.data);
          this.isLoading = false; // Set loading state to false after successful response
        })
        .catch(error => {
          console.error("Filter Error:", error);
          this.isLoading = false; // Set loading state to false after error
        });
    },
    truncateHTML(htmlString, maxLength) {
      if (!htmlString) return '';
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = htmlString;
      const textContent = tempDiv.textContent || tempDiv.innerText;
      // if (textContent.length <= 500) {
        return htmlString;
      // }
      // return textContent;
    },
    getPageUrl(page) {
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set('page', page);
      const baseUrl = new URL(this.products.path, window.location.origin);
      currentUrl.searchParams.forEach((value, key) => {
        baseUrl.searchParams.set(key, value);
      });
      return baseUrl.pathname + baseUrl.search;
    },
    goToPage(page) {
      if (!page || page < 1 || page > this.products.last_page) {
        return;
      }

      this.isLoading = true;

      let params = {};

      if (this.filters && this.filters.search) {
        // If in search mode, build params based on active search filters
        params.type = 'search';

        if (this.filters.searchQuery.trim()) {
          params.search = this.filters.searchQuery.trim();
        }

        if (this.filters.make) {
          params.make = this.filters.make;
        }

        if (this.filters.price.min && this.filters.price.max) {
          params.price_min = this.filters.price.min;
          params.price_max = this.filters.price.max;
        }

        if (this.filters.year.from) {
          params.year_from = this.filters.year.from;
        }

        if (this.filters.year.to) {
          params.year_to = this.filters.year.to;
        }

        if (this.filters.mileage.min) {
          params.mileage_min = this.filters.mileage.min;
        }

        if (this.filters.mileage.max) {
          params.mileage_max = this.filters.mileage.max;
        }

        if (this.filters.bodyStyle) {
          params.body_style = this.filters.bodyStyle;
        }
        params.page = page;
      } else {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('page', page);

        params = Object.fromEntries(urlParams);
      }
      console.log("Pagination Params:", params);

      axios
        .get(route('inventory.listing'), { params })
        .then(response => {
          this.$page.props.products = response.data;

          // Update the URL with new query params
          // const queryString = new URLSearchParams(params).toString();
          // window.history.replaceState({}, '', `${window.location.pathname}?${queryString}`);

          this.isLoading = false;
        })
        .catch(error => {
          console.error('Pagination Error:', error);
          this.isLoading = false;
        });
    },

    applyFilters() {
      this.isLoading = true;

      const urlParams = new URLSearchParams(window.location.search);

      this.filters.search = false; // Set search mode to false

      // Update URL parameters based on selected filters
      if (this.selectedCategory) {
        urlParams.set('category', this.selectedCategory);
      } else {
        urlParams.delete('category');
      }

      if (this.selectedCountry) {
        urlParams.set('country', this.selectedCountry);
      } else {
        urlParams.delete('country');
      }

      if (this.selectedMake) {
        urlParams.set('make', this.selectedMake);
      } else {
        urlParams.delete('make');
      }

      if (this.selectedPriceRange) {
        urlParams.set('price', this.selectedPriceRange);
      } else {
        urlParams.delete('price');
      }

      if (this.selectedBodyStyle) {
        urlParams.set('body_style', this.selectedBodyStyle);
      } else {
        urlParams.delete('body_style');
      }

      // Always reset page to 1 when filters are applied
      urlParams.set('page', 1);

      console.log("Filter URL Params:", urlParams.toString());  

      // Make API call using axios
      axios
        .get(route('inventory.listing'), { params: Object.fromEntries(urlParams) })
        .then(response => {
          this.$page.props.products = response.data;
          // window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
          this.isLoading = false;
        })
        .catch(error => {
          console.error('Apply Filters Error:', error);
          this.isLoading = false;
        });
    },


    clearFilters() {
      this.selectedCategory = null;
      this.selectedMake = null;
      this.selectedCountry = null;
      this.selectedPriceRange = null;
      this.selectedBodyStyle = null;

      const url = new URL(window.location.href);
      url.searchParams.delete('category');
      url.searchParams.delete('country');
      url.searchParams.delete('make');
      url.searchParams.delete('body_style');
      url.searchParams.delete('price');
      url.searchParams.set('page', 1);

      router.visit(url.toString(), {
        preserveScroll: true,
        preserveState: true,
      });
    },
  },

  mounted() {
    const urlParams = new URLSearchParams(window.location.search);

    const categoryParam = urlParams.get('category');
    const makeParam = urlParams.get('make');
    const countryParam = urlParams.get('country');
    const priceParam = urlParams.get('price');
    const bodyStyleParam = urlParams.get('body_style');

    if (categoryParam) {
      this.selectedCategory = categoryParam;
    }
    
    if (bodyStyleParam) {
      this.selectedBodyStyle = bodyStyleParam;
    }

    if (makeParam) {
      this.selectedMake = makeParam;
    }

    if (priceParam) {
      this.selectedPriceRange = priceParam;
    }

    if (countryParam) {
      this.selectedCountry = countryParam;
    }

    if (this.products?.per_page) {
      this.perPage = this.products.per_page;
    }
  }

};
</script>