<script setup>
import { Link } from '@inertiajs/vue3';
import ProductCard from '@/components/Front/ProductCard.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    china: { type: Array, default: () => [] },
    japan: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'japan', label: 'Japan' },
    { key: 'china', label: 'China' },
];
const active = ref('japan');

const products = computed(() => (active.value === 'china' ? props.china : props.japan).slice(0, 6));

</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <!-- Header: theme-aligned, left -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                        <span style="width: 22px; height: 2px; background: #e01f26"></span>FRESH ARRIVALS
                    </div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                        Recommended for you
                    </h2>
                    <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 14px; max-width: 520px">
                        Hand-picked cars, trucks and machinery from our latest stock in Japan and China, ready for the road or the job site.
                    </p>
                </div>

                <!-- Country tabs -->
                <div style="display: flex; gap: 10px; padding-bottom: 6px">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        :style="{
                            fontFamily: 'Manrope', fontSize: '14px', fontWeight: 700, cursor: 'pointer',
                            padding: '11px 22px', borderRadius: '100px', transition: '0.18s',
                            background: active === t.key ? 'linear-gradient(150deg, #12284a, #0b1e3b)' : '#fff',
                            color: active === t.key ? '#fff' : '#33445e',
                            border: active === t.key ? '1px solid #0b1e3b' : '1px solid #e6eaf0',
                            boxShadow: active === t.key ? 'rgba(11,30,59,0.25) 0 8px 20px' : 'none',
                        }"
                        @click="active = t.key"
                    >{{ t.label }}</button>
                </div>
            </div>

            <!-- Cards -->
            <div class="sm-reccards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 44px">
                <ProductCard v-for="p in products" :key="p.id" :product="p" />
            </div>

            <div style="display: flex; justify-content: center; margin-top: 36px">
                <Link
                    :href="`/inventory?country=${active === 'china' ? 'China' : 'Japan'}`"
                    class="scp2"
                    style="display: inline-flex; align-items: center; gap: 9px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #12284a, #0b1e3b); padding: 15px 28px; border-radius: 13px; box-shadow: rgba(11, 30, 59, 0.25) 0 12px 28px; transition: transform 0.18s; text-decoration: none"
                >View all {{ active === 'china' ? 'China' : 'Japan' }} stock →</Link>
            </div>
        </div>
    </section>
</template>
