<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import BodyTypeIcon from '@/components/Front/BodyTypeIcon.vue';
import ProductCard from '@/components/Front/ProductCard.vue';

const props = defineProps({
    products: Object,
    categories: { type: Array, default: () => [] },
    makes: { type: Array, default: () => [] },
    facets: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const stockTotal = computed(() => page.props.headerData?.total ?? props.products?.total ?? 0);

/* ---------------- filter state ---------------- */

const csv = (v) => (typeof v === 'string' && v !== '' ? v.split(',').map((s) => s.trim()) : []);

const emptyState = () => ({
    search: '',
    category: [],
    make: [],
    body_style: [],
    country: [],
    fuel: [],
    transmission: [],
    drive_type: [],
    steering: [],
    condition: [],
    emission_standard: [],
    price_min: '', price_max: '',
    year_from: '', year_to: '',
    mileage_min: '', mileage_max: '',
    engine_min: '', engine_max: '',
    power_min: '', power_max: '',
    load_min: '', load_max: '',
    hours_min: '', hours_max: '',
    seats: '', doors: '', axles: '',
});

const fromParams = (params) => {
    const s = emptyState();
    for (const key of Object.keys(s)) {
        if (Array.isArray(s[key])) s[key] = csv(params[key]);
        else s[key] = params[key] ?? '';
    }
    return s;
};

const toParams = (state, extra = {}) => {
    const params = {};
    for (const [key, value] of Object.entries(state)) {
        if (Array.isArray(value)) {
            if (value.length) params[key] = value.join(',');
        } else if (value !== '' && value !== null && value !== undefined) {
            params[key] = value;
        }
    }
    return { ...params, ...extra };
};

const applied = ref(fromParams(props.filters));
const sort = ref(props.filters.sort ?? '');
watch(() => props.filters, (f) => {
    applied.value = fromParams(f);
    sort.value = f.sort ?? '';
});

const loading = ref(false);
let offStart, offFinish;

const go = (params, { replace = false } = {}) => {
    router.get('/inventory', params, {
        preserveState: true,
        preserveScroll: true,
        only: ['products', 'filters'],
        replace,
    });
};

const currentParams = () => toParams(applied.value, sort.value ? { sort: sort.value } : {});

/* Sidebar controls apply instantly; rapid ticks batch through a short debounce. */
let sideTimer = null;
const sideApply = () => {
    clearTimeout(sideTimer);
    sideTimer = setTimeout(() => go(currentParams()), 250);
};

const toggleApplied = (arr, value) => {
    const i = arr.indexOf(value);
    i === -1 ? arr.push(value) : arr.splice(i, 1);
    sideApply();
};

const resetKeys = (...keys) => {
    for (const k of keys) {
        applied.value[k] = Array.isArray(applied.value[k]) ? [] : '';
    }
    sideApply();
};

let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => go(currentParams(), { replace: true }), 400);
};

const onSortChange = () => go(currentParams());

/* ---------------- applied chips (results header, reference style) ---------------- */

const kFmt = (n) => (n >= 1000 ? `$${(n / 1000).toLocaleString()}k` : `$${Number(n).toLocaleString()}`);
const makeName = (id) => props.makes.find((m) => String(m.id) === String(id))?.cat_title ?? id;
const categoryName = (id) => props.categories.find((c) => String(c.id) === String(id))?.cat_title ?? id;

const appliedChips = computed(() => {
    const a = applied.value;
    const chips = [];
    const listChip = (key, values, labelFn = (v) => v) => {
        for (const v of values) {
            chips.push({ id: `${key}:${v}`, text: labelFn(v), clear: () => toggleApplied(a[key], v) });
        }
    };

    listChip('category', a.category, categoryName);
    listChip('make', a.make, makeName);
    listChip('body_style', a.body_style);
    listChip('country', a.country);
    listChip('fuel', a.fuel);
    listChip('transmission', a.transmission);
    listChip('drive_type', a.drive_type);
    listChip('steering', a.steering);
    listChip('condition', a.condition);
    listChip('emission_standard', a.emission_standard);

    const range = (id, min, max, fmt, keys) => {
        if (min === '' && max === '') return;
        const text = min !== '' && max !== '' ? `${fmt(min)} – ${fmt(max)}`
            : min !== '' ? `${fmt(min)}+` : `Under ${fmt(max)}`;
        chips.push({ id, text, clear: () => resetKeys(...keys) });
    };
    range('price', a.price_min, a.price_max, (v) => kFmt(+v), ['price_min', 'price_max']);
    range('year', a.year_from, a.year_to, (v) => v, ['year_from', 'year_to']);
    range('mileage', a.mileage_min, a.mileage_max, (v) => `${(+v).toLocaleString()} km`, ['mileage_min', 'mileage_max']);
    range('engine', a.engine_min, a.engine_max, (v) => `${v}cc`, ['engine_min', 'engine_max']);
    range('power', a.power_min, a.power_max, (v) => `${v}hp`, ['power_min', 'power_max']);
    range('load', a.load_min, a.load_max, (v) => `${(+v).toLocaleString()}kg`, ['load_min', 'load_max']);
    range('hours', a.hours_min, a.hours_max, (v) => `${(+v).toLocaleString()}h`, ['hours_min', 'hours_max']);

    for (const [key, label] of [['seats', 'seats'], ['doors', 'doors'], ['axles', 'axles']]) {
        if (a[key] !== '') chips.push({ id: key, text: `${a[key]} ${label}`, clear: () => resetKeys(key) });
    }

    return chips;
});

