<script setup>
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const footCols = [
    {
        head: 'Company',
        items: [
            { label: 'About Us', href: '/about-us' },
            { label: 'Blog', href: '/blogs' },
            { label: 'Our Inventory', href: '/inventory' },
            { label: 'FAQs', href: '/faqs' },
            { label: 'Terms', href: '/terms-condition' },
            { label: 'Contact Us', href: '/contact-us' },
        ],
    },
    {
        head: 'Quick Links',
        items: [
            { label: 'Inventory', href: '/inventory' },
            { label: 'About Us', href: '/about-us' },
            { label: 'Bank Details', href: '/bank-details' },
            { label: 'How it Works', href: '/how-to-buy' },
            { label: 'Contact', href: '/contact-us' },
        ],
    },
    {
        head: 'Our Brands',
        items: ['Toyota', 'Porsche', 'Audi', 'BMW', 'Ford', 'Nissan', 'Peugeot', 'Volkswagen'].map((b) => ({
            label: b,
            href: `/inventory?type=search&search=${b}`,
        })),
    },
    {
        head: 'Vehicles Type',
        items: [
            { label: 'Sedan', href: '/inventory?type=search&body_style=Sedan' },
            { label: 'Hatchback', href: '/inventory?type=search&body_style=Hatchback' },
            { label: 'SUV', href: '/inventory?type=search&body_style=SUV' },
            { label: 'Hybrid', href: '/inventory?type=search&fuel=Hybrid' },
            { label: 'Coupe', href: '/inventory?type=search&body_style=Coupe' },
            { label: 'Truck', href: '/inventory?type=search&body_style=Truck' },
            { label: 'Convertible', href: '/inventory?type=search&body_style=Convertible' },
        ],
    },
];

const socials = [
    {
        title: 'Facebook',
        href: 'https://www.facebook.com/people/Supreme-Motors/61575850660503/',
        d: 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 5 3.66 9.13 8.44 9.88v-6.99h-2.54V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99C18.34 21.13 22 17 22 12z',
    },
    {
        title: 'Instagram',
        href: 'https://www.instagram.com/suprememotors.ltd',
        d: 'M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 2.7.27.27 2.69.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95C23.73 2.7 21.31.27 16.95.07 15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84zm0 10.15A3.99 3.99 0 1 1 16 12a3.99 3.99 0 0 1-4 3.99zm6.41-11.85a1.44 1.44 0 1 0 1.44 1.44 1.44 1.44 0 0 0-1.44-1.44z',
    },
    {
        title: 'LinkedIn',
        href: 'https://www.linkedin.com/company/suprememotorsltd/',
        d: 'M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45zM22.23 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.46c.98 0 1.77-.77 1.77-1.72V1.72C24 .77 23.21 0 22.23 0z',
    },
];

const contactRows = [
    {
        title: 'Location',
        lines: ['Unit 1603, 16th Floor, The L. Plaza', "367–375 Queen's Road Central", 'Sheung Wan, Hong Kong'],
        d: 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    },
    {
        title: 'Phone',
        lines: ['+852 5322 1678'],
        href: 'tel:+85253221678',
        d: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z',
    },
    {
        title: 'Email',
        lines: ['info@suprememotors.ltd'],
        href: 'mailto:info@suprememotors.ltd',
        d: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z M22 6l-10 7L2 6',
    },
];

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
</script>

<template>
    <footer class="sm-body" style="margin-top: 104px; background: linear-gradient(#0b1e3b, #081730)">
        <!-- Stay updated strip -->
        <div style="border-bottom: 1px solid rgba(255, 255, 255, 0.09)">
            <div class="sm-footnews" style="max-width: 1280px; margin: 0 auto; padding: 44px 24px; display: flex; align-items: center; justify-content: space-between; gap: 28px; flex-wrap: wrap">
                <div>
                    <div style="font-family: Archivo; font-weight: 800; font-size: 25px; letter-spacing: -0.015em; color: #fff">Stay updated</div>
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

        <div class="sm-footgrid" style="max-width: 1280px; margin: 0 auto; padding: 58px 24px 48px; display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1.4fr; gap: 34px">
            <div>
                <Link href="/" style="display: inline-flex; align-items: center">
                    <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 56px; width: auto; object-fit: contain" />
                </Link>
                <p style="font-size: 14px; line-height: 1.65; color: #93a3bd; font-weight: 500; margin-top: 20px; max-width: 260px">
                    Quality cars, trucks and heavy machinery sourced from Japan and China — with clarity, care and confidence.
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
                        style="width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center; color: #cdd8e8; transition: 0.18s; text-decoration: none"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path :d="s.d" /></svg>
                    </a>
                </div>
            </div>

            <div v-for="col in footCols" :key="col.head">
                <div style="font-family: Archivo; font-weight: 700; font-size: 14px; color: #fff; letter-spacing: 0.02em">{{ col.head }}</div>
                <div style="display: flex; flex-direction: column; gap: 11px; margin-top: 18px">
                    <Link
                        v-for="item in col.items"
                        :key="item.label"
                        :href="item.href"
                        class="scpf"
                        style="font-size: 13.5px; font-weight: 600; color: #93a3bd; transition: color 0.16s"
                    >{{ item.label }}</Link>
                </div>
            </div>

            <div>
                <div style="font-family: Archivo; font-weight: 700; font-size: 14px; color: #fff; letter-spacing: 0.02em">Contact Us</div>
                <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 18px">
                    <div v-for="row in contactRows" :key="row.title" style="display: flex; gap: 12px; align-items: flex-start">
                        <span style="flex: 0 0 auto; width: 36px; height: 36px; border-radius: 11px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ff8085" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="row.d" /></svg>
                        </span>
                        <div style="font-size: 13px; font-weight: 600; color: #93a3bd; line-height: 1.55; padding-top: 2px">
                            <component :is="row.href ? 'a' : 'div'" :href="row.href" class="scpf" style="color: inherit; text-decoration: none; transition: color 0.16s">
                                <div v-for="line in row.lines" :key="line">{{ line }}</div>
                            </component>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="border-top: 1px solid rgba(255, 255, 255, 0.09)">
            <div style="max-width: 1280px; margin: 0 auto; padding: 22px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap">
                <div style="font-size: 13px; font-weight: 600; color: #7d8ea8">© {{ new Date().getFullYear() }} Supreme Motors Ltd. All rights reserved.</div>
                <div style="display: flex; gap: 24px">
                    <Link href="/terms-condition" class="scpf" style="font-size: 13px; font-weight: 600; color: #93a3bd">Terms &amp; Conditions</Link>
                    <Link href="/faqs" class="scpf" style="font-size: 13px; font-weight: 600; color: #93a3bd">FAQs</Link>
                </div>
            </div>
        </div>
        <div style="height: 5px; background: linear-gradient(90deg, #c8151c, #e01f26, #c8151c)"></div>
    </footer>
</template>
