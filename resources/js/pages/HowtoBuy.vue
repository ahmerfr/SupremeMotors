<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import { ref, onMounted } from 'vue';

const props = defineProps({
  auth: Object,
});

const activeTab = ref('process');
const activeStep = ref(1);

const buyingProcesses = [
  {
    id: 1,
    title: 'Vehicle Selection',
    icon: 'search',
    description: 'Browse our extensive inventory and select the vehicle that meets your requirements.',
    details: 'Our user-friendly website allows you to search by make, model, year, price range, and more. Each listing includes high-resolution images, detailed specifications, and inspection reports.',
  },
  {
    id: 2,
    title: 'Price Quotation',
    icon: 'calculator',
    description: 'Request a detailed price quotation that includes all costs involved.',
    details: 'Your quote will include the vehicle price, shipping costs, insurance, and any applicable taxes or duties. Our quotes are transparent with no hidden fees.',
  },
  {
    id: 3,
    title: 'Payment Process',
    icon: 'credit-card',
    description: 'Make a secure payment using one of our accepted payment methods.',
    details: 'We accept bank transfers, credit cards, and other secure payment methods. Payment instructions will be provided in your invoice.',
  },
  {
    id: 4,
    title: 'Vehicle Shipping',
    icon: 'truck',
    description: 'We arrange shipping of your vehicle to your specified destination.',
    details: 'Our logistics team will handle all shipping arrangements, documentation, and customs clearance procedures to ensure a smooth delivery process.',
  },
  {
    id: 5,
    title: 'Vehicle Delivery',
    icon: 'home',
    description: 'Receive your vehicle at your doorstep or preferred location.',
    details: "Once your vehicle arrives at the destination port, we can arrange for final delivery to your specified address. You'll be notified of the delivery date and time in advance.",
    }
];

const faqs = [
  {
    question: 'How do I know the vehicle condition is as described?',
    answer: 'All our vehicles undergo a rigorous 203-point inspection by certified mechanics. We provide detailed inspection reports and high-resolution images showing the exact condition of each vehicle. Additionally, we offer warranty options for added peace of mind.'
  },
  {
    question: 'What payment methods do you accept?',
    answer: 'We accept bank transfers (T/T), credit cards, and local payment options depending on your country. All payment details and options will be clearly outlined in your invoice.'
  },
  {
    question: 'How long does shipping take?',
    answer: 'Shipping times vary based on your location. Typically, shipping takes 2-3 weeks to countries in Asia, 4-6 weeks to countries in Europe, the Middle East and Oceania, and 6-8 weeks to countries in Africa and the Americas.'
  },
  {
    question: 'Are there any additional fees I should be aware of?',
    answer: 'All fees are transparently included in your quotation. These may include shipping costs, insurance, local duties, and taxes. We provide a comprehensive breakdown so there are no surprises.'
  },
  {
    question: "Can I request a specific vehicle that's not in your inventory?",
      answer: "Yes! We offer a vehicle sourcing service. If you don't see what you're looking for in our inventory, contact our customer service team with your specifications, and we'll find the perfect vehicle for you."
    },
  {
    question: 'Do you offer financing options?',
    answer: "While we don't provide direct financing, we work with several international financial institutions that can help with financing options.Contact our customer service team for recommendations suitable for your country."
    },
];

const showStep = (step) => {
  activeStep.value = step;
};

const switchTab = (tab) => {
  activeTab.value = tab;
};

// Ref for FAQ accordion
const openFaq = ref(null);
const toggleFaq = (index) => {
  if (openFaq.value === index) {
    openFaq.value = null;
  } else {
    openFaq.value = index;
  }
};
</script>

