<script setup>
  import { Head, Link } from '@inertiajs/vue3';
  import FrontLayout from '@/layouts/app/FrontLayout.vue';
  const props = defineProps({
    auth: Object,
  });
  </script>
  
  <template>
    <Head title="Contact Us" />
  
    <div class="flex flex-col min-h-screen">
      <FrontLayout>
        <main class="flex-grow">
          <!-- Breadcrumb Hero Section -->
          <section class="py-20 bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
              <div class="absolute inset-0 bg-repeat" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNNTAgMzBhMjAgMjAgMCAxIDEtNDAgMCAyMCAyMCAwIDAgMSA0MCAweiIgZmlsbD0iI2ZmZmZmZiIgZmlsbC1vcGFjaXR5PSIwLjIiLz48L3N2Zz4=')"></div>
            </div>
            
            <div class="max-w-7xl mx-auto px-6 relative z-10">
              <div class="flex flex-col items-center text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Contact Us</h1>
                <nav class="flex" aria-label="Breadcrumb">
                  <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                      <Link href="/" class="inline-flex items-center text-white hover:text-gray-200">
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
                        <span class="text-white ml-1 md:ml-2">Contact Us</span>
                      </div>
                    </li>
                  </ol>
                </nav>
              </div>
            </div>
          </section>
  
          <!-- Contact Page Content -->
          <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-6">
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Left Column: Contact Information -->
                <div class="relative rounded-xl overflow-hidden shadow-xl">
                  <div class="absolute inset-0 bg-gradient-to-br from-[#1e4066] to-[#2c5c8e]"></div>
                  <div class="relative p-8 md:p-12 text-white z-10">
                    <h2 class="text-3xl font-bold mb-6">Get In Touch</h2>
                    <p class="text-white/80 mb-8">Our team of experts is ready to help you find the perfect equipment solutions for your construction needs.</p>
                    
                    <div class="space-y-6">
                      <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 bg-white/10 rounded-full flex items-center justify-center mr-4">
                          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          </svg>
                        </div>
                        <div>
                          <h3 class="text-lg font-semibold mb-1">Our Location</h3>
                          <p class="text-white/80">Unit 1603, 16th Floor, The L. Plaza
                            367 - 375 Queen's Road Central
                            Sheung Wan</p>
                        </div>
                      </div>
                      
                      <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 bg-white/10 rounded-full flex items-center justify-center mr-4">
                          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                          </svg>
                        </div>
                        <div>
                          <h3 class="text-lg font-semibold mb-1">Phone</h3>
                          <p class="text-white/80">+852 5322 1678</p>
                        </div>
                      </div>
                      
                      <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 bg-white/10 rounded-full flex items-center justify-center mr-4">
                          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                          </svg>
                        </div>
                        <div>
                          <h3 class="text-lg font-semibold mb-1">Email</h3>
                          <p class="text-white/80">info@suprememotors.ltd</p>
                        </div>
                      </div>
                      
                    </div>
                    
                    <!-- <div class="mt-10">
                      <h3 class="text-lg font-semibold mb-3">Connect With Us</h3>
                      <div class="flex space-x-4">
                        <a href="#" class="h-10 w-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition duration-300">
                          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"></path>
                          </svg>
                        </a>
                        <a href="#" class="h-10 w-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition duration-300">
                          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"></path>
                          </svg>
                        </a>
                        <a href="#" class="h-10 w-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition duration-300">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                            </svg>
                        </a>
                      </div>
                    </div> -->
                  </div>
                </div>
                
                <!-- Right Column: Contact Form -->
                <!-- Right Column: Contact Form -->
