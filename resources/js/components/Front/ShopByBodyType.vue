<script setup>
import ProductCard from '@/components/Front/ProductCard.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref } from 'vue';
import BodyTypeIcon from '@/components/Front/BodyTypeIcon.vue';

const props = defineProps({
    bodyTypes: { type: Array, default: () => [] },
});

const active = ref(props.bodyTypes[0]?.body_style ?? null);
const products = ref([]);
const loading = ref(false);

// Drop cards whose image 404s and backfill from the extra candidates.
const failed = ref(new Set());
const markFailed = (id) => {
    failed.value = new Set(failed.value).add(id);
};
const visible = computed(() => products.value.filter((p) => !failed.value.has(p.id)).slice(0, 6));

const rowEl = ref(null);
const canPrev = ref(false);
const canNext = ref(false);

const updateArrows = () => {
    const el = rowEl.value;
    if (!el) return;
    canPrev.value = el.scrollLeft > 4;
    canNext.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 4;
};

const slide = (dir) => {
    const el = rowEl.value;
    if (!el) return;
    el.scrollBy({ left: dir * el.clientWidth * 0.75, behavior: 'smooth' });
};

const load = async (style) => {
    active.value = style;
    loading.value = true;
    failed.value = new Set();
    try {
        const { data } = await axios.get('/home/body-type-products', { params: { style } });
        products.value = data || [];
    } catch {
        products.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (active.value) load(active.value);
    nextTick(updateArrows);
    window.addEventListener('resize', updateArrows, { passive: true });
});
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <!-- Header -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px">
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
                <div style="display: flex; gap: 10px; flex: 0 0 auto; padding-bottom: 4px">
                    <button type="button" class="sm-carbtn" :disabled="!canPrev" aria-label="Previous body types" @click="slide(-1)">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                    </button>
                    <button type="button" class="sm-carbtn" :disabled="!canNext" aria-label="Next body types" @click="slide(1)">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                    </button>
                </div>
            </div>

            <!-- Body type carousel -->
            <div ref="rowEl" class="sm-typesrow" style="display: flex; gap: 14px; margin-top: 36px; overflow-x: auto; scroll-snap-type: x mandatory" @scroll.passive="updateArrows">
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
                    <div :style="{ color: active === t.body_style ? '#ff6b70' : '#8494ab', transition: '0.18s' }"><BodyTypeIcon :type="t.body_style" /></div>
                    <div :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '14px', marginTop: '10px', color: active === t.body_style ? '#fff' : '#0b1e3b' }">{{ t.body_style }}</div>
                    <div :style="{ fontSize: '11.5px', fontWeight: 700, marginTop: '3px', color: active === t.body_style ? '#a9b7cc' : '#8494ab' }">{{ Number(t.count).toLocaleString() }}</div>
                </button>
            </div>

            <!-- Listing -->
            <div v-if="loading" class="sm-reccards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 30px">
                <div v-for="i in 6" :key="i" style="height: 420px; border-radius: 18px; background: linear-gradient(100deg, #f4f6f9 40%, #eef1f6 50%, #f4f6f9 60%); border: 1px solid #eef1f6"></div>
            </div>
            <div v-else class="sm-reccards" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 30px">
                <ProductCard v-for="p in visible" :key="p.id" :product="p" @img-error="markFailed(p.id)" />
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
