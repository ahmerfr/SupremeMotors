<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import ProductCard from '@/components/Front/ProductCard.vue';
import SectionDivider from '@/components/Front/SectionDivider.vue';

const props = defineProps({
    product_detail: Object,
    similar_products: { type: Array, default: () => [] },
});

const p = props.product_detail;

/* ---------------- gallery ---------------- */

const resolve = (u) => (typeof u === 'string' && u.startsWith('http') ? u : `/storage/${u}`);
const gallery = computed(() => {
    const list = [];
    if (p.front_image) list.push(resolve(p.front_image));
    for (const u of p.other_images ?? []) {
        if (typeof u === 'string') list.push(resolve(u));
    }
    return [...new Set(list)];
});

const active = ref(0);
const failed = ref(new Set());
const markFailed = (url) => {
    failed.value = new Set(failed.value).add(url);
};
const visibleGallery = computed(() => gallery.value.filter((u) => !failed.value.has(u)));
const mainImage = computed(() => visibleGallery.value[Math.min(active.value, visibleGallery.value.length - 1)] ?? null);

const isZoomed = ref(false);
const mousePos = ref({ x: 50, y: 50 });
const onMove = (e) => {
    if (!isZoomed.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    mousePos.value = {
        x: ((e.clientX - rect.left) / rect.width) * 100,
        y: ((e.clientY - rect.top) / rect.height) * 100,
    };
};

const slide = (dir) => {
    const n = visibleGallery.value.length;
    if (!n) return;
    isZoomed.value = false;
    active.value = (Math.min(active.value, n - 1) + dir + n) % n;
};

onMounted(() => {
    for (const url of gallery.value.slice(0, 8)) {
        const img = new Image();
        img.src = url;
    }
});

/* ---------------- summary data ---------------- */

const brand = computed(() => p.make?.cat_title || p.category?.cat_title || 'Vehicle');
const showPrice = computed(() => p.price > 0 && ['tcv', 'suprememotors', 'electricvehicles', 'autotraderza'].some((s) => (p.website || '').includes(s)));

const fmtNum = (v) => Number(v).toLocaleString();
const keySpecs = computed(() => [
    { label: 'YEAR', value: p.year || '—' },
    { label: 'MILEAGE', value: p.mileage_km ? `${fmtNum(p.mileage_km)} km` : '—' },
    { label: 'FUEL', value: p.fuel || '—' },
    { label: 'GEARBOX', value: p.transmission || '—' },
]);

const whatsappHref = computed(() => {
    const url = typeof window !== 'undefined' ? window.location.href : '';
    return `https://wa.me/447516916622?text=${encodeURIComponent(`Hello, I'm interested in ${p.title} (stock ${p.stock_code ?? p.id}). Product link: ${url}`)}`;
});

/* ---------------- full specification ---------------- */

const specRows = computed(() => {
    const rows = [
        ['Make', p.make?.cat_title],
        ['Model', p.model],
        ['Model code', p.model_code],
        ['Year', p.year],
        ['Body type', p.body_style],
        ['Mileage', p.mileage_km ? `${fmtNum(p.mileage_km)} km` : null],
        ['Fuel', p.fuel],
        ['Transmission', p.transmission],
        ['Engine', p.engine_cc ? `${fmtNum(p.engine_cc)} cc` : null],
        ['Power', p.power_hp ? `${fmtNum(p.power_hp)} hp` : null],
        ['Drive type', p.drive_type],
        ['Steering', p.steering],
        ['Colour', p.color],
        ['Seats', p.seats],
        ['Doors', p.doors],
        ['Condition', p.condition],
        ['Emission standard', p.emission_standard],
        ['Load capacity', p.load_capacity_kg ? `${fmtNum(p.load_capacity_kg)} kg` : null],
        ['Running hours', p.running_hours ? `${fmtNum(p.running_hours)} h` : null],
        ['Axles', p.axles],
        ['Origin', p.country],
        ['Stock code', p.stock_code],
    ];
    return rows.filter(([, v]) => v !== null && v !== undefined && v !== '' && v !== 0);
});

/* extra manufacturer specs (torque, top speed, fuel economy, CO2, equipment...)
   captured in the specifications JSON, minus anything already in the ledger */
const shownSpecKeys = new Set(['make', 'model', 'variant', 'year', 'body type', 'mileage', 'fuel type', 'transmission', 'engine capacity (litre)', 'power maximum (detail)', 'power maximum', 'driven wheels', 'seats (quantity)', 'no of doors', 'manufacturers colour']);
const extraSpecs = computed(() => {
    const raw = p.specifications;
    if (!raw || typeof raw !== 'object') return [];
    return Object.entries(raw).filter(([k]) => !shownSpecKeys.has(String(k).toLowerCase()));
});

/* ---------------- dark-card accordions ---------------- */

const hasDescription = computed(() => {
    const text = (p.product_details || '').replace(/<[^>]*>/g, '').trim();
    return text.length > 10;
});

const sections = computed(() => [
    ...(hasDescription.value ? [{ title: 'Product description', html: p.product_details }] : []),
    {
        title: 'Payment',
        body: 'Payment is by bank transfer to our registered Hong Kong business account — the exact details are on our Bank Details page and on every invoice. Details never change outside a re-issued invoice.',
    },
    {
        title: 'Documents you receive',
        body: 'Commercial invoice, bill of lading, export certificate or deregistration document, and the inspection sheet where applicable — plus certified translations where your customs authority requires them.',
    },
]);
const openSection = ref(0);
const toggleSection = (i) => (openSection.value = openSection.value === i ? -1 : i);
</script>

<template>
    <Head :title="p.title" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <!-- Breadcrumb -->
            <section class="sm-body" style="padding: 22px 24px 0">
                <div style="max-width: 1280px; margin: 0 auto; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #8494ab; flex-wrap: wrap">
                    <Link href="/" style="color: #8494ab; text-decoration: none">Home</Link>
                    <span>/</span>
                    <Link href="/inventory" style="color: #8494ab; text-decoration: none">Inventory</Link>
                    <template v-if="p.category">
                        <span>/</span>
                        <Link :href="`/inventory?category=${p.category_id}`" style="color: #8494ab; text-decoration: none">{{ p.category.cat_title }}</Link>
                    </template>
                    <span>/</span>
                    <span style="color: #0b1e3b; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 340px">{{ p.title }}</span>
                </div>
            </section>

            <!-- Gallery + dark summary card -->
            <section class="sm-body" style="padding: 22px 24px 0">
                <div class="sm-pdgrid" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 28px; align-items: start">
                    <!-- Gallery (min-width 0: the thumb strip must scroll, not stretch the column) -->
                    <div style="min-width: 0">
                        <div
                            style="position: relative; border-radius: 22px; overflow: hidden; background: #f4f6f9; border: 1px solid #eef1f6; height: 480px; cursor: zoom-in"
                            @click="isZoomed = !isZoomed"
                            @mousemove="onMove"
                            @mouseleave="isZoomed = false"
                        >
                            <img
                                v-if="mainImage"
                                :src="mainImage"
                                :alt="p.title"
                                :style="{
                                    width: '100%', height: '100%', objectFit: isZoomed ? 'cover' : 'contain', transition: 'transform 0.15s',
                                    transform: isZoomed ? 'scale(2)' : 'none',
                                    transformOrigin: `${mousePos.x}% ${mousePos.y}%`,
                                }"
                                @error="markFailed(mainImage)"
                            />
                            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#c3cdda" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16.5V14a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2.5" /><path d="M2 16.5h20" /><circle cx="6.5" cy="18.5" r="1.8" /><circle cx="17.5" cy="18.5" r="1.8" /><path d="M14 12V8a2 2 0 0 1 2-2h2.6L22 10v6.5" /></svg>
                            </div>
                            <div v-if="visibleGallery.length" style="position: absolute; bottom: 14px; right: 16px; background: rgba(11, 30, 59, 0.75); color: #fff; font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 100px">
                                {{ Math.min(active + 1, visibleGallery.length) }} / {{ visibleGallery.length }}
                            </div>

                            <!-- Slider arrows -->
                            <template v-if="visibleGallery.length > 1">
                                <button type="button" class="sm-carbtn sm-pdarrow" aria-label="Previous photo" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%)" @click.stop="slide(-1)">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                </button>
                                <button type="button" class="sm-carbtn sm-pdarrow" aria-label="Next photo" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%)" @click.stop="slide(1)">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                                </button>
                            </template>
                        </div>

                        <!-- Thumbnail grid: every photo, big tiles -->
                        <div v-if="visibleGallery.length > 1" class="sm-pdthumbs" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px">
                            <button
                                v-for="(img, i) in visibleGallery"
                                :key="img"
                                type="button"
                                :style="{
                                    aspectRatio: '4 / 3', borderRadius: '14px', overflow: 'hidden', cursor: 'pointer', padding: 0,
                                    border: i === active ? '2.5px solid #e01f26' : '1px solid #eef1f6',
                                    opacity: i === active ? 1 : 0.85,
                                    transition: '0.15s',
                                }"
                                @click="active = i; isZoomed = false"
                            >
                                <img :src="img" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover" @error="markFailed(img)" />
                            </button>
                        </div>
                    </div>

                    <!-- Dark summary card -->
                    <div style="position: relative; overflow: hidden; min-width: 0; border-radius: 24px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 30px 32px">
                        <div style="position: absolute; top: -110px; right: -70px; width: 300px; height: 300px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.15), transparent 70%)"></div>

                        <div style="position: relative">
                            <span style="display: inline-block; background: linear-gradient(150deg, #e5262d, #c8151c); color: #fff; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; padding: 5px 13px; border-radius: 6px">{{ brand }}</span>

                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 25px; letter-spacing: -0.015em; color: #fff; margin-top: 14px; line-height: 1.25">
                                {{ p.title }}
                            </h1>

                            <div style="display: flex; align-items: center; gap: 14px; margin-top: 10px; flex-wrap: wrap">
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #a9b7cc">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#a9b7cc" stroke-width="2.4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                                    {{ p.country }}
                                </span>
                                <span v-if="p.stock_code" style="font-size: 13px; font-weight: 600; color: #7487a3">Stock {{ p.stock_code }}</span>
                            </div>

                            <div style="height: 1px; background: rgba(255, 255, 255, 0.1); margin: 18px 0"></div>

                            <div v-if="showPrice">
                                <div style="font-family: Archivo; font-weight: 800; font-size: 38px; letter-spacing: -0.02em; color: #fff">
                                    ${{ fmtNum(p.price) }}
                                </div>
                            </div>
                            <div v-else>
                                <div style="font-family: Archivo; font-weight: 800; font-size: 30px; color: #fff">Enquire for price</div>
                                <div style="font-size: 12.5px; font-weight: 600; color: #8ea0bc; margin-top: 3px">Quoted per destination — FOB or CIF, current market rate</div>
                            </div>

                            <!-- Key specs -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; overflow: hidden; margin-top: 20px">
                                <div v-for="s in keySpecs" :key="s.label" style="background: rgba(11, 30, 59, 0.6); padding: 12px 16px">
                                    <div style="font-size: 10.5px; font-weight: 800; letter-spacing: 0.05em; color: #8ea0bc">{{ s.label }}</div>
                                    <div style="font-size: 14.5px; font-weight: 700; color: #fff; margin-top: 3px">{{ s.value }}</div>
                                </div>
                            </div>

                            <!-- CTAs -->
                            <a
                                :href="whatsappHref"
                                target="_blank"
                                rel="noopener"
                                class="scp2"
                                style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px; height: 52px; border-radius: 14px; background: linear-gradient(150deg, #e5262d, #c8151c); color: #fff; font-family: Manrope; font-weight: 800; font-size: 15.5px; text-decoration: none; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; transition: transform 0.18s"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.297-.497.1-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z" /></svg>
                                Enquire on WhatsApp
                            </a>
                            <Link
                                href="/contact-us"
                                class="scp3"
                                style="display: flex; align-items: center; justify-content: center; gap: 9px; margin-top: 10px; height: 48px; border-radius: 14px; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.16); color: #fff; font-family: Manrope; font-weight: 700; font-size: 14.5px; text-decoration: none; transition: background 0.18s"
                            >
                                Send an enquiry form
                            </Link>

                            <!-- Accordions -->
                            <div style="margin-top: 22px">
                                <div v-for="(s, i) in sections" :key="s.title" style="border-top: 1px solid rgba(255, 255, 255, 0.1)">
                                    <button
                                        type="button"
                                        style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 14px 0; border: none; background: none; cursor: pointer; text-align: left"
                                        :aria-expanded="openSection === i"
                                        @click="toggleSection(i)"
                                    >
                                        <span style="font-family: Archivo; font-weight: 700; font-size: 14.5px; color: #fff">{{ s.title }}</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8ea0bc" stroke-width="2.6" stroke-linecap="round" :style="{ transform: openSection === i ? 'rotate(45deg)' : 'none', transition: '0.2s' }"><path d="M12 5v14M5 12h14" /></svg>
                                    </button>
                                    <div :style="{ display: 'grid', gridTemplateRows: openSection === i ? '1fr' : '0fr', transition: 'grid-template-rows 0.3s cubic-bezier(0.32, 0.72, 0, 1)' }">
                                        <div style="overflow: hidden">
                                            <div v-if="s.html" class="sm-pdhtml-dark" style="padding-bottom: 14px; max-height: 340px; overflow-y: auto" v-html="s.html"></div>
                                            <p v-else style="font-size: 13.5px; line-height: 1.65; color: #a9b7cc; font-weight: 500; padding-bottom: 14px; margin: 0">{{ s.body }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Specification -->
            <section class="sm-body sm-sec" style="padding-bottom: 0">
                <div style="max-width: 1280px; margin: 0 auto">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #8895ab">
                        <span style="width: 22px; height: 2px; background: #e01f26"></span>SPECIFICATION
                    </div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 30px; letter-spacing: -0.02em; color: #0b1e3b; margin-top: 12px">
                        Full details
                    </h2>
                    <div class="sm-pdspecs" style="margin-top: 22px; display: grid; grid-template-columns: 1fr 1fr; gap: 0 56px">
                        <div v-for="[label, value] in specRows" :key="label" style="display: flex; align-items: baseline; justify-content: space-between; gap: 20px; padding: 12px 0; border-bottom: 1px solid #eef1f6">
                            <span style="font-size: 13.5px; font-weight: 700; color: #8494ab">{{ label }}</span>
                            <span style="font-size: 14.5px; font-weight: 700; color: #0b1e3b; text-align: right">{{ value }}</span>
                        </div>
                    </div>

                    <!-- manufacturer detailed specs (torque, top speed, economy, CO2, equipment...) -->
                    <template v-if="extraSpecs.length">
                        <h3 style="font-family: Archivo; font-weight: 800; font-size: 18px; color: #0b1e3b; margin-top: 40px">
                            Manufacturer specifications
                        </h3>
                        <div class="sm-pdspecs" style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 0 56px">
                            <div v-for="[label, value] in extraSpecs" :key="label" style="display: flex; align-items: baseline; justify-content: space-between; gap: 20px; padding: 11px 0; border-bottom: 1px solid #eef1f6">
                                <span style="font-size: 13px; font-weight: 700; color: #8494ab">{{ label }}</span>
                                <span style="font-size: 14px; font-weight: 700; color: #0b1e3b; text-align: right">{{ value }}</span>
                            </div>
                        </div>
                    </template>

                </div>
            </section>

            <SectionDivider />

            <!-- Similar stock -->
            <section v-if="similar_products.length" class="sm-body sm-sec">
                <div style="max-width: 1280px; margin: 0 auto">
                    <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 24px">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #8895ab">
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>SIMILAR STOCK
                            </div>
                            <h2 style="font-family: Archivo; font-weight: 800; font-size: 30px; letter-spacing: -0.02em; color: #0b1e3b; margin-top: 12px">
                                You may also like
                            </h2>
                        </div>
                        <Link
                            v-if="p.category"
                            :href="`/inventory?category=${p.category_id}`"
                            class="scp2"
                            style="flex: 0 0 auto; display: inline-flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #12284a, #0b1e3b); padding: 13px 24px; border-radius: 13px; box-shadow: rgba(11, 30, 59, 0.25) 0 10px 24px; transition: transform 0.18s; text-decoration: none"
                        >
                            View all {{ p.category.cat_title }} →
                        </Link>
                    </div>
                    <div class="sm-invgrid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 26px">
                        <ProductCard v-for="sp in similar_products" :key="sp.id" :product="sp" />
                    </div>
                </div>
            </section>
        </FrontLayout>
    </div>
</template>

<style scoped>
/* description inside the dark summary card */
.sm-pdhtml-dark :deep(p) {
    font-size: 13.5px;
    line-height: 1.65;
    color: #a9b7cc;
    font-weight: 500;
    margin: 0 0 10px;
}
.sm-pdhtml-dark :deep(ul),
.sm-pdhtml-dark :deep(ol) {
    padding: 0;
    margin: 0;
    list-style: none;
}
.sm-pdhtml-dark :deep(li) {
    font-size: 13px;
    line-height: 1.55;
    color: #cdd8e8;
    font-weight: 500;
    padding: 7px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.sm-pdhtml-dark :deep(strong) {
    color: #8ea0bc;
    font-weight: 700;
    font-size: 12px;
}
.sm-pdhtml-dark::-webkit-scrollbar {
    width: 5px;
}
.sm-pdhtml-dark::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.sm-pdhtml :deep(p) {
    font-size: 15px;
    line-height: 1.75;
    color: #5b6b82;
    font-weight: 500;
    margin: 0 0 12px;
}
.sm-pdhtml :deep(ul),
.sm-pdhtml :deep(ol) {
    columns: 2;
    column-gap: 56px;
    padding: 0;
    margin: 0;
    list-style: none;
}
.sm-pdhtml :deep(li) {
    font-size: 14.5px;
    line-height: 1.6;
    color: #33445e;
    font-weight: 500;
    padding: 9px 0;
    border-bottom: 1px solid #eef1f6;
    break-inside: avoid;
}
.sm-pdhtml :deep(strong) {
    color: #8494ab;
    font-weight: 700;
    font-size: 13.5px;
}
@media (max-width: 1080px) {
    .sm-pdgrid {
        grid-template-columns: 1fr !important;
    }
    .sm-pdspecs {
        grid-template-columns: 1fr !important;
    }
    .sm-pdhtml :deep(ul),
    .sm-pdhtml :deep(ol) {
        columns: 1;
    }
    .sm-invgrid {
        grid-template-columns: 1fr 1fr !important;
    }
}
@media (max-width: 560px) {
    .sm-invgrid {
        grid-template-columns: 1fr !important;
    }
}
</style>