const clearAllApplied = () => {
    const s = emptyState();
    applied.value = s;
    go(toParams(s, sort.value ? { sort: sort.value } : {}));
};

/* filters that only live in the drawer (badge on the Advanced button) */
const advancedCount = computed(() => {
    const a = applied.value;
    let n = a.fuel.length + a.transmission.length + a.drive_type.length + a.steering.length
        + a.condition.length + a.emission_standard.length;
    for (const k of ['seats', 'doors', 'axles', 'year_from', 'year_to', 'mileage_min', 'mileage_max',
        'engine_min', 'engine_max', 'power_min', 'power_max', 'load_min', 'load_max', 'hours_min', 'hours_max']) {
        if (a[k] !== '') n++;
    }
    return n;
});

/* ---------------- dynamic page head ---------------- */

const selectedCategoryNames = computed(() => applied.value.category.map(categoryName));
const headTitle = computed(() => {
    if (selectedCategoryNames.value.length === 1) {
        const name = selectedCategoryNames.value[0];
        // only trust resolvable names — an unknown id must never end up in the h1
        if (props.categories.some((c) => c.cat_title === name)) {
            return `Browse our range of ${name}`;
        }
    }
    return 'Browse Our Entire Range';
});
// Transparent cutout art per category (user-supplied), floated on the
// banner's right like a showroom podium.
const BANNER_IMAGES = {
    'Cars': '/storage/cat_images/cars.png',
    'Trucks': '/storage/cat_images/trucks.png',
    'Buses': '/storage/cat_images/buses.png',
    'Electric Vehicles': '/storage/cat_images/electric-car.png',
    'Tractors': '/storage/cat_images/wheel-tractor-logo.png',
    'Heavy Machinery': '/storage/cat_images/heavy-machinery-banner.png',
    'Equipment': '/storage/cat_images/equipment-banner.png',
};
const ALL_BANNER_IMAGE = '/storage/cat_images/all-vehicles-banner.png';

const headImage = computed(() => {
    if (selectedCategoryNames.value.length === 1) {
        return BANNER_IMAGES[selectedCategoryNames.value[0]] ?? ALL_BANNER_IMAGE;
    }
    return ALL_BANNER_IMAGE;
});

/* ---------------- sidebar sections (collapsible) ---------------- */

const sideOpen = reactive({
    category: true,
    body: true,
    make: true,
    price: false,
    origin: false,
});
// a section holding an applied filter never starts collapsed
const a0 = fromParams(props.filters);
if (a0.price_min !== '' || a0.price_max !== '') sideOpen.price = true;
if (a0.country.length) sideOpen.origin = true;

/* ---------------- sidebar option lists ---------------- */

const makeSearch = ref('');
const showAllMakes = ref(false);
const sidebarMakes = computed(() => {
    let list = props.makes;
    if (makeSearch.value.trim()) {
        const q = makeSearch.value.trim().toLowerCase();
        list = list.filter((m) => m.cat_title.toLowerCase().includes(q));
    }
    return showAllMakes.value || makeSearch.value ? list : list.slice(0, 8);
});

const showAllBodies = ref(false);
const sidebarBodies = computed(() => {
    const list = props.facets.body_styles ?? [];
    return showAllBodies.value ? list : list.slice(0, 8);
});

