<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import HeroV2 from '@/components/Front/HeroV2.vue';
import HowItWorks from '@/components/Front/HowItWorks.vue';
import BrandsExplorer from '@/components/Front/BrandsExplorer.vue';
import RecommendedForYou from '@/components/Front/RecommendedForYou.vue';
import WhyUs from '@/components/Front/WhyUs.vue';
import StayInLoop from '@/components/Front/StayInLoop.vue';
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
      <HeroV2
        :makes="makes"
        :buyer-images="[...(featured_products_china || []), ...(featured_products_japan || [])]
          .filter(p => p.front_image)
          .slice(0, 4)
          .map(p => p.front_image.includes('product_images') ? '/storage/' + p.front_image : p.front_image)"
      />
        <section class="py-16 bg-gradient-to-b from-gray-100 to-white">
          <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-4xl font-bold text-[#1e4066] mb-4 text-center">Explore Our Categories</h2>
            <p class="text-gray-600 text-lg text-center mb-12 max-w-3xl mx-auto">Find exactly what you need from our
              extensive collection of construction equipment and parts</p>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              <div 
                v-for="category in categories" 
                :key="category.id"
                class="bg-white rounded-xl shadow-md overflow-hidden group transition-all duration-300 hover:shadow-xl transform hover:-translate-y-2 hover:rotate-1"
              >
                <Link :href="route('inventory.index', { category: category.id })" class="block h-full flex flex-col">
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
        <BrandsExplorer :makes="makes" />


        <RecommendedForYou :china="featured_products_china" :japan="featured_products_japan" />

        <!-- CTA Section -->
        <HowItWorks />
        <WhyUs />
        <StayInLoop />
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
