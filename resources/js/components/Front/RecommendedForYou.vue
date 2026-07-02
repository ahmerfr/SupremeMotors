<script setup>
import { Link } from '@inertiajs/vue3';
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

const imageUrl = (p) =>
    p.front_image && p.front_image.includes('product_images') ? `/storage/${p.front_image}` : p.front_image;

const brand = (p) => p.make?.cat_title || p.category?.cat_title || 'Vehicle';
const km = (p) => (p.mileage_km ? `${Number(p.mileage_km).toLocaleString()} KM` : '—');
const showPrice = (p) => p.price > 0 && ['tcv', 'suprememotors', 'electricvehicles'].some((s) => (p.website || '').includes(s));
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1180px; margin: 0 auto">
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
                <Link
                    v-for="p in products"
                    :key="p.id"
                    :href="`/inventory/product-detail/${p.id}`"
                    class="scpd"
                    style="display: block; background: #fff; border: 1px solid #eef1f6; border-radius: 18px; overflow: hidden; transition: 0.2s; text-decoration: none; box-shadow: rgba(11, 30, 59, 0.04) 0 4px 14px"
                >
                    <div style="position: relative; height: 220px; background: #f4f6f9; overflow: hidden">
                        <img :src="imageUrl(p)" :alt="p.title" loading="lazy" style="width: 100%; height: 100%; object-fit: cover" />
                        <span style="position: absolute; top: 12px; right: 12px; display: inline-flex; align-items: center; gap: 5px; background: #e01f26; color: #fff; font-size: 10.5px; font-weight: 800; letter-spacing: 0.03em; padding: 5px 11px; border-radius: 100px">
                            ⚡ Featured
                        </span>
                    </div>
                    <div style="padding: 20px 20px 22px">
                        <span style="display: inline-block; background: linear-gradient(150deg, #e5262d, #c8151c); color: #fff; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 11px; border-radius: 6px">{{ brand(p) }}</span>
                        <div style="font-family: Archivo; font-weight: 700; font-size: 18px; color: #0b1e3b; margin-top: 12px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden">
                            {{ p.title }}
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px; font-size: 13.5px; font-weight: 600; color: #8494ab; margin-top: 7px">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {{ p.country }}
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; border: 1px solid #eef1f6; border-radius: 12px; margin-top: 16px; overflow: hidden">
                            <div style="padding: 11px 12px; border-right: 1px solid #eef1f6">
                                <div style="display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; color: #9aa8bd; letter-spacing: 0.03em">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v15"/><path d="M2 22h12"/><path d="M13 11h2a2 2 0 0 1 2 2v3a1.5 1.5 0 0 0 3 0V9l-3-3"/><path d="M5 9h6"/></svg>
                                    FUEL
                                </div>
                                <div style="font-size: 13px; font-weight: 700; color: #33445e; margin-top: 4px">{{ p.fuel || '—' }}</div>
                            </div>
                            <div style="padding: 11px 12px; border-right: 1px solid #eef1f6">
                                <div style="display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; color: #9aa8bd; letter-spacing: 0.03em">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="2.6"/><circle cx="18" cy="6" r="2.6"/><circle cx="6" cy="18" r="2.6"/><path d="M6 8.6v6.8"/><path d="M18 8.6V12a2 2 0 0 1-2 2H8.6"/></svg>
                                    GEAR
                                </div>
                                <div style="font-size: 13px; font-weight: 700; color: #33445e; margin-top: 4px">{{ p.transmission || '—' }}</div>
                            </div>
                            <div style="padding: 11px 12px">
                                <div style="display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 800; color: #9aa8bd; letter-spacing: 0.03em">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20a8 8 0 1 0-8-8"/><path d="M12 12l4-4"/><path d="M4 12h2"/><path d="M12 4v2"/></svg>
                                    TRAVELLED
                                </div>
                                <div style="font-size: 13px; font-weight: 700; color: #33445e; margin-top: 4px">{{ km(p) }}</div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 13px; border-top: 1px solid #f1f3f7">
                            <span v-if="showPrice(p)" style="font-family: Archivo; font-weight: 800; font-size: 21px; color: #e01f26">${{ Number(p.price).toLocaleString() }}</span>
                            <span v-else style="font-family: Archivo; font-weight: 800; font-size: 15px; color: #0b1e3b">Enquire</span>
                            <span style="font-size: 12.5px; font-weight: 700; color: #8494ab">View details →</span>
                        </div>
                    </div>
                </Link>
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
