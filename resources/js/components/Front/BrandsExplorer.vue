<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    makes: { type: Array, default: () => [] },
});

const sorted = computed(() =>
    [...props.makes].filter((m) => m.products_count > 0).sort((a, b) => b.products_count - a.products_count)
);
const popular = computed(() => sorted.value.slice(0, 4));
const others = computed(() => {
    const rest = sorted.value.slice(4, 24).sort((a, b) => a.cat_title.localeCompare(b.cat_title));
    const mid = Math.ceil(rest.length / 2);
    return [rest.slice(0, mid), rest.slice(mid)];
});

const fmt = (n) => Number(n).toLocaleString();
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <div class="sm-whygrid" style="display: grid; grid-template-columns: 0.9fr 1.4fr; gap: 40px 64px; align-items: start">
                <!-- Left: heading + popular brand cards -->
                <div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 34px; letter-spacing: -0.02em; color: #0b1e3b; line-height: 1.15">
                        Explore the Most<br />Popular Brands
                    </h2>

                    <div style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin: 28px 0 12px">MOST POPULAR BRANDS:</div>
                    <div style="display: flex; flex-direction: column; gap: 12px">
                        <Link
                            v-for="(m, i) in popular"
                            :key="m.id"
                            :href="`/inventory?make=${m.id}`"
                            class="scpd"
                            :style="{
                                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px',
                                borderRadius: '14px', padding: '16px 20px', transition: '0.2s', textDecoration: 'none',
                                background: i === 2 ? 'linear-gradient(150deg, #12284a, #0b1e3b)' : '#fff',
                                border: i === 2 ? '1px solid #0b1e3b' : '1px solid #eef1f6',
                                boxShadow: i === 2 ? 'rgba(11,30,59,0.3) 0 12px 28px' : 'rgba(11,30,59,0.04) 0 4px 14px',
                            }"
                        >
                            <div style="line-height: 1.3">
                                <div :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '16px', color: i === 2 ? '#fff' : '#0b1e3b' }">{{ m.cat_title }}</div>
                                <div :style="{ fontSize: '12px', fontWeight: 600, color: i === 2 ? '#a9b7cc' : '#8494ab', marginTop: '3px' }">{{ fmt(m.products_count) }} vehicles</div>
                            </div>
                            <div :style="{ width: '76px', height: '48px', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: '10px', background: i === 2 ? 'rgba(255,255,255,0.95)' : '#f8fafc', padding: '6px' }">
                                <img v-if="m.image" :src="`/storage/${m.image}`" :alt="m.cat_title" style="max-width: 100%; max-height: 100%; object-fit: contain; opacity: 0.85" />
                                <span v-else style="font-family: Archivo; font-weight: 800; font-size: 14px; color: #8494ab">{{ m.cat_title.slice(0, 2).toUpperCase() }}</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Right: blurb + two-column brand index -->
                <div>
                    <p style="font-size: 14.5px; line-height: 1.7; color: #8494ab; font-weight: 600; max-width: 520px">
                        Browse our wide range of trucks, cars and heavy machinery and find the perfect vehicle to match your work and needs.
                        <br />We offer various brands across Japan and China.
                    </p>

                    <div style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin: 28px 0 14px">OTHER BRANDS:</div>
                    <div class="sm-whygrid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 56px">
                        <div v-for="(col, ci) in others" :key="ci">
                            <Link
                                v-for="m in col"
                                :key="m.id"
                                :href="`/inventory?make=${m.id}`"
                                class="scp0"
                                style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 10px; border-radius: 9px; text-decoration: none; transition: 0.16s"
                            >
                                <span style="font-family: Archivo; font-weight: 700; font-size: 14px; color: #0b1e3b">{{ m.cat_title }}</span>
                                <span style="font-size: 13px; font-weight: 700; color: #8494ab; font-variant-numeric: tabular-nums">{{ fmt(m.products_count) }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
