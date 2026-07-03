<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const header = computed(() => page.props.headerData || { total: 0, brands: 0, categories: [] });
const currentUrl = computed(() => page.url);

const menuOpen = ref(false);
const search = ref('');

const submitSearch = () => {
    const term = search.value.trim();
    if (!term) return;
    menuOpen.value = false;
    router.get('/inventory', { type: 'search', search: term });
};

// Live clocks for the sourcing markets
const timeIn = (tz) => new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: tz }).format(new Date());
const chinaTime = ref(timeIn('Asia/Shanghai'));
const japanTime = ref(timeIn('Asia/Tokyo'));
let clockTimer = null;
onMounted(() => {
    clockTimer = setInterval(() => {
        chinaTime.value = timeIn('Asia/Shanghai');
        japanTime.value = timeIn('Asia/Tokyo');
    }, 30000);
});
onBeforeUnmount(() => clearInterval(clockTimer));

const fmt = (n) => Number(n || 0).toLocaleString();

const catHref = (c) => `/inventory?category=${c.id}`;
const isActiveCat = (c) => currentUrl.value.includes(`category=${c.id}`);
const isAllActive = computed(() => currentUrl.value === '/inventory' || currentUrl.value.startsWith('/inventory?page'));

const pageLinks = [
    { label: 'About Us', href: '/about-us' },
    { label: 'How It Works', href: '/how-to-buy' },
    { label: 'Blog', href: '/blogs' },
    { label: 'Contact', href: '/contact-us' },
    { label: 'Sell Your Vehicle', href: '/query-form' },
];
</script>