<div class="bg-white rounded-xl shadow-lg p-8 md:p-10">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Send Us a Message</h2>
    <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
            <div class="space-y-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" id="name" v-model="formData.name" class="block w-full px-4 py-3 text-gray-700 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1e4066] focus:bg-white" placeholder="John Doe" />
                <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>
            <div class="space-y-2">
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" id="email" v-model="formData.email" class="block w-full px-4 py-3 text-gray-700 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1e4066] focus:bg-white" placeholder="john@example.com" />
                <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
            </div>
        </div>
        
        <div class="space-y-2 mb-6">
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
            <input type="tel" id="phone" v-model="formData.phone" class="block w-full px-4 py-3 text-gray-700 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1e4066] focus:bg-white" placeholder="(555) 123-4567" />
            <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone[0] }}</p>
        </div>
        
        <div class="space-y-2 mb-6">
            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
            <input type="text" id="subject" v-model="formData.subject" class="block w-full px-4 py-3 text-gray-700 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1e4066] focus:bg-white" placeholder="How can we help you?" />
            <p v-if="errors.subject" class="mt-1 text-sm text-red-600">{{ errors.subject[0] }}</p>
        </div>
        
        <div class="space-y-2 mb-8">
            <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
            <textarea id="message" v-model="formData.message" rows="6" class="block w-full px-4 py-3 text-gray-700 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1e4066] focus:bg-white" placeholder="Tell us more about your needs..."></textarea>
            <p v-if="errors.message" class="mt-1 text-sm text-red-600">{{ errors.message[0] }}</p>
        </div>
        
        <div class="flex items-center mb-6">
            <input id="consent" v-model="formData.consent" type="checkbox" class="h-5 w-5 text-[#1e4066] border-gray-300 rounded focus:ring-[#1e4066]" />
            <label for="consent" class="ml-2 block text-sm text-gray-700">I consent to having this website store my submitted information so they can respond to my inquiry.</label>
            <p v-if="errors.consent" class="mt-1 text-sm text-red-600">{{ errors.consent[0] }}</p>
        </div>
        
        <button 
            type="submit" 
            class="w-full bg-gradient-to-r from-[#1e4066] to-[#2c5c8e] text-white py-4 px-8 rounded-lg font-semibold text-lg hover:opacity-90 transition duration-300 shadow-lg hover:shadow-xl flex items-center justify-center"
            :disabled="isSubmitting"
        >
            <span v-if="isSubmitting" class="inline-block mr-2">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
            {{ isSubmitting ? 'Sending...' : 'Send Message' }}
        </button>
        
        <div v-if="formSuccess" class="mt-4 p-4 bg-green-100 text-green-700 rounded-lg">
            {{ successMessage }}
        </div>
        
        <div v-if="formError" class="mt-4 p-4 bg-red-100 text-red-700 rounded-lg">
            {{ formError }}
        </div>
    </form>
</div>
              </div>
            </div>
          </section>
          
          <!-- FAQ Section -->
          <!-- <section class="py-16 bg-gray-50">
            <div class="max-w-5xl mx-auto px-6">
              <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Frequently Asked Questions</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Find answers to the most common questions about our equipment and services.</p>
              </div>
              
              <div class="space-y-4">
                <div class="bg-white rounded-lg shadow-md p-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-2">What types of equipment do you offer?</h3>
                  <p class="text-gray-600">We offer a wide range of construction equipment including excavators, bulldozers, loaders, compactors, cranes, and more. Our catalog includes both new and well-maintained used equipment.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-2">Do you provide equipment rental services?</h3>
                  <p class="text-gray-600">Yes, we offer flexible rental options for all our equipment. Whether you need a machine for a day, a week, or several months, we can accommodate your requirements.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-2">What maintenance services do you provide?</h3>
                  <p class="text-gray-600">Our certified technicians provide comprehensive maintenance services including routine inspections, repairs, parts replacement, and emergency servicing to keep your equipment running efficiently.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-2">How quickly can you deliver equipment?</h3>
                  <p class="text-gray-600">Delivery times vary based on equipment availability and your location. In most cases, we can arrange delivery within 24-48 hours for standard equipment in our inventory.</p>
                </div>
              </div>
            </div>
          </section> -->
          
        </main>
      </FrontLayout>
    </div>
  </template>
  
  <script>
  import axios from 'axios';

  export default {
  data() {
    return {
      formData: {
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
        consent: false
      },
      isSubmitting: false,
      formSuccess: false,
      successMessage: '',
      formError: '',
      errors: {},
      isSubmitting : false,
    }
  },
  methods: {
    async submitForm() {
      this.isSubmitting = true;
      this.formSuccess = false;
      this.formError = '';
      this.errors = {};

      
      try {
        const response = await axios.post('/contact-save', this.formData);
        
        this.formSuccess = true;
        this.successMessage = response.data.message;
        
        // Reset form
        Object.keys(formData).forEach(key => {
          formData[key] = key === 'consent' ? false : '';
        });
        
      } catch (error) {
        console.error('Error submitting form:', error);
        
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors;
          this.formError = 'Please correct the errors in the form.';
        } else if (error.response && error.response.data.message) {
          this.formError = error.response.data.message;
        } else {
          this.formError = 'Network error. Please check your connection and try again.';
        }
      } finally {
        this.isSubmitting = false;
      }
    }
  }
}
  </script>