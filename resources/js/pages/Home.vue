<script setup>
import { Head } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import HeroV2 from '@/components/Front/HeroV2.vue';
import ExploreCategories from '@/components/Front/ExploreCategories.vue';
import BrandsExplorer from '@/components/Front/BrandsExplorer.vue';
import ShopByBodyType from '@/components/Front/ShopByBodyType.vue';
import RecommendedForYou from '@/components/Front/RecommendedForYou.vue';
import Testimonials from '@/components/Front/Testimonials.vue';
import CantFindCta from '@/components/Front/CantFindCta.vue';

defineProps({
    categories: Object,
    auth: Object,
    makes: Object,
    body_types: Object,
    featured_products_china: Object,
    featured_products_japan: Object,
    featured_products_europe: Object,
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
                    .slice(0, 10)
                    .map(p => p.front_image.includes('product_images') ? '/storage/' + p.front_image : p.front_image)"
            />
            <ExploreCategories :categories="categories" />
            <BrandsExplorer :makes="makes" />
            <ShopByBodyType :body-types="body_types || []" />
            <RecommendedForYou :china="featured_products_china" :japan="featured_products_japan" :europe="featured_products_europe" />
            <Testimonials />
            <CantFindCta />
        </FrontLayout>
    </div>
</template>
