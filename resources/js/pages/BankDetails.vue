<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import { ref, onMounted } from 'vue';

const props = defineProps({
  auth: Object,
});

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text);
  showToast('Copied to clipboard!');
};

const showingModal = ref(false);
const toastMessage = ref('');
const showingToast = ref(false);

const showToast = (message) => {
  toastMessage.value = message;
  showingToast.value = true;
  setTimeout(() => {
    showingToast.value = false;
  }, 3000);
};

const currencies = [
  { code: 'USD', flag: '🇺🇸', name: 'US Dollar' },
  { code: 'HKD', flag: '🇭🇰', name: 'Hong Kong Dollar' },
  { code: 'AUD', flag: '🇦🇺', name: 'Australian Dollar' },
  { code: 'CAD', flag: '🇨🇦', name: 'Canadian Dollar' },
  { code: 'CNY', flag: '🇨🇳', name: 'Chinese Yuan' },
  { code: 'CHF', flag: '🇨🇭', name: 'Swiss Franc' },
  { code: 'DKK', flag: '🇩🇰', name: 'Danish Krone' },
  { code: 'EUR', flag: '🇪🇺', name: 'Euro' },
  { code: 'GBP', flag: '🇬🇧', name: 'British Pound' },
  { code: 'JPY', flag: '🇯🇵', name: 'Japanese Yen' },
  { code: 'NOK', flag: '🇳🇴', name: 'Norwegian Krone' },
  { code: 'NZD', flag: '🇳🇿', name: 'New Zealand Dollar' },
  { code: 'SEK', flag: '🇸🇪', name: 'Swedish Krona' },
  { code: 'SGD', flag: '🇸🇬', name: 'Singapore Dollar' },
  { code: 'ZAR', flag: '🇿🇦', name: 'South African Rand' },
];
</script>

<template>

  <Head title="Bank Details - Supreme Motor Equipments" />
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
              <h1 class="text-3xl md:text-5xl font-bold mb-4">Bank Transfer Details</h1>
              <p class="text-lg md:text-xl max-w-2xl mb-6">Make secure payments to Supreme Motor Equipments Limited</p>
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
                      <span class="text-white ml-1 md:ml-2">Bank Details</span>
                    </div>
                  </li>
                </ol>
              </nav>
            </div>
          </div>
        </section>

        <!-- Bank Details Section -->
        <section class="py-12 bg-gray-50">
          <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
              <!-- Header Notice -->
              <div class="bg-amber-50 border-l-4 border-amber-500 p-4">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-amber-800">
                      Only SWIFT(T/T) or CHATS/ACH(HK local) payments are supported by the following account.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Bank Details Card -->
              <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6">
                  <!-- Left Column -->
                  <div class="space-y-6">
                    <div>
                      <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-500">SWIFT/BIC</h3>
                        <button @click="copyToClipboard('CHASHKHH')"
                          class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                          </svg>
                          Copy
                        </button>
                      </div>
                      <p class="text-lg font-medium">CHASHKHH <span class="text-sm text-gray-500">
                          (* If 11 characters are required: CHASHKHHXXX)</span></p>
                    </div>

                    <div>
                      <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-500">Account Number</h3>
                        <button @click="copyToClipboard('63003711840')"
                          class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                          </svg>
                          Copy
                        </button>
                      </div>
                      <p class="text-lg font-medium">63003711840</p>
                    </div>

                    <div>
                      <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-500">Account Name</h3>
                        <button @click="copyToClipboard('Supreme Motor Equipments Limited')"
                          class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                          </svg>
                          Copy
                        </button>
                      </div>
                      <p class="text-lg font-medium">Supreme Motor Equipments Limited</p>
                      <p class="text-xs text-gray-500">* If the account name is too long, you can continue filling it
                        into the
                        address field.</p>
                    </div>
                  </div>

                  <!-- Right Column -->
                  <div class="space-y-6">
                    <div>
                      <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-500">Bank Name</h3>
                        <button @click="copyToClipboard('JPMorgan Chase Bank N.A., Hong Kong Branch')"
                          class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                          </svg>
                          Copy
                        </button>
                      </div>
                      <p class="text-lg font-medium">JPMorgan Chase Bank N.A., Hong Kong Branch</p>
                    </div>

                    <div>
                      <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-500">Bank Address</h3>
                        <button
                          @click="copyToClipboard('18/F, 20/F, 22-29/F, CHATER HOUSE, 8 CONNAUGHT ROAD CENTRAL, HONG KONG')"
                          class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                          </svg>
                          Copy
                        </button>
                      </div>
                      <p class="text-lg font-medium">18/F, 20/F, 22-29/F, CHATER HOUSE, 8 CONNAUGHT ROAD CENTRAL, HONG
                        KONG</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Country/Region</h3>
                        <p class="text-lg font-medium">Hong Kong</p>
                      </div>
                      <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Type of Account</h3>
                        <p class="text-lg font-medium">Business Account</p>
                      </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Bank Code</h3>
                        <p class="text-lg font-medium">007</p>
                      </div>
                      <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Branch Code</h3>
                        <p class="text-lg font-medium">863 <span class="text-sm text-gray-500">* If paying from Hong
                            Kong banks</span></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Important Message -->
              <div class="bg-orange-50 p-6">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                      fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-orange-800">Payment Memo Required</h3>
                    <div class="mt-2 text-sm text-orange-700">
                      <p>The following memo/message should be included to the receiver when making a payment:</p>
                      <p class="font-medium mt-1">[Buyer Name][Invoice/Contract Number][Product]</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Acceptable Currencies -->
            <div class="mt-10">
              <h2 class="text-xl font-bold text-gray-800 mb-6">Acceptable Currencies</h2>
              <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-4">
                  <div v-for="currency in currencies" :key="currency.code"
                    class="flex flex-col items-center p-3 rounded-lg hover:bg-blue-50 transition-colors duration-200 cursor-pointer  text-black"
                    @click="copyToClipboard(currency.code)">
                    <span class="text-2xl mb-1">{{ currency.flag }}</span>
                    <span class="font-medium">{{ currency.code }}</span>
                    <span class="text-xs text-gray-500">{{ currency.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Additional Information -->
        <section class="py-12 bg-white">
          <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="grid md:grid-cols-3 gap-8">
              <div class="bg-blue-50 rounded-xl p-6 text-center shadow-sm">
                <div
                  class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Processing Time</h3>
                <p class="text-gray-600">Payments are typically processed within 1-2 business days after receipt.</p>
              </div>

              <div class="bg-blue-50 rounded-xl p-6 text-center shadow-sm">
                <div
                  class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Secure Transactions</h3>
                <p class="text-gray-600">All payment details are securely processed through our banking partners.</p>
              </div>

              <div class="bg-blue-50 rounded-xl p-6 text-center shadow-sm">
                <div
                  class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600 mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Need Help?</h3>
                <p class="text-gray-600">Contact our finance team for payment assistance or questions.</p>
              </div>
            </div>
          </div>
        </section>
      </main>

      <!-- Toast Notification -->
      <div v-if="showingToast"
        class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded shadow-lg flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd" />
        </svg>
        {{ toastMessage }}
      </div>
    </FrontLayout>
  </div>
</template>

<style scoped>
.animate-fade-in-down {
  animation: fadeInDown 0.7s ease-out;
}

.animate-fade-in-up {
  animation: fadeInUp 0.7s ease-out;
}

@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInUp {
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