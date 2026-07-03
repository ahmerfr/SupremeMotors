<script setup>
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const email = ref('');
const btnLabel = ref('Subscribe →');
const error = ref('');
const busy = ref(false);

const subscribe = async () => {
    if (!email.value.trim() || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await axios.post('/newsletter/subscribe', { email: email.value });
        btnLabel.value = 'Subscribed ✓';
        email.value = '';
        setTimeout(() => (btnLabel.value = 'Subscribe →'), 2500);
    } catch (e) {
        error.value = e.response?.data?.message || 'Something went wrong. Try again.';
    } finally {
        busy.value = false;
    }
};

const footCols = [
    {
        head: 'Company',
        items: [
            { label: 'About Us', href: '/about-us' },
            { label: 'How It Works', href: '/how-to-buy' },
            { label: 'Our Team', href: '/about-us' },
            { label: 'Careers', href: '/contact-us' },
            { label: 'Contact', href: '/contact-us' },
        ],
    },
    {
        head: 'Quick Links',
        items: [
            { label: 'Inventory', href: '/inventory' },
            { label: 'Sell Your Vehicle', href: '/query-form' },
            { label: 'Pricing Updates', href: '/blogs' },
            { label: 'Blog', href: '/blogs' },
            { label: 'FAQs', href: '/faqs' },
        ],
    },
    {
        head: 'Our Brands',
        items: ['Toyota', 'Porsche', 'Audi', 'BMW', 'Volkswagen'].map((b) => ({
            label: b,
            href: `/inventory?type=search&search=${b}`,
        })),
    },
    {
        head: 'Vehicle Types',
        items: [
            { label: 'Sedan', href: '/inventory?type=search&body_style=Sedan' },
            { label: 'SUV', href: '/inventory?type=search&body_style=SUV' },
            { label: 'Hatchback', href: '/inventory?type=search&body_style=Hatchback' },
            { label: 'Hybrid', href: '/inventory?type=search&fuel=Hybrid' },
            { label: 'Truck', href: '/inventory?type=search&body_style=Truck' },
        ],
    },
];

const socials = [
    { label: 'f', href: 'https://www.facebook.com/people/Supreme-Motors/61575850660503/', title: 'Facebook' },
    { label: 'in', href: 'https://www.linkedin.com/company/suprememotorsltd/', title: 'LinkedIn' },
    { label: '◎', href: 'https://www.instagram.com/suprememotors.ltd', title: 'Instagram' },
];
</script>

<template>
    <footer class="sm-body sm-footgap" style="background: linear-gradient(180deg, #0b1e3b, #081730)">
        <!-- Stay updated strip (kept per user requirement; not in the design mock) -->
        <div style="border-bottom: 1px solid rgba(255, 255, 255, 0.09)">
            <div class="sm-footnews" style="max-width: 1180px; margin: 0 auto; padding: 40px 24px; display: flex; align-items: center; justify-content: space-between; gap: 28px; flex-wrap: wrap">
                <div>
                    <div style="font-family: Archivo; font-weight: 800; font-size: 24px; letter-spacing: -0.015em; color: #fff">Stay updated</div>
                    <p style="font-size: 14px; font-weight: 500; color: #93a3bd; margin-top: 7px">Subscribe to receive exclusive offers and new arrivals</p>
                </div>
                <div>
                    <div style="display: flex; gap: 0; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.13); border-radius: 14px; padding: 5px; min-width: 400px">
                        <input
                            v-model="email"
                            type="email"
                            placeholder="Enter your email"
                            style="flex: 1; background: transparent; border: none; outline: none; padding: 12px 16px; font-size: 14.5px; font-weight: 600; font-family: Manrope; color: #fff; min-width: 0"
                            @keyup.enter="subscribe"
                        />
                        <button
                            class="scp2"
                            :disabled="busy"
                            style="flex: 0 0 auto; font-size: 14px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); border: none; border-radius: 10px; padding: 12px 22px; cursor: pointer; transition: transform 0.18s"
                            @click="subscribe"
                        >{{ btnLabel }}</button>
                    </div>
                    <div v-if="error" style="font-size: 12.5px; color: #ff8085; font-weight: 700; margin-top: 9px">{{ error }}</div>
                </div>
            </div>
        </div>

        <div class="sm-footgrid" style="max-width: 1180px; margin: 0 auto; padding: 64px 24px 48px; display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr 1fr; gap: 40px">
            <div>
                <Link href="/" style="display: inline-flex; align-items: center">
                    <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 62px; width: auto; object-fit: contain" />
                </Link>
                <p style="font-size: 14px; line-height: 1.65; color: #93a3bd; font-weight: 500; margin-top: 20px; max-width: 280px">
                    A trusted vehicle marketplace helping buyers find quality cars with clarity, care and confidence.
                </p>
                <div style="display: flex; gap: 10px; margin-top: 22px">
                    <a
                        v-for="s in socials"
                        :key="s.title"
                        :href="s.href"
                        :title="s.title"
                        :aria-label="s.title"
                        target="_blank"
                        rel="noopener"
                        class="scpe"
                        style="width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center; color: #cdd8e8; font-size: 16px; font-weight: 800; transition: all 0.18s; text-decoration: none"
                    >{{ s.label }}</a>
                </div>
            </div>

            <div v-for="col in footCols" :key="col.head">
                <div style="font-family: Archivo; font-weight: 700; font-size: 14px; color: #fff; letter-spacing: 0.02em">{{ col.head }}</div>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 18px">
                    <Link
                        v-for="item in col.items"
                        :key="item.label"
                        :href="item.href"
                        class="scpf"
                        style="font-size: 13.5px; font-weight: 600; color: #93a3bd; transition: color 0.16s; text-decoration: none"
                    >{{ item.label }}</Link>
                </div>
            </div>
        </div>

        <div style="border-top: 1px solid rgba(255, 255, 255, 0.09)">
            <div style="max-width: 1180px; margin: 0 auto; padding: 22px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap">
                <div style="font-size: 13px; font-weight: 600; color: #7d8ea8">© {{ new Date().getFullYear() }} Supreme Motors Ltd. All rights reserved.</div>
                <div style="display: flex; gap: 24px">
                    <Link href="/terms-condition" class="scpf" style="font-size: 13px; font-weight: 600; color: #93a3bd; text-decoration: none">Terms &amp; Conditions</Link>
                    <Link href="/terms-condition" class="scpf" style="font-size: 13px; font-weight: 600; color: #93a3bd; text-decoration: none">Privacy Policy</Link>
                </div>
            </div>
        </div>
        <div style="height: 5px; background: linear-gradient(90deg, #c8151c, #e01f26, #c8151c)"></div>
    </footer>
</template>