<template>
    <header class="sm-body" style="position: sticky; top: 0; z-index: 50; box-shadow: 0 6px 24px rgba(8, 23, 48, 0.09)">
        <!-- Navy block: utility + main bar share one surface -->
        <div style="background: #0b1e3b">
            <!-- utility row -->
            <div style="border-bottom: 1px solid rgba(255, 255, 255, 0.08)">
                <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 38px; display: flex; align-items: center; justify-content: space-between; gap: 20px">
                    <div style="display: flex; align-items: center; gap: 20px">
                        <div style="font-size: 12px; font-weight: 500; color: #7d8ea8; white-space: nowrap; letter-spacing: 0.01em">
                            In stock <span style="color: #fff; font-weight: 700; margin-left: 3px">{{ fmt(header.total) }}</span>
                        </div>
                        <div class="sm-util-added" style="width: 3px; height: 3px; border-radius: 50%; background: rgba(255, 255, 255, 0.25)"></div>
                        <div class="sm-util-added" style="font-size: 12px; font-weight: 500; color: #7d8ea8; white-space: nowrap; letter-spacing: 0.01em">
                            Brands <span style="color: #fff; font-weight: 700; margin-left: 3px">{{ fmt(header.brands) }}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 20px">
                        <div class="sm-util-time" style="font-size: 12px; font-weight: 500; color: #7d8ea8; white-space: nowrap">
                            China <span style="color: #c2cfe2; font-weight: 600; margin-left: 2px; font-variant-numeric: tabular-nums">{{ chinaTime }}</span>
                        </div>
                        <div class="sm-util-time" style="font-size: 12px; font-weight: 500; color: #7d8ea8; white-space: nowrap">
                            Japan <span style="color: #c2cfe2; font-weight: 600; margin-left: 2px; font-variant-numeric: tabular-nums">{{ japanTime }}</span>
                        </div>
                        <div class="sm-util-time" style="width: 1px; height: 13px; background: rgba(255, 255, 255, 0.14)"></div>
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #c2cfe2; white-space: nowrap">
                            <svg width="10" height="12" viewBox="0 0 10 13" fill="none"><path d="M5 1a4 4 0 0 1 4 4c0 2.9-4 7-4 7S1 7.9 1 5a4 4 0 0 1 4-4z" stroke="currentColor" stroke-width="1.3" /><circle cx="5" cy="5" r="1.4" fill="currentColor" /></svg>
                            Hong Kong
                        </div>
                    </div>
                </div>
            </div>

            <!-- main row -->
            <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 82px; display: flex; align-items: center; gap: 36px">
                <Link href="/" style="display: flex; align-items: center; flex-shrink: 0">
                    <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 62px; width: auto; object-fit: contain" />
                </Link>

                <div class="sm-headsearch" style="flex: 1; position: relative; max-width: 590px; margin: 0 auto">
                    <svg style="position: absolute; left: 17px; top: 50%; transform: translateY(-50%); pointer-events: none" width="17" height="17" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5.2" stroke="#8494ab" stroke-width="1.6" /><path d="M11 11l3.4 3.4" stroke="#8494ab" stroke-width="1.6" stroke-linecap="round" /></svg>
                    <input
                        v-model="search"
                        placeholder="Search by make, model or type"
                        style="width: 100%; height: 48px; border: none; border-radius: 11px; padding: 0 104px 0 44px; font-size: 14px; font-weight: 500; font-family: Manrope; color: #0b1e3b; background: #fff; outline: none; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), inset 0 0 0 1px rgba(11, 30, 59, 0.06)"
                        @keyup.enter="submitSearch"
                    />
                    <button
                        class="scp6"
                        style="position: absolute; right: 6px; top: 6px; height: 36px; padding: 0 19px; font-size: 13.5px; font-weight: 700; font-family: Manrope; color: #fff; background: #e01f26; border: none; border-radius: 8px; cursor: pointer; transition: background 0.15s"
                        @click="submitSearch"
                    >Search</button>
                </div>

                <div style="display: flex; align-items: center; gap: 22px; flex-shrink: 0">
                    <Link href="/contact-us" class="sm-helplink" style="display: flex; align-items: center; gap: 9px; white-space: nowrap; transition: opacity 0.15s; text-decoration: none">
                        <svg width="19" height="19" viewBox="0 0 20 20" fill="none"><path d="M4 12v-2a6 6 0 0 1 12 0v2" stroke="#c2cfe2" stroke-width="1.6" stroke-linecap="round" /><rect x="2.6" y="11" width="3.4" height="5" rx="1.6" stroke="#c2cfe2" stroke-width="1.5" /><rect x="14" y="11" width="3.4" height="5" rx="1.6" stroke="#c2cfe2" stroke-width="1.5" /><path d="M16 16v.6a2 2 0 0 1-2 2h-2.4" stroke="#c2cfe2" stroke-width="1.5" stroke-linecap="round" /></svg>
                        <span style="line-height: 1.25">
                            <span style="display: block; font-size: 11px; font-weight: 500; color: #7d8ea8">Need help?</span>
                            <span style="display: block; font-size: 13px; font-weight: 700; color: #fff">Talk to sales</span>
                        </span>
                    </Link>
                    <Link
                        href="/inventory"
                        class="scp6 sm-headcta"
                        style="display: inline-flex; align-items: center; font-size: 14px; font-weight: 700; color: #fff; background: #e01f26; padding: 13px 22px; border-radius: 10px; white-space: nowrap; transition: background 0.15s; text-decoration: none"
                    >Browse Inventory</Link>
                    <button
                        class="sm-burger"
                        style="display: none; width: 42px; height: 42px; border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 10px; background: transparent; flex-direction: column; gap: 4px; align-items: center; justify-content: center; cursor: pointer"
                        @click="menuOpen = !menuOpen"
                    >
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Category strip: distinct white surface -->
        <div style="background: #fff; border-bottom: 1px solid #f1f3f7">
            <div class="sm-catnav" style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 50px; display: flex; align-items: stretch; gap: 28px">
                <Link
                    href="/inventory"
                    :style="{
                        display: 'inline-flex', alignItems: 'center', fontSize: '13.5px', whiteSpace: 'nowrap', textDecoration: 'none',
                        fontWeight: isAllActive ? 800 : 600,
                        color: isAllActive ? '#0b1e3b' : '#4a5a72',
                        boxShadow: isAllActive ? 'inset 0 -2px 0 #e01f26' : 'none',
                        transition: 'color 0.15s',
                    }"
                >All Vehicles</Link>
                <Link
                    v-for="c in header.categories"
                    :key="c.id"
                    :href="catHref(c)"
                    class="scp7"
                    :style="{
                        display: 'inline-flex', alignItems: 'center', fontSize: '13.5px', whiteSpace: 'nowrap', textDecoration: 'none',
                        fontWeight: isActiveCat(c) ? 800 : 600,
                        color: isActiveCat(c) ? '#0b1e3b' : '#4a5a72',
                        boxShadow: isActiveCat(c) ? 'inset 0 -2px 0 #e01f26' : 'none',
                        transition: 'color 0.15s',
                    }"
                >{{ c.label }}</Link>
                <div style="margin-left: auto; display: flex; align-items: center; gap: 8px">
                    <div style="display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; color: #4a5a72; white-space: nowrap; padding: 7px 12px; border-radius: 8px">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.4" stroke="#4a5a72" stroke-width="1.3" /><path d="M1.6 8h12.8M8 1.6c1.8 1.7 2.7 4 2.7 6.4S9.8 12.7 8 14.4C6.2 12.7 5.3 10.4 5.3 8S6.2 3.3 8 1.6z" stroke="#4a5a72" stroke-width="1.1" /></svg>
                        English
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-if="menuOpen" style="border-top: 1px solid rgba(255, 255, 255, 0.08); background: #0b1e3b; padding: 14px 24px 20px">
            <div style="position: relative; margin-bottom: 14px">
                <input
                    v-model="search"
                    placeholder="Search make, model, type…"
                    style="width: 100%; height: 46px; border: none; border-radius: 10px; padding: 0 16px; font-size: 15px; font-weight: 500; font-family: Manrope; color: #0b1e3b; background: #fff; outline: none"
                    @keyup.enter="submitSearch"
                />
            </div>
            <Link
                v-for="c in header.categories"
                :key="c.id"
                :href="catHref(c)"
                style="display: block; font-size: 15px; font-weight: 600; color: #c2cfe2; padding: 12px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); text-decoration: none"
                @click="menuOpen = false"
            >{{ c.label }}</Link>
            <Link
                v-for="l in pageLinks"
                :key="l.label"
                :href="l.href"
                style="display: block; font-size: 15px; font-weight: 600; color: #8494ab; padding: 12px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); text-decoration: none"
                @click="menuOpen = false"
            >{{ l.label }}</Link>
            <Link href="/inventory" style="display: block; text-align: center; margin-top: 16px; font-weight: 700; color: #fff; background: #e01f26; padding: 13px; border-radius: 10px; text-decoration: none" @click="menuOpen = false">Browse Inventory</Link>
        </div>
    </header>
</template>
