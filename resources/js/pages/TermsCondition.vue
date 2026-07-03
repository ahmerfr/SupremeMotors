<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';

const sections = [
    {
        id: 'agreement',
        title: 'Agreement to these terms',
        body: [
            'These Terms & Conditions govern your use of the Supreme Motors website and the purchase of any vehicle, machinery or equipment through Supreme Motors Equipments Limited, a company registered in Hong Kong with its office in Sheung Wan, Hong Kong.',
            'By browsing the website, sending an enquiry or placing an order, you accept these terms. If you are acting for a company, you confirm you are authorised to bind that company.',
        ],
    },
    {
        id: 'listings',
        title: 'Listings, pricing & availability',
        body: [
            'Our inventory is aggregated from auctions, dealers and partner stock across Japan, China and Europe. Listings — including photographs, specifications, mileage and condition notes — are provided by the source and are shared with you in good faith as received.',
            'Stock moves quickly: a listed unit may be sold, withdrawn or re-priced by the source before your enquiry is confirmed. No listing is an offer; a purchase only becomes binding when we issue a commercial invoice and you pay it.',
            'Prices shown as “Enquire” are quoted per destination on request (FOB or CIF) because auction prices and exchange rates change daily.',
        ],
    },
    {
        id: 'orders',
        title: 'Orders & payment',
        body: [
            'Every order is confirmed with a commercial invoice stating the vehicle, price, incoterm and destination. Payment is by bank transfer to the registered company account named on the invoice — we never ask you to pay a personal account, and any change of bank details will only ever be communicated on a re-issued invoice, never by chat message.',
            'Ownership documents and shipping documents are issued to the buyer named on the invoice after payment clears in full. Bank charges on the sender’s side are for the buyer’s account.',
        ],
    },
    {
        id: 'shipping',
        title: 'Shipping & delivery',
        body: [
            'We arrange shipment RoRo or by container to your nominated port. Transit estimates (typically 2–6 weeks from Japan, 2–5 from China, 2–4 from Europe) are estimates only — vessel schedules, port congestion and carrier decisions are outside our control.',
            'Risk passes according to the incoterm on your invoice. Import duties, taxes, port charges and clearance at destination are the buyer’s responsibility, and we recommend confirming your country’s import rules (age limits, emissions, steering position) before ordering.',
        ],
    },
    {
        id: 'condition',
        title: 'Vehicle condition & inspections',
        body: [
            'Most units we sell are used. Auction sheets, inspection reports, extra photos or third-party inspections are available on request before you pay, and we encourage you to use them.',
            'Used vehicles are sold as described in the listing and inspection documents. Fair wear consistent with age and mileage — and defects disclosed in the auction sheet or listing — are not grounds for a claim. If a unit materially differs from its description on arrival, contact us within 7 days of taking delivery and we will work with you and the source on a remedy.',
        ],
    },
    {
        id: 'liability',
        title: 'Limitation of liability',
        body: [
            'Nothing in these terms excludes liability that cannot be excluded by law. Subject to that, our total liability for any order is limited to the amount you paid us for that order, and we are not liable for indirect losses such as lost profit, lost business or delays caused by carriers, ports or customs.',
        ],
    },
    {
        id: 'website',
        title: 'Website use & intellectual property',
        body: [
            'The Supreme Motors name, logo and the design of this website are our property. Listing data and images remain the property of their respective sources. You may not scrape, republish or resell the contents of this website without written permission.',
            'We work to keep the website accurate and available but provide it “as is”, without warranty that it is error-free or uninterrupted.',
        ],
    },
    {
        id: 'privacy',
        title: 'Privacy',
        body: [
            'We collect the details you send us — name, contact information and enquiry content — solely to answer your enquiry and process your order. We do not sell your data. Payment goes directly to our bank; we never store card numbers. To have your data corrected or removed, email info@suprememotors.ltd.',
        ],
    },
    {
        id: 'law',
        title: 'Governing law & contact',
        body: [
            'These terms are governed by the laws of the Hong Kong Special Administrative Region, and disputes are subject to the exclusive jurisdiction of the Hong Kong courts.',
            'Questions about these terms: info@suprememotors.ltd, +44 7516 916622 or +1 647 846 3886.',
        ],
    },
];

const active = ref(sections[0].id);
let observer = null;

onMounted(() => {
    // Scrollspy: the section nearest below the sticky header wins.
    observer = new IntersectionObserver(
        (entries) => {
            const hit = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
            if (hit) active.value = hit.target.id;
        },
        { rootMargin: '-230px 0px -55% 0px' },
    );
    sections.forEach((s) => {
        const el = document.getElementById(s.id);
        if (el) observer.observe(el);
    });
});
onBeforeUnmount(() => observer?.disconnect());

