<style>
/* Hide Google Translate toolbar */
body > .skiptranslate {
    display: none;
}
.goog-te-banner-frame.skiptranslate {
    display: none !important;
}
body {
    top: 0px !important;
}
@media print {
    #google_translate_element {
        display: none;
    }
}
</style>

<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const scrolled = ref(false);
const menuOpen = ref(false);

const navLinks = [
    { label: 'Home', href: '/' },
    { label: 'Inventory', href: '/inventory' },
    { label: 'About Us', href: '/about-us' },
    { label: 'How It Works', href: '/how-to-buy' },
    { label: 'Blog', href: '/blogs' },
    { label: 'Contact', href: '/contact-us' },
];

const onScroll = () => (scrolled.value = window.scrollY > 12);
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header
        class="sm-body"
        :style="{
            position: 'sticky', top: 0, zIndex: 50,
            background: scrolled ? 'rgba(255,255,255,0.92)' : '#ffffff',
            backdropFilter: 'blur(12px)',
            borderBottom: scrolled ? '1px solid #E6EAF0' : '1px solid #F1F3F7',
            boxShadow: scrolled ? '0 6px 24px rgba(8,23,48,.07)' : 'none',
            transition: 'box-shadow .2s, background .2s',
        }"
    >
        <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 24px">
            <Link href="/" style="display: flex; align-items: center; flex-shrink: 0">
                <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 54px; width: auto; object-fit: contain" />
            </Link>

            <nav class="sm-desknav" style="display: flex; align-items: center; gap: 4px">
                <Link
                    v-for="l in navLinks"
                    :key="l.label"
                    :href="l.href"
                    class="scp0"
                    style="font-size: 14.5px; font-weight: 600; color: #33445e; padding: 9px 15px; border-radius: 9px; white-space: nowrap; transition: 0.18s"
                >{{ l.label }}</Link>
            </nav>

            <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0">
                <Link href="/query-form" class="sm-selllink" style="font-size: 14px; font-weight: 700; color: #0b1e3b; padding: 8px 4px">Sell Your Vehicle</Link>
                <Link
                    href="/inventory"
                    class="scp2"
                    style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #fff; background: linear-gradient(150deg, #12284a, #0b1e3b); background: linear-gradient(150deg, #12284a, #0b1e3b); padding: 12px 20px; border-radius: 11px; box-shadow: rgba(11, 30, 59, 0.28) 0 6px 18px; transition: transform 0.18s"
                >
                    Browse Inventory
                    <span style="width: 6px; height: 6px; border-top: 2px solid #e01f26; border-right: 2px solid #e01f26; transform: rotate(45deg); display: inline-block"></span>
                </Link>
                <button
                    class="sm-burger"
                    style="display: none; width: 44px; height: 44px; border: 1px solid #e6eaf0; border-radius: 11px; background: #fff; flex-direction: column; gap: 4px; align-items: center; justify-content: center; cursor: pointer"
                    @click="menuOpen = !menuOpen"
                >
                    <span style="width: 18px; height: 2px; background: #0b1e3b; border-radius: 2px"></span>
                    <span style="width: 18px; height: 2px; background: #0b1e3b; border-radius: 2px"></span>
                    <span style="width: 18px; height: 2px; background: #0b1e3b; border-radius: 2px"></span>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div v-if="menuOpen" style="border-top: 1px solid #f1f3f7; background: #fff; padding: 12px 24px 18px; display: flex; flex-direction: column; gap: 2px">
            <Link
                v-for="l in navLinks"
                :key="l.label"
                :href="l.href"
                style="font-size: 15px; font-weight: 600; color: #33445e; padding: 11px 8px; border-radius: 9px"
                @click="menuOpen = false"
            >{{ l.label }}</Link>
            <Link href="/query-form" style="font-size: 15px; font-weight: 700; color: #0b1e3b; padding: 11px 8px" @click="menuOpen = false">Sell Your Vehicle</Link>
        </div>
    </header>
</template>
