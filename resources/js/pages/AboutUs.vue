<script setup>
  import { Head, Link } from '@inertiajs/vue3';
  import FrontLayout from '@/layouts/app/FrontLayout.vue';
  
  const props = defineProps({
    auth: Object,
  });
</script>

<template>
  <Head title="About Us | Supreme Motors" />

  <div class="flex flex-col min-h-screen">
    <FrontLayout>
      <main class="flex-grow">
        <!-- Breadcrumb Hero Section -->
        <section class="py-24 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-repeat" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')"></div>
          </div>
          
          <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col items-center text-center">
              <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-fade-in-down">Our Journey of Excellence</h1>
              <p class="text-xl md:text-2xl max-w-3xl mb-8 animate-fade-in-up">Discover the story behind Supreme Motors and our commitment to automotive excellence</p>
              <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                  <li class="inline-flex items-center">
                    <Link href="/" class="inline-flex items-center text-white hover:text-gray-200 transition duration-300">
                      <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                      </svg>
                      Home
                    </Link>
                  </li>
                  <li>
                    <div class="flex items-center">
                      <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span class="text-white ml-1 md:ml-2">About Us</span>
                    </div>
                  </li>
                </ol>
              </nav>
            </div>
          </div>
        </section>
      
        <!-- Our Story Section -->
        <section class="py-16 md:py-24 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center gap-12">
              <div class="md:w-1/2 transform transition hover:scale-105 duration-500">
                <img src="/assets/images/slider/banner-1.jpg" alt="Supreme Motors Headquarters" class="rounded-lg shadow-xl w-full h-auto object-cover hover:shadow-2xl transition duration-300" />
              </div>
              <div class="md:w-1/2">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 relative">
                  <span class="inline-block pr-2 bg-white relative z-10">Our Story</span>
                  <!-- <span class="absolute left-0 bottom-3 w-full h-3 bg-blue-100 -z-0"></span> -->
                </h2>
                <p class="text-lg text-gray-700 mb-4">
                  Founded in 2005, Supreme Motors began as a small dealership with a big dream: to revolutionize the automotive industry by putting customer satisfaction and vehicle quality above all else.
                </p>
                <p class="text-lg text-gray-700 mb-4">
                  What started as a modest showroom with just five employees has grown into a nationwide enterprise with multiple locations, serving thousands of satisfied customers each year. Our growth is testament to our unwavering commitment to excellence in every aspect of our business.
                </p>
                <p class="text-lg text-gray-700">
                  Today, we're proud to be one of the leading automotive dealerships, offering a comprehensive range of new and pre-owned vehicles, financing solutions, and after-sales services that set industry standards.
                </p>
              </div>
            </div>
          </div>
        </section>
      
        <!-- Mission & Vision Section with Cards -->
        <section class="py-16 md:py-24 bg-gray-50">
          <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
              <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 relative inline-block">
                <span class="inline-block px-2 relative z-10">Our Mission & Vision</span>
                <!-- <span class="absolute left-0 bottom-2 w-full h-3 bg-emerald-100 -z-0"></span> -->
              </h2>
              <p class="text-xl text-gray-700 max-w-3xl mx-auto">
                Guiding principles that drive our journey toward automotive excellence
              </p>
            </div>
      
            <div class="grid md:grid-cols-2 gap-12">
              <!-- Mission Card -->
              <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition duration-500 hover:scale-105 hover:shadow-xl">
                <div class="h-3 bg-blue-600"></div>
                <div class="p-8">
                  <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6 transform transition duration-500 hover:rotate-12">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Mission</h3>
                  <p class="text-lg text-gray-700 mb-4">
                    To provide exceptional automotive experiences by offering superior vehicles, transparent services, and building lasting relationships with our customers.
                  </p>
                  <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Deliver unparalleled customer service at every touchpoint</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Offer vehicles that exceed quality and performance expectations</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Maintain the highest standards of integrity and transparency</span>
                    </li>
                  </ul>
                </div>
              </div>
      
              <!-- Vision Card -->
              <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition duration-500 hover:scale-105 hover:shadow-xl">
                <div class="h-3 bg-emerald-600"></div>
                <div class="p-8">
                  <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mb-6 transform transition duration-500 hover:rotate-12">
                    <svg class="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                      <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                      <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-4">Our Vision</h3>
                  <p class="text-lg text-gray-700 mb-4">
                    To be the most trusted and innovative automotive retailer, setting new standards for excellence in the industry while creating sustainable value for our customers, employees, and communities.
                  </p>
                  <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-emerald-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Pioneer sustainable automotive solutions for a greener future</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-emerald-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Expand our footprint while maintaining personalized customer care</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-emerald-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Leverage technology to enhance customer experience and satisfaction</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      
        <!-- Core Values Section -->
        <section class="py-16 md:py-24 bg-white">
          <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
              <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 relative inline-block">
                <span class="inline-block px-2 relative z-10">Our Core Values</span>
                <!-- <span class="absolute left-0 bottom-2 w-full h-3 bg-blue-100 -z-0"></span> -->
              </h2>
              <p class="text-xl text-gray-700 max-w-3xl mx-auto">
                The principles that guide every decision we make and every interaction we have
              </p>
            </div>
      
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
              <!-- Value 1 -->
              <div class="bg-gray-50 rounded-lg p-6 text-center transform transition duration-500 hover:shadow-xl hover:translate-y-[-10px]">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform duration-500 hover:scale-110">
                  <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Integrity</h3>
                <p class="text-gray-700">
                  We conduct business with unwavering honesty, transparency, and ethical standards in all our dealings.
                </p>
              </div>
      
              <!-- Value 2 -->
              <div class="bg-gray-50 rounded-lg p-6 text-center transform transition duration-500 hover:shadow-xl hover:translate-y-[-10px]">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform duration-500 hover:scale-110">
                  <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Customer Focus</h3>
                <p class="text-gray-700">
                  We place our customers at the center of everything we do, striving to exceed their expectations at every turn.
                </p>
              </div>
      
              <!-- Value 3 -->
              <div class="bg-gray-50 rounded-lg p-6 text-center transform transition duration-500 hover:shadow-xl hover:translate-y-[-10px]">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform duration-500 hover:scale-110">
                  <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Innovation</h3>
                <p class="text-gray-700">
                  We embrace change and continuously seek new ways to improve our products, services, and processes.
                </p>
              </div>
      
              <!-- Value 4 -->
              <div class="bg-gray-50 rounded-lg p-6 text-center transform transition duration-500 hover:shadow-xl hover:translate-y-[-10px]">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 transition-transform duration-500 hover:scale-110">
                  <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Passion</h3>
                <p class="text-gray-700">
                  We are driven by our love for automobiles and our commitment to delivering exceptional experiences.
                </p>
              </div>
            </div>
          </div>
        </section>
      
        <section class="py-16 md:py-24 bg-gray-50">
          <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
              <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 relative inline-block">
                <span class="inline-block px-2 relative z-10">Customer Testimonials</span>
                <!-- <span class="absolute left-0 bottom-2 w-full h-3 bg-blue-100 -z-0"></span> -->
              </h2>
              <p class="text-xl text-gray-700 max-w-3xl mx-auto">
                Read what our satisfied customers are saying about their experience with Supreme Motors
              </p>
            </div>
            
            <!-- Testimonials Grid Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
              <div v-for="(testimonial, index) in displayedTestimonials" :key="index" 
                   class="bg-white rounded-lg shadow-md overflow-hidden transform transition duration-300 hover:shadow-xl hover:-translate-y-1">
                <div class="p-6">
                  <!-- Customer Info -->
                  <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-xl mr-3">
                      {{ testimonial.name.charAt(0) }}
                    </div>
                    <div>
                      <h4 class="font-bold text-lg text-gray-900">{{ testimonial.name }}</h4>
                      <p class="text-gray-600 text-sm">{{ testimonial.role }}</p>
                    </div>
                  </div>
                  
                  <!-- Rating -->
                  <div class="flex mb-3">
                    <svg v-for="star in testimonial.rating" :key="'full-'+star" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <svg v-for="star in (5 - testimonial.rating)" :key="'empty-'+star" class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                  </div>
                  
                  <!-- Testimonial Text -->
                  <div class="relative">
                    <svg class="absolute top-0 left-0 w-8 h-8 text-blue-100 transform -translate-x-3 -translate-y-3" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true">
                      <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z" />
                    </svg>
                    <p class="text-gray-700 italic pl-6 line-clamp-4">{{ testimonial.text }}</p>
                  </div>
                  
                  <!-- Vehicle Info -->
                  <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                    <p class="text-blue-600 font-medium">Supreme Motors Customer</p>
                    <span class="text-xs text-gray-500">{{ testimonial.date }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Pagination Controls -->
            <div class="flex justify-center mt-8 space-x-2">
              <button 
                v-for="pageNumber in totalPages" 
                :key="pageNumber"
                @click="currentPage = pageNumber"
                :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center text-sm transition-colors duration-300',
                  currentPage === pageNumber 
                    ? 'bg-blue-600 text-white font-medium' 
                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-100'
                ]"
              >
                {{ pageNumber }}
              </button>
            </div>
            
            <!-- View All Button -->
            <div class="text-center mt-8">
              <button 
                @click="showAllTestimonials = !showAllTestimonials" 
                class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              >
                <span>{{ showAllTestimonials ? 'Show Less' : 'View All Testimonials' }}</span>
                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
            </div>
          </div>
        </section>
            
      
        <!-- CTA Section -->
        <section class="py-16 md:py-24 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white relative overflow-hidden">
          <!-- Background Pattern -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-repeat" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')"></div>
          </div>
          
          <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row items-center justify-between gap-12">
              <div class="md:w-2/3">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Experience the Supreme Motors Difference?</h2>
                <p class="text-xl mb-6">
                  Join thousands of satisfied customers who have chosen Supreme Motors for their automotive needs. 
                  Our expert team is ready to help you find the perfect vehicle tailored to your lifestyle and budget.
                </p>
                <div class="flex flex-wrap gap-4">
                  <Link href="/contact-us" class="inline-flex items-center px-6 py-3 rounded-lg bg-white text-blue-600 font-medium transition duration-300 hover:bg-gray-100">
                    Contact Us
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                  </Link>
                  <Link href="/inventory" class="inline-flex items-center px-6 py-3 rounded-lg bg-blue-700 text-white font-medium transition duration-300 hover:bg-blue-800">
                    Browse Our Inventory
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                  </Link>
                </div>
              </div>
              <div class="md:w-1/3">
                <div class="bg-white rounded-lg p-6 shadow-xl transform transition duration-500 hover:scale-105">
                  <h3 class="text-blue-600 font-bold text-xl mb-4">Visit Our Showroom</h3>
                  <p class="text-gray-700 mb-4">Experience our vehicles in person and meet our friendly team.</p>
                  <ul class="space-y-3 text-gray-700">
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                      </svg>
                      <span>Unit 1603, 16th Floor, The L. Plaza
                        367 - 375 Queen's Road Central
                        Sheung Wan</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                      </svg>
                      <span>+852 5322 1678</span>
                    </li>
                    <li class="flex items-start">
                      <svg class="w-5 h-5 text-blue-600 mr-2 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.243 5.757a6 6 0 10-.986 9.284 1 1 0 111.087 1.678A8 8 0 1118 10a3 3 0 01-4.8 2.401A4 4 0 1114 10a1 1 0 102 0c0-1.537-.586-3.07-1.757-4.243zM12 10a2 2 0 10-4 0 2 2 0 004 0z" clip-rule="evenodd"></path>
                      </svg>
                      <span>info@suprememotors.ltd</span>
                    </li>
                  </ul>
                </div>
              </div>
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
        currentPage: 1,
        itemsPerPage: 6,
        showAllTestimonials: false,
        testimonials:  [
  {
    "name": "Adrian",
    "role": "Director of Sales",
    "rating": 5,
    "date": "April 10, 2025",
    "text": "Supreme Motors exceeded my expectations. The team helped me find the perfect car for my wife, and I ended up buying two vehicles. Their professionalism and customer service are unmatched."
  },
  {
    "name": "Jason",
    "role": "Marketing Manager",
    "rating": 5,
    "date": "April 9, 2025",
    "text": "I don't even know where to start! Supreme Motors is the best. I've never felt more comfortable buying a car. The staff was responsive and helped me get the car of my dreams."
  },
  {
    "name": "Colin",
    "role": "Real Estate Agent",
    "rating": 5,
    "date": "April 8, 2025",
    "text": "Is there a 7-star rating? Supreme Motors goes above and beyond. They made a believer out of our family. We love the BMW they helped us get."
  },
  {
    "name": "Miguel",
    "role": "Engineer",
    "rating": 5,
    "date": "April 7, 2025",
    "text": "We fell in love with the M3 the moment we saw it online. Supreme Motors provided a professional and courteous experience. The car was exactly as advertised."
  },
  {
    "name": "Quan the Man",
    "role": "Car Enthusiast",
    "rating": 5,
    "date": "April 6, 2025",
    "text": "Supreme Motors is not your typical car dealer. Pat and Jeff are honest and straightforward. They made the car buying experience enjoyable and stress-free."
  },
  {
    "name": "Steven",
    "role": "Business Owner",
    "rating": 5,
    "date": "April 5, 2025",
    "text": "Just picked up a beautiful Mercedes from Jeff and Pat at Supreme Motors. They're straight shooters who take pride in their inventory and business practices."
  },
  {
    "name": "Chris",
    "role": "IT Specialist",
    "rating": 5,
    "date": "April 4, 2025",
    "text": "I bought a 2011 Lexus IS 250 from Supreme Motors. The car is beautiful, and the process was seamless. I would definitely buy another car from them."
  },
  {
    "name": "KP",
    "role": "Sales Executive",
    "rating": 5,
    "date": "April 3, 2025",
    "text": "Recently bought a used BMW M5 from Supreme Motors. The process was painless, and the team was transparent and helpful throughout. Highly recommend!"
  },
  {
    "name": "Heather",
    "role": "Teacher",
    "rating": 5,
    "date": "April 2, 2025",
    "text": "Hands down the best in town. Extremely happy with our experience at Supreme Motors. Jeff and Pat are amazing!"
  },
  {
    "name": "Racheal",
    "role": "Audi Owner",
    "rating": 5,
    "date": "April 1, 2025",
    "text": "Jeff and Pat are trustworthy and kind. I've had multiple interactions with them regarding scheduled maintenance after purchasing my 2013 Audi. Definitely recommend Supreme Motors 100%."
  },
  {
    "name": "Crystal Hogg",
    "role": "Customer",
    "rating": 5,
    "date": "March 31, 2025",
    "text": "Reached out to Supreme Motors about a car I saw online. Jeff was responsive and helpful. The car was too good to be true, but it turned out perfect!"
  },
  {
    "name": "Ricky Lipscomb",
    "role": "Customer",
    "rating": 5,
    "date": "March 30, 2025",
    "text": "Supreme Motors has the best used cars I've seen around. Jeff and Pat were amazing in helping us purchase the 2018 Lexus RX450h. Outstanding customer service!"
  },
  {
    "name": "Sierra Santiago",
    "role": "Customer",
    "rating": 5,
    "date": "March 29, 2025",
    "text": "100% recommend Supreme Motors! Super happy with my purchase. They only have cars in pristine condition!"
  },
  {
    "name": "Anna",
    "role": "Customer",
    "rating": 5,
    "date": "March 28, 2025",
    "text": "Gave me a great deal and quick process. Thank you guys!"
  },
  {
    "name": "Pamela",
    "role": "Customer",
    "rating": 5,
    "date": "March 27, 2025",
    "text": "Nice experience at Supreme Motors."
  },
  {
    "name": "Bigmac6516",
    "role": "Customer",
    "rating": 5,
    "date": "March 26, 2025",
    "text": "Amazing customer service! Matt was awesome in every aspect. Everything was smooth and quick. Best car buying experience ever!"
  },
  {
    "name": "Andy Sing",
    "role": "Customer",
    "rating": 5,
    "date": "March 25, 2025",
    "text": "They went above and beyond to help me with purchasing a car. Reach out to Matt and staff for a great deal."
  },
  {
    "name": "Godwin",
    "role": "Customer",
    "rating": 5,
    "date": "March 24, 2025",
    "text": "Very professional, reasonable negotiations, and extremely fast to finalize. Highly recommended!"
  },
  {
    "name": "Tj",
    "role": "Customer",
    "rating": 5,
    "date": "March 23, 2025",
    "text": "Matt was wonderful to work with. Answered every question flawlessly. Amazing experience overall. 5 stars for sure!"
  }
]

      }
        
    },
    computed: {
      displayedTestimonials() {
        if (this.showAllTestimonials) {
          return this.testimonials;
        } else {
          const start = (this.currentPage - 1) * this.itemsPerPage;
          const end = start + this.itemsPerPage;
          return this.testimonials.slice(start, end);
        }
      },
      totalPages() {
        return Math.ceil(this.testimonials.length / this.itemsPerPage);
      }
    },
    methods: {
      // Add animation for new testimonials when they appear
      animateTestimonials() {
        this.$nextTick(() => {
          const elements = document.querySelectorAll('.testimonial-card');
          elements.forEach((el, index) => {
            setTimeout(() => {
              el.classList.add('animate-fade-in');
            }, index * 100);
          });
        });
      }
    },
    watch: {
      currentPage() {
        this.animateTestimonials();
        // Scroll to testimonials section when changing page
        this.$nextTick(() => {
          const element = document.querySelector('.testimonials-section');
          if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      }
    },
    mounted() {
      // Initialize animations
      this.animateTestimonials();
    }
  }
  </script>
  
  <style scoped>
  .line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  
  .animate-fade-in {
    animation: fadeIn 0.5s ease-in-out forwards;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  </style>