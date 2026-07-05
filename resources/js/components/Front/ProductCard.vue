<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const emit = defineEmits(['img-error']);

const p = props.product;
const brand = p.make?.cat_title || p.category?.cat_title || 'Vehicle';
const km = p.mileage_km ? `${Number(p.mileage_km).toLocaleString()} KM` : '—';
const showPrice = p.price > 0 && ['tcv', 'suprememotors', 'electricvehicles', 'autotraderza'].some((s) => (p.website || '').includes(s));

/* mini slider: front + up to 3 gallery shots, dot-switchable */
const resolve = (u) => (typeof u === 'string' && u.startsWith('product_images') ? `/storage/${u}` : u);
const failed = ref(new Set());
const images = computed(() => {
    const list = [];
    if (p.front_image) list.push(resolve(p.front_image));
    for (const u of Array.isArray(p.other_images) ? p.other_images : []) {
        if (typeof u === 'string' && list.length < 4) list.push(resolve(u));
    }
    return [...new Set(list)].filter((u) => !failed.value.has(u));
});
const idx = ref(0);
const current = computed(() => images.value[Math.min(idx.value, images.value.length - 1)] ?? null);
const onImgError = () => {
    if (!current.value) return;
    failed.value = new Set(failed.value).add(current.value);
    if (!images.value.length) emit('img-error');
};
const setIdx = (i) => (idx.value = i);
</script>

<template>
    <Link
        :href="`/inventory/product-detail/${p.id}`"
        class="scpd"
        style="display: block; background: #fff; border: 1px solid #eef1f6; border-radius: 18px; overflow: hidden; transition: 0.2s; text-decoration: none; box-shadow: rgba(11, 30, 59, 0.04) 0 4px 14px"
    >
        <div style="position: relative; height: 320px; background: #f4f6f9; overflow: hidden">
            <img v-if="current" :src="current" :alt="p.title" loading="lazy" style="width: 100%; height: 100%; object-fit: cover" @error="onImgError" />
            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(150deg, #eef1f6, #f8fafc)">
                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#c3cdda" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16.5V14a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2.5"/><path d="M2 16.5h20"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/><path d="M14 12V8a2 2 0 0 1 2-2h2.6L22 10v6.5"/></svg>
            </div>

            <!-- dot picture changer -->
            <div v-if="images.length > 1" style="position: absolute; bottom: 12px; left: 0; right: 0; display: flex; justify-content: center; gap: 7px">
                <button
                    v-for="(img, i) in images"
                    :key="img"
                    type="button"
                    :aria-label="`Photo ${i + 1}`"
                    :style="{
                        width: i === idx ? '22px' : '8px', height: '8px', borderRadius: '100px', border: 'none', cursor: 'pointer', padding: 0,
                        background: i === idx ? '#fff' : 'rgba(255, 255, 255, 0.55)',
                        boxShadow: 'rgba(11, 30, 59, 0.35) 0 1px 5px',
                        transition: 'all 0.25s cubic-bezier(0.32, 0.72, 0, 1)',
                    }"
                    @click.prevent.stop="setIdx(i)"
                    @mouseenter="setIdx(i)"
                />
            </div>
        </div>
        <div style="padding: 20px 20px 22px">
            <span style="display: inline-block; background: linear-gradient(150deg, #e5262d, #c8151c); color: #fff; font-size: 10.5px; font-weight: 800; letter-spacing: 0.04em; padding: 4px 11px; border-radius: 6px">{{ brand }}</span>
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
                        KILOMETERS
                    </div>
                    <div style="font-size: 13px; font-weight: 700; color: #33445e; margin-top: 4px">{{ km }}</div>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 13px; border-top: 1px solid #f1f3f7">
                <span v-if="showPrice" style="font-family: Archivo; font-weight: 800; font-size: 21px; color: #e01f26">${{ Number(p.price).toLocaleString() }}</span>
                <span v-else style="font-family: Archivo; font-weight: 800; font-size: 15px; color: #0b1e3b">Enquire</span>
                <span style="font-size: 12.5px; font-weight: 700; color: #8494ab">View details →</span>
            </div>
        </div>
    </Link>
</template>
