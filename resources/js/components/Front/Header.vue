<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const header = computed(() => page.props.headerData || { total: 0, addedToday: 0, categories: [] });
const currentUrl = computed(() => page.url);

// Signed-in user (shared as auth.user). Admin/editor also get a dashboard link.
const user = computed(() => page.props.auth?.user || null);
const canAdmin = computed(() => ['admin', 'editor'].includes(user.value?.role));
const acctOpen = ref(false);
const logout = () => {
    acctOpen.value = false;
    router.post('/logout');
};

const menuOpen = ref(false);
const search = ref('');
const searching = ref(false);

const submitSearch = () => {
    const term = search.value.trim();
    if (!term || searching.value) return;
    menuOpen.value = false;
    searching.value = true;
    router.get('/inventory', { type: 'search', search: term }, { onFinish: () => (searching.value = false) });
};

// Visitor's country like the design intended (geo-detected, cached a day,
// Hong Kong HQ as the fallback)
const visitorCountry = ref('Hong Kong');
onMounted(async () => {
    try {
        const cached = JSON.parse(localStorage.getItem('sm_geo') || 'null');
        if (cached && Date.now() - cached.at < 86400000) {
            visitorCountry.value = cached.country;
            return;
        }
        const res = await fetch('https://api.country.is/');
        const data = await res.json();
        if (data?.country) {
            const name = new Intl.DisplayNames(['en'], { type: 'region' }).of(data.country) || data.country;
            visitorCountry.value = name;
            localStorage.setItem('sm_geo', JSON.stringify({ country: name, at: Date.now() }));
        }
    } catch {
        /* keep fallback */
    }
});

// Language switcher driving the hidden Google Translate engine via its cookie
const languages = [
    { code: 'en', label: 'English' },
    { code: 'zh-CN', label: '简体中文' },
    { code: 'zh-TW', label: '繁體中文' },
    { code: 'ja', label: '日本語' },
    { code: 'fr', label: 'Français' },
    { code: 'es', label: 'Español' },
    { code: 'de', label: 'Deutsch' },
    { code: 'pt', label: 'Português' },
    { code: 'ru', label: 'Русский' },
    { code: 'ar', label: 'العربية' },
    { code: 'sw', label: 'Kiswahili' },
];
const langOpen = ref(false);
const currentLang = ref('English');

const readLangCookie = () => {
    const m = document.cookie.match(/googtrans=\/en\/([a-zA-Z-]+)/);
    const code = m ? m[1] : 'en';
    currentLang.value = languages.find((l) => l.code === code)?.label || 'English';
};

const setLanguage = async (code) => {
    langOpen.value = false;
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    const writeCookie = () => {
        const host = location.hostname;
        if (code === 'en') {
            for (const domain of ['', `; domain=${host}`, `; domain=.${host}`]) {
                document.cookie = `googtrans=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT${domain}`;
            }
        } else {
            document.cookie = `googtrans=/en/${code}; path=/`;
            document.cookie = `googtrans=/en/${code}; path=/; domain=.${host}`;
        }
    };

    // Proof the widget actually processed the switch: it toggles the
    // translated-* class on <html> and rewrites its own googtrans cookie.
    const applied = () => {
        const translated = /translated-(ltr|rtl)/.test(document.documentElement.className);
        return code === 'en' ? !translated : translated && document.cookie.includes(`/en/${code}`);
    };

    // find the hidden select (widget may still be booting)
    let combo = null;
    for (let i = 0; i < 30 && !combo; i++) {
        combo = document.querySelector('.goog-te-combo');
        if (!combo) await sleep(100);
    }

    if (combo) {
        for (let attempt = 0; attempt < 2 && !applied(); attempt++) {
            combo.value = code;
            combo.dispatchEvent(new Event('change'));
            for (let i = 0; i < 20 && !applied(); i++) await sleep(150);
        }
        if (applied()) {
            writeCookie();
            currentLang.value = languages.find((l) => l.code === code)?.label || 'English';
            return;
        }
    }

    // widget missing or unresponsive: cookie + reload always applies
    writeCookie();
    location.reload();
};

const onDocClick = (e) => {
    if (!e.target.closest('.sm-langwrap')) langOpen.value = false;
    if (!e.target.closest('.sm-acctwrap')) acctOpen.value = false;
};

