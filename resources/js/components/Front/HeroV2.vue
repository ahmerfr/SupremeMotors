<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    makes: { type: Array, default: () => [] },
    buyerImages: { type: Array, default: () => [] },
});

// Skip avatar images that fail to load and backfill from the spares.
const failedAvatars = ref(new Set());
const avatars = computed(() => props.buyerImages.filter((img) => !failedAvatars.value.has(img)).slice(0, 4));
const markAvatarFailed = (img) => {
    failedAvatars.value = new Set(failedAvatars.value).add(img);
};

const search = ref('');
const make = ref('');
const bodyStyle = ref('');
const price = ref('');

const bodyTypes = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Wagon', 'Van / Minivan', 'Mini Vehicle', 'Truck', 'Convertible'];
const priceRanges = [
    { label: 'Under 20k', min: '', max: '20000' },
    { label: '20k – 50k', min: '20000', max: '50000' },
    { label: '50k+', min: '50000', max: '' },
];
const popular = ['Toyota RAV4', 'BMW X5', 'Audi A6', 'Porsche'];

const submit = () => {
    const params = { type: 'search' };
    if (search.value) params.search = search.value;
    if (make.value) params.make = make.value;
    if (bodyStyle.value) params.body_style = bodyStyle.value;
    if (price.value !== '') {
        const range = priceRanges[price.value];
        if (range.min) params.price_min = range.min;
        if (range.max) params.price_max = range.max;
    }
    router.get('/inventory', params);
};

const searchPopular = (term) => router.get('/inventory', { type: 'search', search: term });
</script>