const priceSteps = [500, 1000, 2000, 3000, 5000, 7500, 10000, 15000, 20000, 30000, 50000, 75000, 100000, 150000, 200000];
const mileageSteps = [10000, 20000, 30000, 50000, 75000, 100000, 150000, 200000, 250000];
const engineSteps = [660, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 8000, 10000, 13000];
const powerSteps = [50, 100, 150, 200, 300, 400, 500, 700];
const loadSteps = [1000, 3000, 5000, 10000, 20000, 30000];
const hoursSteps = [500, 1000, 2000, 5000, 10000, 20000];

const yearOptions = computed(() => {
    const max = props.facets.year_bounds?.max || new Date().getFullYear();
    const min = Math.max(props.facets.year_bounds?.min || 1970, 1970);
    const list = [];
    for (let y = max; y >= min; y--) list.push(y);
    return list;
});

const specGroups = computed(() => [
    { key: 'fuel', label: 'FUEL', options: props.facets.fuels ?? [] },
    { key: 'transmission', label: 'TRANSMISSION', options: props.facets.transmissions ?? [] },
    { key: 'drive_type', label: 'DRIVE TYPE', options: props.facets.drive_types ?? [] },
    { key: 'steering', label: 'STEERING', options: props.facets.steerings ?? [] },
    { key: 'condition', label: 'CONDITION', options: props.facets.conditions ?? [] },
    { key: 'emission_standard', label: 'EMISSION STANDARD', options: props.facets.emission_standards ?? [] },
].filter((g) => g.options.length > 0));

/* ---------------- advanced drawer (staged) ---------------- */

const drawerOpen = ref(false);
const staged = ref(emptyState());
const openSections = reactive({ body: true, year: true, mileage: true, spec: false, commercial: false });
const sectionEls = {};
const bindSection = (name) => (el) => { if (el) sectionEls[name] = el; };

const openDrawer = (section = null) => {
    staged.value = JSON.parse(JSON.stringify(applied.value));
    drawerOpen.value = true;
    stagedTotal.value = props.products?.total ?? null;
    if (section) {
        openSections[section] = true;
        nextTick(() => sectionEls[section]?.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    }
};
const closeDrawer = () => (drawerOpen.value = false);
const onKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };

const applyDrawer = () => {
    applied.value = JSON.parse(JSON.stringify(staged.value));
    drawerOpen.value = false;
    go(currentParams());
};

const clearAllStaged = () => {
    const keepSearch = staged.value.search;
    staged.value = emptyState();
    staged.value.search = keepSearch;
};

const stagedTotal = ref(null);
const counting = ref(false);
let countTimer = null;
watch(staged, () => {
    if (!drawerOpen.value) return;
    clearTimeout(countTimer);
    counting.value = true;
    countTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get('/inventory/count', { params: toParams(staged.value) });
            stagedTotal.value = data.total;
        } catch { /* keep the last count */ } finally {
            counting.value = false;
        }
    }, 350);
}, { deep: true });

const toggleIn = (arr, value) => {
    const i = arr.indexOf(value);
    i === -1 ? arr.push(value) : arr.splice(i, 1);
};

const commercialCategoryNames = ['Trucks', 'Buses', 'Heavy Machinery', 'Tractors', 'Equipment'];
const showCommercial = computed(() => {
    if (!staged.value.category.length) return false;
    return props.categories.some((c) => staged.value.category.includes(String(c.id)) && commercialCategoryNames.includes(c.cat_title));
});

/* ---------------- pagination ---------------- */

const pages = computed(() => {
    const current = props.products?.current_page ?? 1;
    const last = props.products?.last_page ?? 1;
    const items = [];
    if (last <= 7) {
        for (let i = 1; i <= last; i++) items.push(i);
    } else {
        items.push(1);
        if (current > 3) items.push('…');
        for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) items.push(i);
        if (current < last - 2) items.push('…');
        items.push(last);
    }
    return items;
});

const goPage = (p) => {
    if (p === '…' || p === (props.products?.current_page ?? 1)) return;
    go({ ...currentParams(), page: p });
};

/* ---------------- lifecycle ---------------- */

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    offStart = router.on('start', () => (loading.value = true));
    offFinish = router.on('finish', () => (loading.value = false));
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    offStart?.(); offFinish?.();
});

