<style>
/* Hide Google Translate toolbar */
body > .skiptranslate {
  display: none;
}

.goog-te-banner-frame.skiptranslate {
  display: none !important;
}
body {
  top: 0px !important;
}
@media print {
  #google_translate_element {
    display: none;
  }
}
</style>
<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { Link } from "@inertiajs/vue3";

const isDropdownOpen = ref(false);
const isMobileMenuOpen = ref(false);

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value;
  if (isDropdownOpen.value) {
    isMobileMenuOpen.value = false;
  }
};

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
  if (isMobileMenuOpen.value) {
    isDropdownOpen.value = false;
  }
};

const handleClickOutside = (event) => {
  const dropdownElement = document.getElementById('userDropdown');
  const avatarElement = document.getElementById('userAvatar');
  const mobileMenuButton = document.getElementById('mobileMenuButton');
  const mobileMenu = document.getElementById('mobileMenu');
  
  if (isDropdownOpen.value && 
      dropdownElement && 
      avatarElement && 
      !dropdownElement.contains(event.target) && 
      !avatarElement.contains(event.target)) {
    isDropdownOpen.value = false;
  }

  if (isMobileMenuOpen.value &&
      mobileMenu &&
      mobileMenuButton &&
      !mobileMenu.contains(event.target) &&
      !mobileMenuButton.contains(event.target)) {
    isMobileMenuOpen.value = false;
  }
};
</script>

