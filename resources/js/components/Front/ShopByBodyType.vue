<script setup>
import ProductCard from '@/components/Front/ProductCard.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
    bodyTypes: { type: Array, default: () => [] },
});

const icons = {
    'Van / Minivan': '🚐',
    'SUV': '🚙',
    'Mini Vehicle': '🚗',
    'Hatchback': '🚘',
    'Sedan': '🚗',
    'Wagon': '🚐',
    'Truck': '🛻',
    'Coupe': '🏎️',
    'Convertible': '🏎️',
    'Bus': '🚌',
};

const active = ref(props.bodyTypes[0]?.body_style ?? null);
const products = ref([]);
const loading = ref(false);

const load = async (style) => {
    active.value = style;
    loading.value = true;
    try {
        const { data } = await axios.get('/inventory/listing', { params: { type: 'search', body_style: style } });
        products.value = (data.data || []).slice(0, 6);
    } catch {
        products.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (active.value) load(active.value);
});
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <!-- Header -->
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>SHOP BY BODY TYPE
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                    The right shape for the job
                </h2>
                <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 14px; max-width: 520px">
                    From city sedans to working trucks, pick a body type and browse what is on the yard right now.
                </p>
            </div>

            <!-- Body type slider -->
            <div class="sm-typesrow" style="display: flex; gap: 14px; margin-top: 36px; overflow-x: auto; padding-bottom: 10px; scroll-snap-type: x mandatory">
                <button
                    v-for="t in props.bodyTypes"
                    :key="t.body_style"
                    type="button"
                    :style="{
                        flex: '0 0 auto', scrollSnapAlign: 'start', cursor: 'pointer', textAlign: 'center',
                        minWidth: '132px', padding: '18px 16px 15px', borderRadius: '16px', transition: '0.18s',
                        background: active === t.body_style ? 'linear-gradient(150deg, #12284a, #0b1e3b)' : '#f8fafc',
                        border: active === t.body_style ? '1px solid #0b1e3b' : '1px solid #eef1f6',
                        boxShadow: active === t.body_style ? 'rgba(11,30,59,0.25) 0 10px 24px' : 'none',
                    }"
                    @click="load(t.body_style)"
                >
                    <div style="font-size: 30px; line-height: 1" :style="{ filter: active === t.body_style ? 'none' : 'grayscale(0.6)' }">{{ icons[t.body_style] || '🚗' }}</div>
                    <div :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '14px', marginTop: '10px', color: active === t.body_style ? '#fff' : '#0b1e3b' }">{{ t.body_style }}</div>
                    <div :style="{ fontSize: '11.5px', fontWeight: 700, marginTop: '3px', color: active === t.body_style ? '#a9b7cc' : '#8494ab' }">{{ Number(t.count).toLocaleString() }}</div>
                </button>
            </div>

            <!-- Listing -->
            <div v-if="loading" class="sm-reccards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 30px">
                <div v-for="i in 6" :key="i" style="height: 420px; border-radius: 18px; background: linear-gradient(100deg, #f4f6f9 40%, #eef1f6 50%, #f4f6f9 60%); border: 1px solid #eef1f6"></div>
            </div>
            <div v-else class="sm-reccards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 30px">
                <ProductCard v-for="p in products" :key="p.id" :product="p" />
            </div>

            <div style="display: flex; justify-content: center; margin-top: 36px">
                <Link
                    :href="`/inventory?type=search&body_style=${encodeURIComponent(active || '')}`"
                    class="scp2"
                    style="display: inline-flex; align-items: center; gap: 9px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #12284a, #0b1e3b); padding: 15px 28px; border-radius: 13px; box-shadow: rgba(11, 30, 59, 0.25) 0 12px 28px; transition: transform 0.18s; text-decoration: none"
                >View all {{ active }} stock →</Link>
            </div>
        </div>
    </section>
</template>