watch(drawerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <Head title="Inventory" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <!-- Page banner: navy card like the other pages, text left, photo right -->
            <section class="sm-body" style="padding: 60px 24px 0">
                <div style="max-width: 1420px; margin: 0 auto">
                    <div style="position: relative; overflow: hidden; border-radius: 28px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730)">
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="1.5" style="position: absolute; top: -70px; left: -70px; width: 220px; height: 220px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>
                        <div style="position: absolute; top: -120px; right: 6%; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.18), transparent 70%)"></div>
                        <img
                            :key="headImage"
                            :src="headImage"
                            alt=""
                            aria-hidden="true"
                            class="sm-invcutout"
                            style="position: absolute; right: 52px; top: 50%; transform: translateY(-50%); height: 72%; max-width: 38%; object-fit: contain; filter: drop-shadow(0 22px 30px rgba(0, 0, 0, 0.45))"
                        />

                        <div class="sm-invbanner" style="position: relative; padding: 52px 56px">
                            <div style="display: inline-flex; align-items: center; gap: 8px; color: #cdd8e8; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>INVENTORY
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 46px; letter-spacing: -0.025em; color: #fff; margin-top: 14px; line-height: 1.08; max-width: 640px">
                                {{ headTitle }}<span style="color: #e01f26">.</span>
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin-top: 12px; max-width: 480px">
                                Sourced from Japan, China and Europe — updated daily.
                            </p>
                            <div style="display: flex; align-items: center; gap: 9px; margin-top: 20px">
                                <span class="sm-livedot"></span>
                                <span style="font-family: Archivo; font-weight: 800; font-size: 21px; color: #fff">{{ stockTotal.toLocaleString() }}</span>
                                <span style="font-size: 12px; font-weight: 800; letter-spacing: 0.06em; color: #8ea0bc">VEHICLES LIVE RIGHT NOW</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sidebar + results -->
            <section class="sm-body" style="padding: 30px 24px 60px">
                <div class="sm-shopgrid" style="max-width: 1420px; margin: 0 auto; display: grid; grid-template-columns: 228px 1fr; gap: 36px; align-items: start">
                    <!-- Sidebar -->
                    <aside class="sm-sidebar">
                        <!-- Category -->
                        <div class="sm-ssec">
                            <button type="button" class="sm-ssec-head" :aria-expanded="sideOpen.category" @click="sideOpen.category = !sideOpen.category">
                                <span>Category</span>
                                <span class="sm-ssec-tools">
                                    <span v-if="applied.category.length" class="sm-sreset" role="button" @click.stop="resetKeys('category')">Reset</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.6" stroke-linecap="round" :style="{ transform: sideOpen.category ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-show="sideOpen.category" class="sm-ssec-body">
                                <label v-for="c in categories" :key="c.id" class="sm-frow">
                                    <input type="checkbox" class="sm-fcheck" :checked="applied.category.includes(String(c.id))" @change="toggleApplied(applied.category, String(c.id))" />
                                    <span style="flex: 1">{{ c.cat_title }}</span>
                                    <span class="sm-fcount">{{ Number(c.products_count).toLocaleString() }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Body type -->
                        <div class="sm-ssec">
                            <button type="button" class="sm-ssec-head" :aria-expanded="sideOpen.body" @click="sideOpen.body = !sideOpen.body">
                                <span>Body type</span>
                                <span class="sm-ssec-tools">
                                    <span v-if="applied.body_style.length" class="sm-sreset" role="button" @click.stop="resetKeys('body_style')">Reset</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.6" stroke-linecap="round" :style="{ transform: sideOpen.body ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-show="sideOpen.body" class="sm-ssec-body">
                                <label v-for="b in sidebarBodies" :key="b.value" class="sm-frow">
                                    <input type="checkbox" class="sm-fcheck" :checked="applied.body_style.includes(b.value)" @change="toggleApplied(applied.body_style, b.value)" />
                                    <span style="flex: 1">{{ b.value }}</span>
                                    <span class="sm-fcount">{{ Number(b.count).toLocaleString() }}</span>
                                </label>
                                <button v-if="(facets.body_styles ?? []).length > 8" type="button" class="sm-smore" @click="showAllBodies = !showAllBodies">
                                    {{ showAllBodies ? 'Show fewer' : `Show all ${(facets.body_styles ?? []).length}` }}
                                </button>
                            </div>
                        </div>

                        <!-- Make -->
                        <div class="sm-ssec">
                            <button type="button" class="sm-ssec-head" :aria-expanded="sideOpen.make" @click="sideOpen.make = !sideOpen.make">
                                <span>Make</span>
                                <span class="sm-ssec-tools">
                                    <span v-if="applied.make.length" class="sm-sreset" role="button" @click.stop="resetKeys('make')">Reset</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.6" stroke-linecap="round" :style="{ transform: sideOpen.make ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-show="sideOpen.make" class="sm-ssec-body">
                                <input
                                    v-model="makeSearch"
                                    type="text"
                                    placeholder="Search makes…"
                                    style="width: 100%; height: 38px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6eaf0; padding: 0 12px; font-size: 13.5px; font-weight: 600; color: #0b1e3b; outline: none; margin-bottom: 6px"
                                />
                                <label v-for="m in sidebarMakes" :key="m.id" class="sm-frow">
                                    <input type="checkbox" class="sm-fcheck" :checked="applied.make.includes(String(m.id))" @change="toggleApplied(applied.make, String(m.id))" />
                                    <span style="flex: 1">{{ m.cat_title }}</span>
                                    <span class="sm-fcount">{{ Number(m.products_count).toLocaleString() }}</span>
                                </label>
                                <button v-if="!showAllMakes && !makeSearch && makes.length > 8" type="button" class="sm-smore" @click="showAllMakes = true">
                                    Show all {{ makes.length }} makes
                                </button>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="sm-ssec">
                            <button type="button" class="sm-ssec-head" :aria-expanded="sideOpen.price" @click="sideOpen.price = !sideOpen.price">
                                <span>Price</span>
                                <span class="sm-ssec-tools">
                                    <span v-if="applied.price_min !== '' || applied.price_max !== ''" class="sm-sreset" role="button" @click.stop="resetKeys('price_min', 'price_max')">Reset</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.6" stroke-linecap="round" :style="{ transform: sideOpen.price ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-show="sideOpen.price" class="sm-ssec-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px">
                                <select v-model="applied.price_min" class="sm-fselect" style="height: 38px" @change="sideApply">
                                    <option value="">No min</option>
                                    <option v-for="p in priceSteps" :key="'spmin' + p" :value="p">{{ kFmt(p) }}</option>
                                </select>
                                <select v-model="applied.price_max" class="sm-fselect" style="height: 38px" @change="sideApply">
                                    <option value="">No max</option>
                                    <option v-for="p in priceSteps" :key="'spmax' + p" :value="p">{{ kFmt(p) }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Origin -->
                        <div class="sm-ssec">
                            <button type="button" class="sm-ssec-head" :aria-expanded="sideOpen.origin" @click="sideOpen.origin = !sideOpen.origin">
                                <span>Origin</span>
                                <span class="sm-ssec-tools">
                                    <span v-if="applied.country.length" class="sm-sreset" role="button" @click.stop="resetKeys('country')">Reset</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.6" stroke-linecap="round" :style="{ transform: sideOpen.origin ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-show="sideOpen.origin" class="sm-ssec-body">
                                <label v-for="c in facets.countries ?? []" :key="c.value" class="sm-frow">
                                    <input type="checkbox" class="sm-fcheck" :checked="applied.country.includes(c.value)" @change="toggleApplied(applied.country, c.value)" />
                                    <span style="flex: 1">{{ c.value }}</span>
                                    <span class="sm-fcount">{{ Number(c.count).toLocaleString() }}</span>
                                </label>
                            </div>
                        </div>

                        <button type="button" class="scp2 sm-advbtn" @click="openDrawer()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                            Advanced filters
                            <span v-if="advancedCount > 0" class="sm-allfilters-badge">{{ advancedCount }}</span>
                        </button>
                    </aside>

                    <!-- Results column -->
                    <div style="min-width: 0">
                        <!-- Applied chips -->
                        <div v-if="appliedChips.length" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 16px">
                            <button v-for="chip in appliedChips" :key="chip.id" type="button" class="sm-achip" @click="chip.clear()">
                                {{ chip.text }}
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                            </button>
                            <button type="button" style="border: none; background: none; font-size: 13px; font-weight: 800; color: #e01f26; cursor: pointer; text-decoration: underline; text-underline-offset: 3px" @click="clearAllApplied">
                                Clear all
                            </button>
                        </div>

                        <!-- Count | search + sort -->
                        <div class="sm-reshead" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 16px; border-bottom: 1px solid #eef1f6">
                            <div :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '18px', color: '#0b1e3b', opacity: loading ? 0.5 : 1, transition: 'opacity 0.2s', flex: '0 0 auto' }">
                                Showing {{ (products?.from ?? 0).toLocaleString() }}–{{ (products?.to ?? 0).toLocaleString() }}
                                <span style="font-weight: 600; color: #8494ab">of {{ (products?.total ?? 0).toLocaleString() }} results</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 14px; min-width: 0">
                                <button type="button" class="sm-advbtn sm-advbtn-mobile scp2" style="display: none" @click="openDrawer()">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                                    Filters
                                    <span v-if="advancedCount + appliedChips.length > 0" class="sm-allfilters-badge">{{ advancedCount + appliedChips.length }}</span>
                                </button>
                                <div class="sm-ressearch" style="position: relative; width: 240px">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%)"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                                    <input
                                        v-model="applied.search"
                                        type="text"
                                        placeholder="Search within results…"
                                        style="width: 100%; height: 38px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6eaf0; padding: 0 12px 0 34px; font-size: 13.5px; font-weight: 600; color: #0b1e3b; outline: none"
                                        @input="onSearchInput"
                                    />
                                </div>
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #8494ab; flex: 0 0 auto">
                                    Sort
                                    <select v-model="sort" style="border: none; background: transparent; font-family: Manrope, sans-serif; font-weight: 700; font-size: 14px; color: #0b1e3b; cursor: pointer; outline: none" @change="onSortChange">
                                        <option value="">Newest listed</option>
                                        <option value="price_asc">Price: low to high</option>
                                        <option value="price_desc">Price: high to low</option>
                                        <option value="year_desc">Year: newest first</option>
                                        <option value="mileage_asc">Mileage: lowest first</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <!-- Grid -->
                        <div
                            v-if="products?.data?.length"
                            class="sm-invgrid"
                            :style="{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '24px', marginTop: '22px', opacity: loading ? 0.5 : 1, transition: 'opacity 0.2s' }"
                        >
                            <ProductCard v-for="p in products.data" :key="p.id" :product="p" />
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="!loading" style="text-align: center; padding: 60px 24px">
                            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#0b1e3b" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto"><path d="M14 16.5V14a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2.5" /><path d="M2 16.5h20" /><circle cx="6.5" cy="18.5" r="1.8" /><circle cx="17.5" cy="18.5" r="1.8" /><path d="M14 12V8a2 2 0 0 1 2-2h2.6L22 10v6.5" /></svg>
                            <div style="font-family: Archivo; font-weight: 700; font-size: 20px; color: #0b1e3b; margin-top: 18px">No vehicles match these filters</div>
                            <p style="font-size: 15px; color: #5b6b82; font-weight: 500; margin-top: 8px">Try removing one:</p>
                            <div style="display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 14px">
                                <button v-for="chip in appliedChips" :key="chip.id" type="button" class="sm-achip" @click="chip.clear()">
                                    {{ chip.text }}
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="scp2"
                                style="display: inline-flex; align-items: center; gap: 9px; margin-top: 22px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 13px 26px; border-radius: 13px; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; border: none; cursor: pointer; transition: transform 0.18s"
                                @click="clearAllApplied"
                            >
                                Clear all filters
                            </button>
                        </div>

                        <!-- Pagination -->
                        <div v-if="(products?.last_page ?? 1) > 1" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 40px">
                            <button type="button" class="sm-pagebtn" :disabled="(products?.current_page ?? 1) <= 1" aria-label="Previous page" @click="goPage((products?.current_page ?? 1) - 1)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                            </button>
                            <button
                                v-for="(p, i) in pages"
                                :key="`${p}-${i}`"
                                type="button"
                                class="sm-pagebtn"
                                :class="{ 'is-on': p === (products?.current_page ?? 1), 'is-gap': p === '…' }"
                                @click="goPage(p)"
                            >
                                {{ p }}
                            </button>
                            <button type="button" class="sm-pagebtn" :disabled="(products?.current_page ?? 1) >= (products?.last_page ?? 1)" aria-label="Next page" @click="goPage((products?.current_page ?? 1) + 1)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Advanced drawer -->
            <Transition name="sm-scrim">
                <div v-if="drawerOpen" style="position: fixed; inset: 0; background: rgba(11, 30, 59, 0.45); z-index: 80" @click="closeDrawer"></div>
            </Transition>
            <Transition name="sm-drawer">
                <aside v-if="drawerOpen" class="sm-drawer" role="dialog" aria-label="Advanced filters">
                    <div style="flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; height: 72px; border-bottom: 1px solid #eef1f6">
                        <div style="font-family: Archivo; font-weight: 800; font-size: 22px; color: #0b1e3b">Advanced filters</div>
                        <button type="button" class="sm-carbtn" aria-label="Close filters" @click="closeDrawer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div style="flex: 1 1 auto; overflow-y: auto">
                        <!-- Body type tiles (mobile also reaches category/make here via checkboxes below) -->
                        <div :ref="bindSection('body')" class="sm-fsec sm-drawer-mainsections">
                            <button type="button" class="sm-fsec-head" @click="openSections.body = !openSections.body">
                                <span>Category, make &amp; body</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" :style="{ transform: openSections.body ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <div v-if="openSections.body" class="sm-fsec-body">
                                <div style="font-size: 12px; font-weight: 800; letter-spacing: 0.06em; color: #8895ab; margin-bottom: 6px">CATEGORY</div>
                                <label v-for="c in categories" :key="'dc' + c.id" class="sm-frow">
                                    <input type="checkbox" class="sm-fcheck" :checked="staged.category.includes(String(c.id))" @change="toggleIn(staged.category, String(c.id))" />
                                    <span style="flex: 1">{{ c.cat_title }}</span>
                                    <span class="sm-fcount">{{ Number(c.products_count).toLocaleString() }}</span>
                                </label>
                                <div style="font-size: 12px; font-weight: 800; letter-spacing: 0.06em; color: #8895ab; margin: 14px 0 6px">BODY TYPE</div>
                                <div class="sm-ftiles">
                                    <button
                                        v-for="b in (facets.body_styles ?? []).slice(0, 18)"
                                        :key="'db' + b.value"
                                        type="button"
                                        class="sm-ftile"
                                        :class="{ 'is-on': staged.body_style.includes(b.value) }"
                                        @click="toggleIn(staged.body_style, b.value)"
                                    >
                                        <div :style="{ color: staged.body_style.includes(b.value) ? '#ff6b70' : '#8494ab' }"><BodyTypeIcon :type="b.value" :size="44" /></div>
                                        <div class="sm-ftile-name">{{ b.value }}</div>
                                        <div class="sm-ftile-count">{{ Number(b.count).toLocaleString() }}</div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Year -->
                        <div :ref="bindSection('year')" class="sm-fsec">
                            <button type="button" class="sm-fsec-head" @click="openSections.year = !openSections.year">
                                <span>Year</span>
                                <span style="display: inline-flex; align-items: center; gap: 10px">
                                    <span v-if="!openSections.year && (staged.year_from !== '' || staged.year_to !== '')" class="sm-fsec-sum">{{ staged.year_from || '…' }} – {{ staged.year_to || '…' }}</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" :style="{ transform: openSections.year ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-if="openSections.year" class="sm-fsec-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                                <select v-model="staged.year_from" class="sm-fselect">
                                    <option value="">From</option>
                                    <option v-for="y in yearOptions" :key="'yf' + y" :value="y">{{ y }}</option>
                                </select>
                                <select v-model="staged.year_to" class="sm-fselect">
                                    <option value="">To</option>
                                    <option v-for="y in yearOptions" :key="'yt' + y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Mileage -->
                        <div :ref="bindSection('mileage')" class="sm-fsec">
                            <button type="button" class="sm-fsec-head" @click="openSections.mileage = !openSections.mileage">
                                <span>Mileage</span>
                                <span style="display: inline-flex; align-items: center; gap: 10px">
                                    <span v-if="!openSections.mileage && (staged.mileage_min !== '' || staged.mileage_max !== '')" class="sm-fsec-sum">set</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" :style="{ transform: openSections.mileage ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                                </span>
                            </button>
                            <div v-if="openSections.mileage" class="sm-fsec-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                                <select v-model="staged.mileage_min" class="sm-fselect">
                                    <option value="">No min</option>
                                    <option v-for="m in mileageSteps" :key="'mmin' + m" :value="m">{{ m.toLocaleString() }} km</option>
                                </select>
                                <select v-model="staged.mileage_max" class="sm-fselect">
                                    <option value="">No max</option>
                                    <option v-for="m in mileageSteps" :key="'mmax' + m" :value="m">{{ m.toLocaleString() }} km</option>
                                </select>
                            </div>
                        </div>

                        <!-- Specification -->
                        <div :ref="bindSection('spec')" class="sm-fsec">
                            <button type="button" class="sm-fsec-head" @click="openSections.spec = !openSections.spec">
                                <span>Specification</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" :style="{ transform: openSections.spec ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <div v-if="openSections.spec" class="sm-fsec-body">
                                <div v-for="g in specGroups" :key="g.key" style="margin-bottom: 18px">
                                    <div style="font-size: 12px; font-weight: 800; letter-spacing: 0.06em; color: #8895ab; margin-bottom: 6px">{{ g.label }}</div>
                                    <label v-for="o in g.options" :key="o.value" class="sm-frow">
                                        <input type="checkbox" class="sm-fcheck" :checked="staged[g.key].includes(o.value)" @change="toggleIn(staged[g.key], o.value)" />
                                        <span style="flex: 1">{{ o.value }}</span>
                                        <span class="sm-fcount">{{ Number(o.count).toLocaleString() }}</span>
                                    </label>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                                    <label class="sm-flabel">SEATS
                                        <select v-model="staged.seats" class="sm-fselect" style="margin-top: 6px">
                                            <option value="">Any</option>
                                            <option v-for="n in [2, 3, 4, 5, 6, 7, 8, 9]" :key="'s' + n" :value="n">{{ n }}</option>
                                        </select>
                                    </label>
                                    <label class="sm-flabel">DOORS
                                        <select v-model="staged.doors" class="sm-fselect" style="margin-top: 6px">
                                            <option value="">Any</option>
                                            <option v-for="n in [2, 3, 4, 5]" :key="'d' + n" :value="n">{{ n }}</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Commercial specs -->
                        <div v-if="showCommercial" :ref="bindSection('commercial')" class="sm-fsec">
                            <button type="button" class="sm-fsec-head" @click="openSections.commercial = !openSections.commercial">
                                <span>Commercial specs</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="2.4" stroke-linecap="round" :style="{ transform: openSections.commercial ? 'rotate(180deg)' : 'none', transition: '0.2s' }"><path d="m6 9 6 6 6-6" /></svg>
                            </button>
                            <div v-if="openSections.commercial" class="sm-fsec-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px 10px">
                                <label class="sm-flabel">ENGINE CC MIN
                                    <select v-model="staged.engine_min" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in engineSteps" :key="'emin' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">ENGINE CC MAX
                                    <select v-model="staged.engine_max" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in engineSteps" :key="'emax' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">POWER HP MIN
                                    <select v-model="staged.power_min" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in powerSteps" :key="'pwmin' + v" :value="v">{{ v }}</option></select>
                                </label>
                                <label class="sm-flabel">POWER HP MAX
                                    <select v-model="staged.power_max" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in powerSteps" :key="'pwmax' + v" :value="v">{{ v }}</option></select>
                                </label>
                                <label class="sm-flabel">LOAD KG MIN
                                    <select v-model="staged.load_min" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in loadSteps" :key="'lmin' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">LOAD KG MAX
                                    <select v-model="staged.load_max" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in loadSteps" :key="'lmax' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">RUNNING HOURS MIN
                                    <select v-model="staged.hours_min" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in hoursSteps" :key="'hmin' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">RUNNING HOURS MAX
                                    <select v-model="staged.hours_max" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="v in hoursSteps" :key="'hmax' + v" :value="v">{{ v.toLocaleString() }}</option></select>
                                </label>
                                <label class="sm-flabel">AXLES
                                    <select v-model="staged.axles" class="sm-fselect" style="margin-top: 6px"><option value="">Any</option><option v-for="n in [2, 3, 4, 5, 6]" :key="'ax' + n" :value="n">{{ n }}</option></select>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="flex: 0 0 auto; display: flex; align-items: center; gap: 16px; padding: 14px 24px; border-top: 1px solid #eef1f6; background: #fff">
                        <button type="button" style="border: none; background: none; font-size: 14px; font-weight: 700; color: #5b6b82; cursor: pointer" @click="clearAllStaged">Clear all</button>
                        <button
                            type="button"
                            class="scp2"
                            :style="{
                                flex: '1 1 auto', height: '48px', border: 'none', cursor: stagedTotal === 0 ? 'default' : 'pointer',
                                borderRadius: '13px', fontFamily: 'Manrope, sans-serif', fontWeight: 800, fontSize: '15px', color: '#fff',
                                background: stagedTotal === 0 ? 'rgba(11, 30, 59, 0.3)' : 'linear-gradient(150deg, #e5262d, #c8151c)',
                                boxShadow: stagedTotal === 0 ? 'none' : 'rgba(224, 31, 38, 0.35) 0 10px 24px',
                                transition: 'transform 0.18s',
                            }"
                            :disabled="stagedTotal === 0"
                            @click="applyDrawer"
                        >
                            <template v-if="stagedTotal === 0">No exact matches</template>
                            <template v-else>
                                Show <span :style="{ opacity: counting ? 0.5 : 1 }">{{ stagedTotal !== null ? stagedTotal.toLocaleString() : '…' }}</span> vehicles
                            </template>
                        </button>
                    </div>
                </aside>
            </Transition>
        </FrontLayout>
    </div>
</template>