<template>
  <header class="w-full z-30">
    <!-- Top Info Bar with Contact Details - Now Red (#8e2527) -->
    <div class="bg-[#8e2527] text-white py-2">
      <div class="max-w-7xl mx-auto px-4 lg:px-6 flex flex-wrap justify-between items-center">
        <div class="flex items-center space-x-6">
          <!-- Location -->
          <div class="flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            <span>Unit 1603, 16th Floor, The L. Plaza
              367 - 375 Queen's Road Central
              Sheung Wan</span>
          </div>
        </div>
        <div class="hidden sm:flex items-center space-x-3">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
          </svg>
          <span>+852 5322 1678</span>
          <!-- <a href="#" class="text-white hover:text-gray-200 transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16">
              <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
            </svg>
          </a>
          <a href="#" class="text-white hover:text-gray-200 transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16">
              <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
            </svg>
          </a>
          <a href="#" class="text-white hover:text-gray-200 transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-youtube" viewBox="0 0 16 16">
              <path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.007 2.007 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.007 2.007 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31.4 31.4 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.007 2.007 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A99.788 99.788 0 0 1 7.858 2h.193zM6.4 5.209v4.818l4.157-2.408L6.4 5.209z"/>
            </svg>
          </a> -->
        </div>
      </div>
    </div>
    <div id="google_translate_element" class="hidden"></div>
    <!-- Main Navigation - Now Dark Blue (#1d3858) -->
    <div class="bg-[#1d3858] text-white border-t border-white/10 shadow-md">
      <div class="max-w-7xl mx-auto flex items-center justify-between px-4 lg:px-6 py-4">
        <!-- Logo -->
        <div class="flex items-center">
          <Link 
            :href="route('home')" 
            class="transition duration-300"
          >
            <img src="/assets/images/site-logo.png" alt="SupremeMotors" class="h-12 sm:h-16" />
          </Link>
        </div>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center space-x-8">
          <Link 
            :href="route('home')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            Home
          </Link>
          <Link 
            :href="route('inventory.index')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            Inventory
          </Link>
          <Link 
            :href="route('about-us')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            About Us
          </Link>
          <Link 
            :href="route('bank-details')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            Bank Details
          </Link>
          <Link 
            :href="route('how-to-buy')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            How to buy
          </Link>
          <Link 
            :href="route('contact-us')" 
            class="text-white text-sm uppercase tracking-wider font-medium hover:text-gray-300 transition duration-300"
          >
            Contact
          </Link>

          <!-- Language Dropdown with Flags -->
          <div class="relative ml-4" >
            <button
              @click="isLangDropdownOpen = !isLangDropdownOpen"
              class="flex items-center gap-2 bg-white/10 text-white text-sm px-3 py-1 rounded hover:bg-white/20 transition duration-300"
            >
            <span>{{ selectedLanguage.flag }}</span>
            <span>{{ selectedLanguage.name }}</span>
            
              <svg class="w-4 h-4 text-white/70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown -->
            <div
              v-show="isLangDropdownOpen"
              class="absolute z-50 mt-2 w-40 bg-white text-gray-800 rounded shadow-lg overflow-hidden"
            >
              <ul>
                <li
                  v-for="lang in languages"
                  :key="lang.code"
                  @click="selectLanguage(lang)"
                  class="flex items-center gap-2 px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm"
                >
                  <span v-html="lang.flag"></span>
                  {{ lang.name }}
                </li>
              </ul>
            </div>
          </div>


          <!-- User is not logged in - Login Button -->
          <Link
            v-if="!$page.props.auth.user"
            :href="route('login')"
            class="ml-4 px-5 py-2 text-sm font-medium text-white bg-[#8e2527] hover:bg-[#622627] rounded transition duration-300"
          >
            Customer Login
          </Link>

          <!-- User is logged in - User Dropdown -->
          <div v-else class="relative ml-4">
            <button 
              id="userAvatar"
              @click.stop="toggleDropdown" 
              class="flex items-center gap-2 focus:outline-none bg-white/10 rounded-full pl-3 pr-2 py-1 hover:bg-white/20 transition duration-300"
            >
              <span class="text-white text-sm font-medium">
                {{ $page.props.auth.user.name }}
              </span>
              <div class="w-8 h-8 rounded-full overflow-hidden border border-white/30 flex-shrink-0 flex items-center justify-center bg-gray-100">
                <img 
                  v-if="$page.props.auth.user.profile_picture" 
                  :src="$page.props.auth.user.profile_picture" 
                  alt="User Profile" 
                  class="w-full h-full object-cover"
                />
                <span v-else class="text-[#1e4066] font-bold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </span>
              </div>
              <svg 
                xmlns="http://www.w3.org/2000/svg" 
                fill="none" 
                viewBox="0 0 24 24" 
                stroke-width="2" 
                stroke="currentColor" 
                class="w-4 h-4 text-white/70"
                :class="isDropdownOpen ? 'rotate-180' : ''"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div 
              id="userDropdown"
              v-show="isDropdownOpen" 
              class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-100"
            >
              <Link 
                :href="route('logout')" 
                method="post" 
                as="button" 
                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition duration-300"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2 text-gray-500">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Logout
              </Link>
            </div>
          </div>
        </nav>

        <!-- Mobile Menu Button -->
        <button 
          id="mobileMenuButton"
          @click.stop="toggleMobileMenu" 
          class="md:hidden text-white p-2 focus:outline-none"
        >
          <svg 
            xmlns="http://www.w3.org/2000/svg" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke-width="1.5" 
            stroke="currentColor" 
            class="w-6 h-6"
            :class="{ 'hidden': isMobileMenuOpen }"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
          <svg 
            xmlns="http://www.w3.org/2000/svg" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke-width="1.5" 
            stroke="currentColor" 
            class="w-6 h-6"
            :class="{ 'hidden': !isMobileMenuOpen }"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Menu -->
    <div 
      id="mobileMenu"
      v-show="isMobileMenuOpen" 
      class="md:hidden bg-[#1d3858]/95 backdrop-blur-sm shadow-xl"
    >
      <!-- Mobile Contact Info -->
      <div class="px-4 py-3 border-t border-white/10">
        <div class="flex items-center py-1 text-sm text-white/90">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2 text-[#8e2527]">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
          <span>Unit 1603, 16th Floor, The L. Plaza
            367 - 375 Queen's Road Central
            Sheung Wan</span>
        </div>
        <div class="flex items-center py-1 text-sm text-white/90">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2 text-[#8e2527]">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
          </svg>
          <span>+852 5322 1678</span>
        </div>
      </div>
      
      <!-- Mobile Navigation -->
      <nav class="px-4 pb-4 pt-1">
        <Link 
          :href="route('home')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          Home
        </Link>
        <Link 
          :href="route('inventory.index')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          Inventory
        </Link>
        <Link 
          :href="route('about-us')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          About Us
        </Link>
        <Link 
          :href="route('bank-details')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          Bank Details
        </Link>
        <Link 
          :href="route('how-to-buy')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          How to buy
        </Link>
        <Link 
          :href="route('contact-us')" 
          class="block py-2.5 text-white hover:text-gray-300 border-b border-white/10"
          @click="isMobileMenuOpen = false"
        >
          Contact
        </Link>
        
        <!-- Mobile User Login/Info -->
        <div class="py-3 border-t border-white/10 mt-2">
          <Link
            v-if="!$page.props.auth.user"
            :href="route('login')"
            class="block w-full text-center px-4 py-2 text-sm font-medium text-white bg-[#8e2527] hover:bg-[#622627] rounded transition duration-300"
            @click="isMobileMenuOpen = false"
          >
            Customer Login
          </Link>

          <div v-else>
            <div class="flex items-center py-2">
              <div class="w-8 h-8 rounded-full overflow-hidden border border-white/30 flex-shrink-0 flex items-center justify-center bg-gray-100">
                <img 
                  v-if="$page.props.auth.user.profile_picture" 
                  :src="$page.props.auth.user.profile_picture" 
                  alt="User Profile" 
                  class="w-full h-full object-cover"
                />
                <span v-else class="text-[#1e4066] font-bold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </span>
              </div>
              <span class="ml-2 text-white font-medium">{{ $page.props.auth.user.name }}</span>
            </div>

            <Link 
              :href="route('logout')" 
              method="post" 
              as="button" 
              class="flex items-center gap-2 w-full text-left px-2 py-2 text-sm text-white hover:bg-white/10 rounded transition duration-300"
              @click="isMobileMenuOpen = false"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
              </svg>
              Logout
            </Link>
          </div>
        </div>
      </nav>
    </div>
  </header>
</template>
<script>
  export default {
    data() {
      return {
        selectedLanguage:  { code: 'en', name: 'English', flag: '🇺🇸' },
      isLangDropdownOpen: false,
      languages: [
        { code: 'en', name: 'English', flag: '🇺🇸' },
        { code: 'zh-CN', name: '中文', flag: '🇨🇳' },
        { code: 'ja', name: '日本語', flag: '🇯🇵' },
        { code: 'th', name: 'ไทย', flag: '🇹🇭' },
        { code: 'pt', name: 'Português', flag: '🇧🇷' },     // or 🇵🇹 depending on target region
        { code: 'fr', name: 'Français', flag: '🇫🇷' },
        { code: 'sw', name: 'Kiswahili', flag: '🇰🇪' },     // or 🇹🇿
        { code: 'es', name: 'Español', flag: '🇪🇸' },       // or 🇲🇽 depending on audience
        { code: 'ru', name: 'Русский', flag: '🇷🇺' },
        { code: 'ka', name: 'ქართული', flag: '🇬🇪' },
        { code: 'ar', name: 'العربية', flag: '🇸🇦' }
      ],

      };
    },
    methods: {
      selectLanguage(lang) {
        this.selectedLanguage = lang;
        this.isLangDropdownOpen = false;
        this.changeLanguage();
      },
      getLangName(langObj) {
        return langObj.name;
      },
      getLangFlag(langObj) {
        return langObj.flag;
      },
      changeLanguage() {
        const googleTranslateElement = document.getElementById('google_translate_element');
        if (googleTranslateElement) {
          const select = googleTranslateElement.querySelector('.goog-te-combo');
          if (select) {
            select.value = this.selectedLanguage.code;
            select.dispatchEvent(new Event('change'));
          }
        }
      }
    },

    mounted() {
      const googleTranslateElementInit = () => {
        new window.google.translate.TranslateElement(
          {
            pageLanguage: "en",
            autoDisplay: false
          },
          "google_translate_element"
        );
      };
      // Load Google Translate script
      var addScript = document.createElement("script");
    addScript.setAttribute(
      "src",
      "//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"
    );
    document.body.appendChild(addScript);
    window.googleTranslateElementInit = googleTranslateElementInit;
    },
  };
  </script>