const jump = (id) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
    active.value = id;
};
</script>

<template>
    <Head title="Terms & Conditions" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <!-- Banner (shared page-header treatment) -->
            <section class="sm-body sm-sec" style="padding-bottom: 0">
                <div style="max-width: 1280px; margin: 0 auto">
                    <div style="position: relative; overflow: hidden; border-radius: 28px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 64px 32px; text-align: center">
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" style="position: absolute; top: -70px; left: -70px; width: 220px; height: 220px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" style="position: absolute; bottom: -70px; right: -70px; width: 220px; height: 220px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>
                        <div style="position: absolute; top: -120px; right: 10%; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>

                        <div style="position: relative">
                            <div style="display: inline-flex; align-items: center; gap: 8px; color: #cdd8e8; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>LEGAL<span style="width: 22px; height: 2px; background: #e01f26"></span>
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                Terms &amp; Conditions
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 560px">
                                The plain-English rules for browsing our inventory and buying through Supreme Motors — including our privacy commitments.
                            </p>
                            <p style="font-size: 13px; color: #7487a3; font-weight: 600; margin-top: 18px">Last updated: July 4, 2026</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOC + content -->
            <section class="sm-body sm-sec">
                <div class="sm-faqgrid" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 0.85fr 1.4fr; gap: 48px; align-items: start">
                    <div class="sm-faqside">
                        <div style="font-family: Archivo; font-weight: 800; font-size: 13px; letter-spacing: 0.08em; color: #8895ab; display: flex; align-items: center; gap: 8px">
                            <span style="width: 22px; height: 2px; background: #e01f26"></span>ON THIS PAGE
                        </div>
                        <nav style="margin-top: 18px; display: flex; flex-direction: column">
                            <button
                                v-for="(s, i) in sections"
                                :key="s.id"
                                type="button"
                                class="sm-tocbtn"
                                :style="{
                                    display: 'flex', alignItems: 'baseline', gap: '12px', width: '100%',
                                    padding: '11px 14px', border: 'none', cursor: 'pointer', textAlign: 'left',
                                    borderRadius: '12px', transition: 'all 0.25s cubic-bezier(0.32, 0.72, 0, 1)',
                                    background: active === s.id ? '#0b1e3b' : 'transparent',
                                }"
                                @click="jump(s.id)"
                            >
                                <span :style="{ fontFamily: 'Archivo', fontWeight: 800, fontSize: '12.5px', color: active === s.id ? '#e5262d' : '#b6c0d0', minWidth: '22px' }">
                                    {{ String(i + 1).padStart(2, '0') }}
                                </span>
                                <span :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '14.5px', lineHeight: 1.35, color: active === s.id ? '#fff' : '#33445e' }">
                                    {{ s.title }}
                                </span>
                            </button>
                        </nav>

                        <!-- Questions card -->
                        <div style="position: relative; overflow: hidden; margin-top: 26px; border-radius: 22px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 26px">
                            <div style="position: absolute; bottom: -100px; right: -60px; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>
                            <div style="position: relative">
                                <div style="font-family: Archivo; font-weight: 800; font-size: 19px; color: #fff">Questions about these terms?</div>
                                <p style="font-size: 14px; line-height: 1.6; color: #a9b7cc; font-weight: 500; margin-top: 6px">Our team replies within one working day.</p>
                                <Link
                                    href="/contact-us"
                                    class="scp2"
                                    style="display: inline-flex; align-items: center; gap: 9px; margin-top: 18px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 12px 22px; border-radius: 100px; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; transition: transform 0.18s; text-decoration: none"
                                >
                                    Contact us
                                    <span style="width: 26px; height: 26px; border-radius: 50%; background: rgba(255, 255, 255, 0.18); display: inline-flex; align-items: center; justify-content: center">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Sections -->
                    <div style="display: flex; flex-direction: column; gap: 42px">
                        <article
                            v-for="(s, i) in sections"
                            :id="s.id"
                            :key="s.id"
                            style="scroll-margin-top: 228px"
                        >
                            <h2 style="display: flex; align-items: baseline; gap: 14px; font-family: Archivo; font-weight: 800; font-size: 20px; letter-spacing: -0.01em; color: #0b1e3b; margin: 0">
                                <span style="color: #e01f26; font-size: 15px">{{ String(i + 1).padStart(2, '0') }}</span>
                                {{ s.title }}
                            </h2>
                            <p v-for="(p, j) in s.body" :key="j" style="font-size: 15.5px; line-height: 1.75; color: #5b6b82; font-weight: 500; margin: 14px 0 0">
                                {{ p }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>
        </FrontLayout>
    </div>
</template>
