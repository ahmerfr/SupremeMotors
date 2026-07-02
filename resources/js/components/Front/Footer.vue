<script setup>
import { Link } from '@inertiajs/vue3';

const footCols = [
    {
        head: 'Company',
        items: [
            { label: 'About Us', href: '/about-us' },
            { label: 'How It Works', href: '/how-to-buy' },
            { label: 'Customer Reviews', href: '/customer-review' },
            { label: 'Bank Details', href: '/bank-details' },
            { label: 'Contact', href: '/contact-us' },
        ],
    },
    {
        head: 'Quick Links',
        items: [
            { label: 'Inventory', href: '/inventory' },
            { label: 'Sell Your Vehicle', href: '/query-form' },
            { label: 'Customer Support', href: '/customer-support' },
            { label: 'Blog', href: '/blogs' },
            { label: 'FAQs', href: '/faqs' },
        ],
    },
    {
        head: 'Our Brands',
        items: [
            { label: 'Toyota', href: '/inventory?type=search&search=Toyota' },
            { label: 'Shacman', href: '/inventory?type=search&search=Shacman' },
            { label: 'Nissan', href: '/inventory?type=search&search=Nissan' },
            { label: 'Honda', href: '/inventory?type=search&search=Honda' },
            { label: 'Howo', href: '/inventory?type=search&search=Howo' },
        ],
    },
    {
        head: 'Vehicle Types',
        items: [
            { label: 'Sedan', href: '/inventory?type=search&body_style=Sedan' },
            { label: 'SUV', href: '/inventory?type=search&body_style=SUV' },
            { label: 'Hatchback', href: '/inventory?type=search&body_style=Hatchback' },
            { label: 'Wagon', href: '/inventory?type=search&body_style=Wagon' },
            { label: 'Truck', href: '/inventory?type=search&body_style=Truck' },
        ],
    },
];

const socials = [
    { label: 'f', href: 'https://facebook.com', title: 'Facebook' },
    { label: 'in', href: 'https://linkedin.com', title: 'LinkedIn' },
    { label: 'X', href: 'https://x.com', title: 'X' },
    { label: '◎', href: 'https://instagram.com', title: 'Instagram' },
];
</script>

<template>
    <footer class="sm-body" style="margin-top: 104px; background: linear-gradient(#0b1e3b, #081730)">
        <div class="sm-footgrid" style="max-width: 1180px; margin: 0 auto; padding: 64px 24px 48px; display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr 1fr; gap: 40px">
            <div>
                <Link href="/" style="display: inline-flex; align-items: center">
                    <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 56px; width: auto; object-fit: contain" />
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
                        target="_blank"
                        rel="noopener"
                        class="scpe"
                        style="width: 40px; height: 40px; border-radius: 11px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center; color: #cdd8e8; font-size: 16px; font-weight: 800; transition: 0.18s; text-decoration: none"
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
                        style="font-size: 13.5px; font-weight: 600; color: #93a3bd; transition: color 0.16s"
                    >{{ item.label }}</Link>
                </div>
            </div>
        </div>

        <div style="border-top: 1px solid rgba(255, 255, 255, 0.09)">
            <div style="max-width: 1180px; margin: 0 auto; padding: 22px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap">
                <div style="font-size: 13px; font-weight: 600; color: #7d8ea8">© {{ new Date().getFullYear() }} Supreme Motors Ltd. All rights reserved.</div>
                <div style="display: flex; gap: 24px">
                    <Link href="/terms-condition" class="scpf" style="font-size: 13px; font-weight: 600; color: #93a3bd">Terms &amp; Conditions</Link>
                </div>
            </div>
        </div>
        <div style="height: 5px; background: linear-gradient(90deg, #c8151c, #e01f26, #c8151c)"></div>
    </footer>
</template>