<template>

  <Head title="How to Buy - Supreme Motor Equipments" />
  <div class="flex flex-col min-h-screen">
    <FrontLayout>
      <main class="flex-grow">
        <!-- Hero Section -->
        <section class="py-24 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white relative overflow-hidden">
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-repeat"
              style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')">
            </div>
          </div>

          <div class="max-w-7xl mx-auto px-4 sm:px-6 relative z-10">
            <div class="flex flex-col items-center text-center">
              <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-fade-in-down">How to Buy</h1>
              <p class="text-xl md:text-2xl max-w-3xl mb-8 animate-fade-in-up">Your guide to purchasing quality vehicles
                from Supreme Motor Equipments - simplified and hassle-free</p>
              <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                  <li class="inline-flex items-center">
                    <Link href="/"
                      class="inline-flex items-center text-white hover:text-gray-200 transition duration-300">
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
                      <span class="text-white ml-1 md:ml-2">How to Buy</span>
                    </div>
                  </li>
                </ol>
              </nav>
            </div>
          </div>
        </section>

        <!-- Tabs Section -->
        <section class="py-8 bg-gray-50">
          <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="border-b border-gray-200">
              <nav class="flex justify-center -mb-px" aria-label="Tabs">
                <button @click="switchTab('process')"
                  class="px-6 py-4 text-sm font-medium text-center border-b-2 transition-colors duration-200"
                  :class="activeTab === 'process' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Buying Process
                  </span>
                </button>

                <button @click="switchTab('faq')"
                  class="px-6 py-4 text-sm font-medium text-center border-b-2 transition-colors duration-200"
                  :class="activeTab === 'faq' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    FAQs
                  </span>
                </button>
              </nav>
            </div>
          </div>
        </section>

        <div v-if="activeTab === 'process'">
          <!-- Step Progress Bar -->
          <section class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-between">
                  <div v-for="step in buyingProcesses" :key="step.id" @click="showStep(step.id)"
                    class="flex flex-col items-center cursor-pointer group">
                    <div :class="[
                      'transition-colors duration-200 h-12 w-12 rounded-full border-2 flex items-center justify-center',
                      activeStep === step.id
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : step.id < activeStep
                          ? 'border-blue-600 bg-white text-blue-600'
                          : 'border-gray-300 bg-white text-gray-500 group-hover:border-gray-400'
                    ]">
                      <span v-if="step.id === 1 || step.id > activeStep">{{ step.id }}</span>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                    <div class="text-sm font-medium text-gray-900 mt-2 text-center">{{ step.title }}</div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Step Content -->
          <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
              <div class="bg-blue-50 rounded-xl overflow-hidden shadow-lg">
                <div class="md:flex">
                  <!-- Step Illustration -->
                  <div
                    class="md:w-1/2 bg-gradient-to-br from-blue-600 to-blue-800 text-white p-12 flex flex-col justify-center">
                    <div v-if="activeStep === 1">
                      <div class="text-5xl mb-8">🔍</div>
                      <h3 class="text-3xl font-bold mb-4">Vehicle Selection</h3>
                      <p class="text-blue-100 mb-6">Browse our extensive inventory of quality vehicles from Japan and
                        select the one that matches your requirements.</p>
                      <ul class="space-y-2">
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          High-quality images from multiple angles
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Detailed vehicle specifications
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Inspection reports available
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Advanced search filters
                        </li>
                      </ul>
                    </div>

                    <div v-if="activeStep === 2">
                      <div class="text-5xl mb-8">📝</div>
                      <h3 class="text-3xl font-bold mb-4">Price Quotation</h3>
                      <p class="text-blue-100 mb-6">Request a detailed quotation that includes all costs involved in
                        purchasing and shipping your vehicle.</p>
                      <ul class="space-y-2">
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Transparent pricing breakdown
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Includes shipping and insurance costs
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Estimated import duties and taxes
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          No hidden fees or charges
                        </li>
                      </ul>
                    </div>

                    <div v-if="activeStep === 3">
                      <div class="text-5xl mb-8">💳</div>
                      <h3 class="text-3xl font-bold mb-4">Payment Process</h3>
                      <p class="text-blue-100 mb-6">Once you're satisfied with the quotation, proceed with the payment
                        to secure your vehicle.</p>
                      <ul class="space-y-2">
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Secure payment methods
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Bank transfer options available
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Detailed payment instructions
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Payment confirmation within 24-48 hours
                        </li>
                      </ul>
                    </div>

                    <div v-if="activeStep === 4">
                      <div class="text-5xl mb-8">🚢</div>
                      <h3 class="text-3xl font-bold mb-4">Vehicle Shipping</h3>
                      <p class="text-blue-100 mb-6">After payment confirmation, we prepare your vehicle for shipping to
                        your specified destination.</p>
                      <ul class="space-y-2">
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Full documentation handled by us
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Customs clearance assistance
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Vehicle tracking information
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Regular shipping updates
                        </li>
                      </ul>
                    </div>

                    <div v-if="activeStep === 5">
                      <div class="text-5xl mb-8">🏁</div>
                      <h3 class="text-3xl font-bold mb-4">Vehicle Delivery</h3>
                      <p class="text-blue-100 mb-6">Receive your vehicle at your doorstep or preferred location.</p>
                      <ul class="space-y-2">
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Final delivery coordination
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Condition verification at delivery
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          Documentation handover
                        </li>
                        <li class="flex items-center">
                          <svg class="h-5 w-5 mr-2 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"></path>
                          </svg>
                          After-delivery support
                        </li>
                      </ul>
                    </div>
                  </div>

                  <!-- Step Details -->
                  <div class="md:w-1/2 p-12">
                    <h3 class="text-2xl font-semibold text-gray-900 mb-4">{{ buyingProcesses[activeStep - 1].title }}
                    </h3>
                    <p class="text-lg text-gray-600 mb-6">{{ buyingProcesses[activeStep - 1].description }}</p>
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-100">
                      <h4 class="font-medium text-gray-900 mb-3">What to expect:</h4>
                      <p class="text-gray-600">{{ buyingProcesses[activeStep - 1].details }}</p>
                    </div>

                    <div class="mt-8 flex justify-between">
                      <button v-if="activeStep > 1" @click="showStep(activeStep - 1)"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                          viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Previous
                      </button>
                      <div></div>
                      <button v-if="activeStep < 5" @click="showStep(activeStep + 1)"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Next
                        <svg class="ml-2 -mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                          viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                      <Link v-else href="/inventory"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                      Browse Inventory
                      <svg class="ml-2 -mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div v-if="activeTab === 'faq'" class="py-12 bg-white">
          <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Frequently Asked Questions</h2>
            <div class="space-y-4">
              <div v-for="(faq, index) in faqs" :key="index" class="border border-gray-200 rounded-lg overflow-hidden">
                <button @click="toggleFaq(index)"
                  class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none"
                  :class="openFaq === index ? 'bg-blue-50' : 'bg-white'">
                  <span class="font-medium text-gray-900">{{ faq.question }}</span>
                  <svg class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    :class="openFaq === index ? 'transform rotate-180' : ''" xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                      clip-rule="evenodd" />
                  </svg>
                </button>
                <div v-show="openFaq === index" class="px-6 py-4 bg-white border-t border-gray-200">
                  <p class="text-gray-600">{{ faq.answer }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CTA Section -->
        <section class="py-16 bg-gray-50">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center">
            <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
              Ready to find your perfect vehicle?
            </h2>
            <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">
              Start browsing our inventory or contact our team for personalized assistance.
            </p>
            <div class="mt-8 flex justify-center space-x-4">
              <Link href="/inventory"
                class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
              Browse Inventory
              </Link>
              <Link href="/contact-us"
                class="inline-flex items-center justify-center px-5 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
              Contact Us
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
  },
  mounted() {
  }
}
</script>