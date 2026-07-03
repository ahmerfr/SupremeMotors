<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';

const faqs = [
    {
        q: 'How do I buy a vehicle from Supreme Motors?',
        a: 'Browse the inventory and send an enquiry on any vehicle — or tell our specialists what you need. We confirm availability, condition and a final landed quote for your port. Once you approve and pay, we handle export paperwork, loading and shipping, and keep you updated until delivery.',
    },
    {
        q: 'Which countries do you ship to?',
        a: 'Worldwide. Our busiest lanes are the UK, Hong Kong, the US, East and West Africa and the Middle East. Depending on the vehicle and destination we ship RoRo or in containers — including consolidated containers for multiple units or mixed machinery.',
    },
    {
        q: 'How long does shipping take?',
        a: 'It depends on the source and destination: typically 2–6 weeks from Japan, 2–5 weeks from China, and 2–4 weeks from Europe once the vessel departs. We share the booking and the bill of lading as soon as your unit is loaded, so you can track the vessel yourself.',
    },
    {
        q: 'How do I pay, and is it secure?',
        a: 'Payment is by bank transfer to our registered Hong Kong company account — the exact account details are on our Bank Details page and on every invoice. You receive a formal invoice before paying, and shipping documents are issued to your name after payment clears.',
    },
    {
        q: 'Can I verify a vehicle’s condition before paying?',
        a: 'Yes. Every listing carries the source inspection data, and on request we provide auction sheets, extra photos, cold-start videos or an independent third-party inspection before you commit. If a seller’s report notes a defect, you see it too.',
    },
    {
        q: 'What documents do I receive?',
        a: 'The commercial invoice, the bill of lading, the export certificate or deregistration document, and the inspection sheet where applicable — plus certified translations where your customs authority requires them. Your clearing agent gets everything needed for smooth clearance.',
    },
    {
        q: 'Can you find a vehicle that isn’t listed?',
        a: 'Yes — sourcing is what we do. We buy across Japan, China and Europe, including auction stock and dealer inventory that never appears on the website. Send the spec, budget and destination, and a specialist comes back with options, usually within a few days.',
    },
    {
        q: 'Why do some vehicles show “Enquire” instead of a price?',
        a: 'Prices move with auctions and exchange rates, and machinery is often negotiated per unit. “Enquire” means we quote you the current price for your destination — FOB or CIF — rather than showing a number that may be stale by the time you order.',
    },
];

// Items open independently: closing others would collapse content above
// the clicked question and yank the page upward.
const open = ref([0]);
const isOpen = (i) => open.value.includes(i);
const toggle = (i) => {
    open.value = isOpen(i) ? open.value.filter((x) => x !== i) : [...open.value, i];
};

const teamAvatars = [
    { initials: 'SM', bg: '#c9d3e2' },
    { initials: 'JL', bg: '#dfe5ee' },
    { initials: 'AK', bg: '#b8c4d7' },
];
</script>