<template>
    <section class="sm-body" style="position: relative; background: #081730; overflow: hidden">
        <!-- Niche background image with navy gradient scrim -->
        <img src="/assets/images/hero-truck.jpg" alt="" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 72% center" />
        <div style="position: absolute; inset: 0; background: linear-gradient(95deg, rgba(8, 23, 48, 0.96) 0%, rgba(11, 30, 59, 0.88) 38%, rgba(11, 30, 59, 0.62) 68%, rgba(8, 23, 48, 0.72) 100%)"></div>
        <div style="position: absolute; inset: 0; background: radial-gradient(120% 130% at 85% 0%, rgba(14, 36, 71, 0.35) 0%, rgba(11, 30, 59, 0.25) 45%, rgba(8, 23, 48, 0.55) 100%)"></div>
        <div style="position: absolute; top: -120px; right: -80px; width: 520px; height: 520px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 1px; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12), transparent)"></div>

        <div class="sm-herogrid" style="position: relative; max-width: 1280px; margin: 0 auto; padding: 70px 24px 88px; display: grid; grid-template-columns: 1.05fr 1fr; gap: 56px; align-items: center">
            <div>
                <h1 class="sm-h1" style="font-family: Archivo; font-weight: 800; font-size: 60px; line-height: 1.04; letter-spacing: -0.025em; color: #fff; margin: 26px 0 0">
                    Your
                    <span style="position: relative; white-space: nowrap">Journey<span style="position: absolute; left: 0; right: 0; bottom: 6px; height: 11px; background: rgba(224, 31, 38, 0.5); z-index: -1; border-radius: 3px"></span></span><br />Begins With Us
                </h1>

                <p style="font-size: 18px; line-height: 1.6; color: #a9b7cc; margin: 24px 0 0; max-width: 490px">
                    From family cars to heavy trucks and construction machinery. Sourced across Japan, China and Europe, inspected with care, and delivered wherever the road takes you.
                </p>

                <div style="display: flex; gap: 14px; margin-top: 34px; flex-wrap: wrap">
                    <a href="/inventory" class="scp2" style="display: inline-flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 700; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 16px 28px; border-radius: 13px; box-shadow: rgba(224, 31, 38, 0.34) 0 12px 30px; transition: transform 0.18s; text-decoration: none">
                        Browse Inventory
                        <span style="width: 7px; height: 7px; border-top: 2px solid #fff; border-right: 2px solid #fff; transform: rotate(45deg); display: inline-block"></span>
                    </a>
                    <a href="/how-to-buy" class="scp3" style="display: inline-flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; color: #fff; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.16); padding: 16px 26px; border-radius: 13px; transition: background 0.18s; text-decoration: none">
                        <span style="width: 26px; height: 26px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center"><span style="width: 0; height: 0; border-left: 7px solid #0b1e3b; border-top: 5px solid transparent; border-bottom: 5px solid transparent; margin-left: 2px"></span></span>
                        How It Works
                    </a>
                </div>

                <div style="display: flex; align-items: center; gap: 22px; margin-top: 38px; flex-wrap: wrap">
                    <div style="display: flex; align-items: center">
                        <img
                            v-for="(img, i) in avatars"
                            :key="img"
                            :src="img"
                            alt=""
                            @error="markAvatarFailed(img)"
                            :style="{ width: '38px', height: '38px', borderRadius: '50%', border: '2px solid #0b1e3b', marginLeft: i === 0 ? '0' : '-10px', objectFit: 'cover', background: '#c9d3e2' }"
                        />
                    </div>
                    <div style="line-height: 1.3">
                        <div style="font-weight: 800; font-size: 15px"><span style="color: #ffc24b; letter-spacing: 1px">★★★★★</span> <span style="color: #a9b7cc; font-weight: 600">Rated by buyers</span></div>
                        <div style="color: #8496b0; font-size: 13px; font-weight: 600">Quality vehicles, handled with care</div>
                    </div>
                </div>
            </div>

            <!-- Search card -->
            <div style="position: relative">
                <div class="sm-search" style="background: #fff; border-radius: 22px; box-shadow: rgba(0, 0, 0, 0.4) 0 40px 80px; border: 1px solid rgba(255, 255, 255, 0.6); padding: 26px">
                    <div style="display: flex; align-items: center; gap: 13px">
                        <div style="width: 46px; height: 46px; border-radius: 13px; background: linear-gradient(150deg, #e5262d, #c8151c); display: flex; align-items: center; justify-content: center; box-shadow: rgba(224, 31, 38, 0.32) 0 8px 20px">
                            <span style="width: 15px; height: 15px; border: 2px solid #fff; border-radius: 50%; position: relative; display: inline-block"><span style="position: absolute; width: 6px; height: 2px; background: #fff; transform: rotate(45deg); bottom: -2px; right: -3px; border-radius: 2px"></span></span>
                        </div>
                        <div style="line-height: 1.3">
                            <div style="font-family: Archivo; font-weight: 800; font-size: 19px; color: #0b1e3b">Find Your Vehicle</div>
                            <div style="font-size: 13px; font-weight: 600; color: #7d8ea8">Search our curated inventory</div>
                        </div>
                    </div>

                    <div style="margin-top: 22px">
                        <label style="display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin-bottom: 8px; padding-left: 2px">SEARCH</label>
                        <input v-model="search" placeholder="Make or model…" style="width: 100%; border: 1px solid #e6eaf0; border-radius: 12px; padding: 14px 15px; font-size: 14.5px; font-weight: 600; font-family: Manrope; color: #0b1e3b; background: #f8fafc; outline: none" @keyup.enter="submit" />
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 16px">
                        <div>
                            <label style="display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin-bottom: 8px; padding-left: 2px">BRAND</label>
                            <select v-model="make" style="width: 100%; border: 1px solid #e6eaf0; border-radius: 12px; padding: 14px 15px; font-size: 14.5px; font-weight: 600; font-family: Manrope; color: #33445e; background: #f8fafc; outline: none; cursor: pointer">
                                <option value="">All Brands</option>
                                <option v-for="m in props.makes" :key="m.id" :value="m.id">{{ m.cat_title }}</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin-bottom: 8px; padding-left: 2px">TYPE</label>
                            <select v-model="bodyStyle" style="width: 100%; border: 1px solid #e6eaf0; border-radius: 12px; padding: 14px 15px; font-size: 14.5px; font-weight: 600; font-family: Manrope; color: #33445e; background: #f8fafc; outline: none; cursor: pointer">
                                <option value="">All Types</option>
                                <option v-for="t in bodyTypes" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 16px">
                        <label style="display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.06em; color: #8494ab; margin-bottom: 8px; padding-left: 2px">PRICE RANGE</label>
                        <select v-model="price" style="width: 100%; border: 1px solid #e6eaf0; border-radius: 12px; padding: 14px 15px; font-size: 14.5px; font-weight: 600; font-family: Manrope; color: #33445e; background: #f8fafc; outline: none; cursor: pointer">
                            <option value="">Any Price</option>
                            <option v-for="(r, i) in priceRanges" :key="r.label" :value="i">{{ r.label }}</option>
                        </select>
                    </div>

                    <button class="scp2" style="width: 100%; margin-top: 22px; display: inline-flex; align-items: center; gap: 9px; justify-content: center; height: 52px; font-size: 15px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); border: none; border-radius: 13px; cursor: pointer; box-shadow: rgba(224, 31, 38, 0.32) 0 12px 28px; transition: transform 0.18s" @click="submit">
                        <span style="width: 15px; height: 15px; border: 2px solid #fff; border-radius: 50%; position: relative; display: inline-block"><span style="position: absolute; width: 6px; height: 2px; background: #fff; transform: rotate(45deg); bottom: -2px; right: -3px; border-radius: 2px"></span></span>
                        Search Vehicles
                    </button>

                    <div style="display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f3f7">
                        <span style="font-size: 12px; font-weight: 800; color: #9aa8bd; letter-spacing: 0.03em">Popular:</span>
                        <button v-for="p in popular" :key="p" class="scp5" style="font-size: 12.5px; font-weight: 700; color: #33445e; background: #f4f6f9; border: 1px solid #eef1f6; padding: 7px 13px; border-radius: 100px; transition: 0.16s; cursor: pointer" @click="searchPopular(p)">{{ p }}</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