// Live clocks for the sourcing markets ("4:12 AM" per the design)
const timeIn = (tz) => new Date().toLocaleTimeString('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: true });
const chinaTime = ref(timeIn('Asia/Shanghai'));
const japanTime = ref(timeIn('Asia/Tokyo'));
let clockTimer = null;
onMounted(() => {
    readLangCookie();
    document.addEventListener('click', onDocClick);
    clockTimer = setInterval(() => {
        chinaTime.value = timeIn('Asia/Shanghai');
        japanTime.value = timeIn('Asia/Tokyo');
    }, 30000);
});
onBeforeUnmount(() => {
    clearInterval(clockTimer);
    document.removeEventListener('click', onDocClick);
});

const fmt = (n) => Number(n || 0).toLocaleString();

const catHref = (c) => `/inventory?category=${c.id}`;

// One tab is always active like the design. 'All' owns the inventory page
// (and everywhere else); a category tab lights up only when the URL's
// category param is exactly that single category.
const catTabs = computed(() => [
    { id: '__all', label: 'All', href: '/inventory' },
    ...(header.value.categories || []).map((c) => ({ id: c.id, label: c.label, href: catHref(c) })),
]);
const urlCategoryParam = computed(() => {
    try {
        return new URL(currentUrl.value, window.location.origin).searchParams.get('category') ?? '';
    } catch {
        return '';
    }
});
const activeCatId = computed(() => {
    const hit = (header.value.categories || []).find((c) => urlCategoryParam.value === String(c.id));
    return hit ? hit.id : '__all';
});

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
                <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 42px; display: flex; align-items: center; justify-content: space-between; gap: 20px">
                    <div style="display: flex; align-items: center; gap: 20px">
                        <div style="font-size: 13px; font-weight: 500; color: #7d8ea8; white-space: nowrap; letter-spacing: 0.01em">
                            In stock <span style="color: #fff; font-weight: 700; margin-left: 3px">{{ fmt(header.total) }}</span>
                        </div>
                        <div class="sm-util-added" style="width: 3px; height: 3px; border-radius: 50%; background: rgba(255, 255, 255, 0.25)"></div>
                        <div class="sm-util-added" style="font-size: 13px; font-weight: 500; color: #7d8ea8; white-space: nowrap; letter-spacing: 0.01em">
                            Added today <span style="color: #fff; font-weight: 700; margin-left: 3px">{{ fmt(header.addedToday) }}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 20px">
                        <div class="sm-util-time" style="font-size: 13px; font-weight: 500; color: #7d8ea8; white-space: nowrap">
                            China <span style="color: #c2cfe2; font-weight: 600; margin-left: 2px; font-variant-numeric: tabular-nums">{{ chinaTime }}</span>
                        </div>
                        <div class="sm-util-time" style="font-size: 13px; font-weight: 500; color: #7d8ea8; white-space: nowrap">
                            Japan <span style="color: #c2cfe2; font-weight: 600; margin-left: 2px; font-variant-numeric: tabular-nums">{{ japanTime }}</span>
                        </div>
                        <div class="sm-util-time" style="width: 1px; height: 13px; background: rgba(255, 255, 255, 0.14)"></div>
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #c2cfe2; white-space: nowrap">
                            <svg width="11" height="13" viewBox="0 0 10 13" fill="none"><path d="M5 1a4 4 0 0 1 4 4c0 2.9-4 7-4 7S1 7.9 1 5a4 4 0 0 1 4-4z" stroke="currentColor" stroke-width="1.3" /><circle cx="5" cy="5" r="1.4" fill="currentColor" /></svg>
                            {{ visitorCountry }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- main row -->
            <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 104px; display: flex; align-items: center; gap: 36px">
                <Link href="/" style="display: flex; align-items: center; flex-shrink: 0">
                    <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 80px; width: auto; object-fit: contain" />
                </Link>

                <div class="sm-headsearch" style="flex: 1; position: relative; max-width: 620px; margin: 0 auto">
                    <svg style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); pointer-events: none" width="18" height="18" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5.2" stroke="#8494ab" stroke-width="1.6" /><path d="M11 11l3.4 3.4" stroke="#8494ab" stroke-width="1.6" stroke-linecap="round" /></svg>
                    <input
                        v-model="search"
                        placeholder="Search by make, model or type"
                        style="width: 100%; height: 54px; border: none; border-radius: 12px; padding: 0 124px 0 47px; font-size: 15.5px; font-weight: 500; font-family: Manrope; color: #0b1e3b; background: #fff; outline: none; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), inset 0 0 0 1px rgba(11, 30, 59, 0.06)"
                        @keyup.enter="submitSearch"
                    />
                    <button
                        class="scp6"
                        :disabled="searching"
                        style="position: absolute; right: 6px; top: 6px; height: 42px; padding: 0 22px; font-size: 15px; font-weight: 700; font-family: Manrope; color: #fff; background: #e01f26; border: none; border-radius: 9px; cursor: pointer; transition: background 0.15s"
                        @click="submitSearch"
                    >{{ searching ? 'Searching…' : 'Search' }}</button>
                </div>

                <div class="sm-headright" style="display: flex; align-items: center; gap: 22px; flex-shrink: 0">
                    <Link href="/contact-us" class="sm-helplink" style="display: flex; align-items: center; gap: 10px; white-space: nowrap; transition: opacity 0.15s; text-decoration: none">
                        <svg width="22" height="22" viewBox="0 0 20 20" fill="none"><path d="M4 12v-2a6 6 0 0 1 12 0v2" stroke="#c2cfe2" stroke-width="1.6" stroke-linecap="round" /><rect x="2.6" y="11" width="3.4" height="5" rx="1.6" stroke="#c2cfe2" stroke-width="1.5" /><rect x="14" y="11" width="3.4" height="5" rx="1.6" stroke="#c2cfe2" stroke-width="1.5" /><path d="M16 16v.6a2 2 0 0 1-2 2h-2.4" stroke="#c2cfe2" stroke-width="1.5" stroke-linecap="round" /></svg>
                        <span style="line-height: 1.3">
                            <span style="display: block; font-size: 12px; font-weight: 500; color: #7d8ea8">Need help?</span>
                            <span style="display: block; font-size: 14.5px; font-weight: 700; color: #fff">Talk to sales</span>
                        </span>
                    </Link>
                    <!-- Account / Login -->
                    <template v-if="user">
                        <div class="sm-acctwrap sm-headright-acct" style="position: relative; flex-shrink: 0">
                            <button
                                class="scp0"
                                style="display: flex; align-items: center; gap: 9px; padding: 8px 14px 8px 9px; border-radius: 11px; border: 1px solid rgba(255, 255, 255, 0.16); background: transparent; cursor: pointer; font-family: Manrope; white-space: nowrap"
                                @click="acctOpen = !acctOpen"
                            >
                                <span style="width: 30px; height: 30px; border-radius: 50%; background: #e01f26; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; text-transform: uppercase">{{ (user.name || 'U').charAt(0) }}</span>
                                <span style="font-size: 14px; font-weight: 700; color: #fff; max-width: 110px; overflow: hidden; text-overflow: ellipsis">{{ (user.name || 'Account').split(' ')[0] }}</span>
                                <svg width="8" height="5" viewBox="0 0 8 5" fill="none" :style="{ transform: acctOpen ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }"><path d="M1 1l3 3 3-3" stroke="#c2cfe2" stroke-width="1.4" stroke-linecap="round" /></svg>
                            </button>
                            <div
                                v-if="acctOpen"
                                style="position: absolute; top: calc(100% + 8px); right: 0; z-index: 60; background: #fff; border: 1px solid #e6eaf0; border-radius: 13px; box-shadow: rgba(8, 23, 48, 0.18) 0 18px 40px; padding: 6px; min-width: 190px"
                            >
                                <div style="padding: 10px 12px 8px; border-bottom: 1px solid #f1f3f7; margin-bottom: 4px">
                                    <div style="font-size: 13.5px; font-weight: 800; color: #0b1e3b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ user.name }}</div>
                                    <div style="font-size: 12px; font-weight: 600; color: #8494ab; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ user.email }}</div>
                                </div>
                                <Link v-if="canAdmin" href="/admin/dashboard" class="scp0" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 9px; font-size: 14px; font-weight: 700; color: #33445e; text-decoration: none; font-family: Manrope" @click="acctOpen = false">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4a5a72" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1" /><rect x="14" y="3" width="7" height="5" rx="1" /><rect x="14" y="12" width="7" height="9" rx="1" /><rect x="3" y="16" width="7" height="5" rx="1" /></svg>
                                    Dashboard
                                </Link>
                                <button class="scp0" style="display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; border-radius: 9px; font-size: 14px; font-weight: 700; color: #e01f26; background: transparent; border: none; cursor: pointer; text-align: left; font-family: Manrope" @click="logout">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                                    Log out
                                </button>
                            </div>
                        </div>
                    </template>
                    <Link
                        v-else
                        href="/login"
                        class="scp0 sm-headright-acct"
                        style="display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, 0.18); padding: 13px 22px; border-radius: 11px; white-space: nowrap; text-decoration: none; flex-shrink: 0"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#c2cfe2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                        Login
                    </Link>
                    <Link
                        href="/inventory"
                        class="scp6 sm-headcta"
                        style="display: inline-flex; align-items: center; font-size: 15px; font-weight: 700; color: #fff; background: #e01f26; padding: 15px 26px; border-radius: 11px; white-space: nowrap; transition: background 0.15s; text-decoration: none"
                    >Browse Inventory</Link>
                    <button
                        class="sm-burger"
                        style="display: none; width: 46px; height: 46px; border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 11px; background: transparent; flex-direction: column; gap: 4px; align-items: center; justify-content: center; cursor: pointer"
                        @click="menuOpen = !menuOpen"
                    >
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                        <span style="width: 17px; height: 1.8px; background: #fff; border-radius: 2px"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Category strip: distinct white surface, active tab = navy block -->
        <div style="background: #fff; border-bottom: 1px solid #f1f3f7">
            <div class="sm-catnav" style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 56px; display: flex; align-items: stretch; gap: 15px">
                <Link
                    v-for="c in catTabs"
                    :key="c.id"
                    :href="c.href"
                    :class="c.id === activeCatId ? '' : 'scp7'"
                    :style="c.id === activeCatId
                        ? 'display:inline-flex;align-items:center;padding:0 22px;font-size:15px;font-weight:700;color:#fff;background:#0b1e3b;white-space:nowrap;text-decoration:none'
                        : 'display:inline-flex;align-items:center;padding:0 4px;font-size:15px;font-weight:600;color:#4a5a72;white-space:nowrap;transition:color .15s;text-decoration:none'"
                >{{ c.label }}</Link>
                <div class="sm-catright sm-langwrap" style="margin-left: auto; display: flex; align-items: center; gap: 8px; position: relative">
                    <button
                        class="scp0"
                        style="display: flex; align-items: center; gap: 7px; font-size: 14px; font-weight: 600; color: #4a5a72; white-space: nowrap; cursor: pointer; padding: 8px 13px; border-radius: 8px; transition: background 0.15s; border: none; background: transparent; font-family: Manrope"
                        @click="langOpen = !langOpen"
                    >
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.4" stroke="#4a5a72" stroke-width="1.3" /><path d="M1.6 8h12.8M8 1.6c1.8 1.7 2.7 4 2.7 6.4S9.8 12.7 8 14.4C6.2 12.7 5.3 10.4 5.3 8S6.2 3.3 8 1.6z" stroke="#4a5a72" stroke-width="1.1" /></svg>
                        <span class="notranslate">{{ currentLang }}</span>
                        <svg width="8" height="5" viewBox="0 0 8 5" fill="none" :style="{ transform: langOpen ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }"><path d="M1 1l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" /></svg>
                    </button>
                    <div
                        v-if="langOpen"
                        class="notranslate"
                        style="position: absolute; top: calc(100% + 6px); right: 0; z-index: 60; background: #fff; border: 1px solid #e6eaf0; border-radius: 13px; box-shadow: rgba(8, 23, 48, 0.14) 0 18px 40px; padding: 6px; min-width: 172px"
                    >
                        <button
                            v-for="l in languages"
                            :key="l.code"
                            class="scp0"
                            :style="{
                                display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%', gap: '12px',
                                fontSize: '14px', fontWeight: currentLang === l.label ? 800 : 600, fontFamily: 'Manrope',
                                color: currentLang === l.label ? '#0b1e3b' : '#4a5a72',
                                padding: '9px 12px', borderRadius: '9px', border: 'none', background: 'transparent',
                                cursor: 'pointer', textAlign: 'left', transition: 'background .15s',
                            }"
                            @click="setLanguage(l.code)"
                        >
                            {{ l.label }}
                            <svg v-if="currentLang === l.label" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-if="menuOpen" style="border-top: 1px solid rgba(255, 255, 255, 0.08); background: #0b1e3b; padding: 14px 24px 20px; max-height: calc(100vh - 150px); overflow-y: auto; -webkit-overflow-scrolling: touch">
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

            <!-- Account / Login (mobile) -->
            <template v-if="user">
                <Link v-if="canAdmin" href="/admin/dashboard" style="display: block; text-align: center; margin-top: 10px; font-weight: 700; color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, 0.2); padding: 13px; border-radius: 10px; text-decoration: none" @click="menuOpen = false">Dashboard</Link>
                <button style="display: block; width: 100%; text-align: center; margin-top: 10px; font-weight: 700; color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, 0.2); padding: 13px; border-radius: 10px; cursor: pointer; font-family: Manrope" @click="menuOpen = false; logout()">Log out ({{ (user.name || 'Account').split(' ')[0] }})</button>
            </template>
            <Link v-else href="/login" style="display: block; text-align: center; margin-top: 10px; font-weight: 700; color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, 0.2); padding: 13px; border-radius: 10px; text-decoration: none" @click="menuOpen = false">Login</Link>
        </div>
    </header>
</template>