<template>
    <Head title="FAQs" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <!-- Banner (contact-page header, FAQ heading) -->
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
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>HELP CENTER<span style="width: 22px; height: 2px; background: #e01f26"></span>
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                Frequently asked questions
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 520px">
                                Quick answers about buying, shipping, payments and paperwork — straight from our team.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ layout: intro + talk card left, accordion right -->
            <section class="sm-body sm-sec">
                <div class="sm-faqgrid" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 0.85fr 1.4fr; gap: 48px; align-items: start">
                    <div class="sm-faqside">
                        <h2 style="font-family: Archivo; font-weight: 800; font-size: 34px; letter-spacing: -0.025em; color: #0b1e3b; line-height: 1.1; margin: 0 0 14px">
                            FAQ<span style="color: #e01f26">.</span>
                        </h2>
                        <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; max-width: 380px">
                            Find quick answers to common questions about buying, shipping and payments. Can't find yours? Our specialists reply within one working day.
                        </p>

                        <!-- Still have questions? -->
                        <div style="position: relative; overflow: hidden; margin-top: 28px; border-radius: 22px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 28px">
                            <div style="position: absolute; bottom: -100px; right: -60px; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>
                            <div style="position: relative">
                                <div style="display: flex; align-items: center; gap: 9px">
                                    <div style="display: flex; align-items: center">
                                        <span
                                            v-for="(a, i) in teamAvatars"
                                            :key="a.initials"
                                            :style="{
                                                width: '32px', height: '32px', borderRadius: '50%', background: a.bg,
                                                border: '2px solid #0b1e3b', marginLeft: i === 0 ? '0' : '-9px',
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                fontFamily: 'Archivo', fontWeight: 700, fontSize: '11px', color: '#33445e',
                                            }"
                                        >{{ a.initials }}</span>
                                    </div>
                                    <span style="font-size: 12px; font-weight: 800; color: #0b1e3b; background: #fff; padding: 4px 11px; border-radius: 100px">+ You</span>
                                </div>
                                <div style="font-family: Archivo; font-weight: 800; font-size: 21px; color: #fff; margin-top: 16px">Still have questions?</div>
                                <p style="font-size: 14px; line-height: 1.6; color: #a9b7cc; font-weight: 500; margin-top: 6px">Reach out, and our team will guide you.</p>
                                <Link
                                    href="/contact-us"
                                    class="scp2"
                                    style="display: inline-flex; align-items: center; gap: 9px; margin-top: 20px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 13px 24px; border-radius: 100px; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; transition: transform 0.18s; text-decoration: none"
                                >
                                    Talk to our team
                                    <span style="width: 26px; height: 26px; border-radius: 50%; background: rgba(255, 255, 255, 0.18); display: inline-flex; align-items: center; justify-content: center">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Accordion -->
                    <div style="display: flex; flex-direction: column; gap: 14px">
                        <div
                            v-for="(f, i) in faqs"
                            :key="f.q"
                            :style="{
                                borderRadius: '18px', overflow: 'hidden', transition: 'all 0.3s cubic-bezier(0.32, 0.72, 0, 1)',
                                background: isOpen(i) ? 'linear-gradient(150deg, #12284a, #0b1e3b 60%, #081730)' : '#fff',
                                border: isOpen(i) ? '1px solid #0b1e3b' : '1px solid #e6eaf0',
                                boxShadow: isOpen(i) ? 'rgba(11, 30, 59, 0.28) 0 14px 34px' : 'rgba(11, 30, 59, 0.04) 0 4px 14px',
                            }"
                        >
                            <button
                                type="button"
                                style="display: flex; align-items: center; justify-content: space-between; gap: 18px; width: 100%; padding: 22px 26px; border: none; background: transparent; cursor: pointer; text-align: left"
                                :aria-expanded="isOpen(i)"
                                @click="toggle(i)"
                            >
                                <span :style="{ fontFamily: 'Archivo', fontWeight: 700, fontSize: '16.5px', lineHeight: 1.4, color: isOpen(i) ? '#fff' : '#0b1e3b' }">
                                    {{ i + 1 }}. {{ f.q }}
                                </span>
                                <span
                                    :style="{
                                        flex: '0 0 auto', width: '30px', height: '30px', borderRadius: '50%',
                                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                        background: isOpen(i) ? 'rgba(255,255,255,0.12)' : '#f4f6f9',
                                        border: isOpen(i) ? '1px solid rgba(255,255,255,0.18)' : '1px solid #eef1f6',
                                        transition: 'transform 0.3s cubic-bezier(0.32, 0.72, 0, 1)',
                                        transform: isOpen(i) ? 'rotate(180deg)' : 'none',
                                    }"
                                >
                                    <svg v-if="isOpen(i)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"><path d="M5 12h14" /></svg>
                                    <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#33445e" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                                </span>
                            </button>
                            <div :style="{ display: 'grid', gridTemplateRows: isOpen(i) ? '1fr' : '0fr', transition: 'grid-template-rows 0.35s cubic-bezier(0.32, 0.72, 0, 1)' }">
                                <div style="overflow: hidden">
                                    <p style="padding: 0 26px 24px; font-size: 15px; line-height: 1.7; color: #a9b7cc; font-weight: 500; max-width: 640px">
                                        {{ f.a }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </FrontLayout>
    </div>
</template>
