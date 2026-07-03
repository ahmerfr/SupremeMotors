<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import SectionDivider from '@/components/Front/SectionDivider.vue';
import CantFindCta from '@/components/Front/CantFindCta.vue';

const steps = [
    {
        title: 'Find your vehicle',
        timing: 'Typically same day',
        body: 'Browse hundreds of thousands of cars, trucks and machines live in stock from Japan, China and Europe — or tell us the spec and budget if you don’t see it.',
        checks: [
            'Search by make, model, year, price and body type',
            'Source inspection data and photos on every listing',
            'Off-catalog sourcing from auctions and dealer stock',
        ],
        link: { label: 'Browse inventory', href: '/inventory' },
    },
    {
        title: 'Get your quote',
        timing: 'Within one working day',
        body: 'Send an enquiry and a specialist confirms availability and condition, then quotes your final landed price — FOB or CIF to your port.',
        checks: [
            'Transparent breakdown: vehicle, freight, insurance',
            'Auction sheets, extra photos or a third-party inspection on request',
            'No obligation — compare us before you commit',
        ],
        link: { label: 'Talk to a specialist', href: '/contact-us' },
    },
    {
        title: 'Pay securely',
        timing: '1–2 business days to clear',
        body: 'You receive a commercial invoice from Supreme Motor Equipments Limited and pay by bank transfer to our registered Hong Kong business account.',
        checks: [
            'SWIFT (T/T) international, or CHATS/ACH from Hong Kong banks',
            'Include the memo: [Buyer Name][Invoice Number][Product]',
            'Details change only on a re-issued invoice — verify before you wire',
        ],
        link: { label: 'See bank details', href: '/bank-details' },
    },
    {
        title: 'We ship it',
        timing: '2–6 weeks depending on lane',
        body: 'We handle export paperwork, loading and vessel booking — RoRo or container, including consolidated containers for multiple units or mixed machinery.',
        checks: [
            'Booking and bill of lading shared at loading — track the vessel yourself',
            'Japan 2–6 weeks, China 2–5, Europe 2–4 once the vessel departs',
            'Progress updates from loading to arrival',
        ],
    },
    {
        title: 'Receive & clear',
        timing: 'At your port',
        body: 'Your documents reach you before the vehicle does — commercial invoice, bill of lading, export certificate and inspection sheet, with certified translations where your customs authority needs them.',
        checks: [
            'Everything your clearing agent needs, first time',
            'Port pickup or onward delivery can be arranged',
            'Our team stays reachable after delivery',
        ],
    },
];
</script>

<template>
    <Head title="How to Buy" />

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
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>BUYING GUIDE<span style="width: 22px; height: 2px; background: #e01f26"></span>
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                How to Buy
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 540px">
                                From first search to keys in hand — five steps, one team handling everything in between.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Journey timeline -->
            <section class="sm-body sm-sec">
                <div style="max-width: 880px; margin: 0 auto">
                    <div v-for="(s, i) in steps" :key="s.title" style="display: flex; gap: 28px">
                        <!-- Rail: number + connecting line -->
                        <div style="display: flex; flex-direction: column; align-items: center; flex: 0 0 auto">
                            <div
                                :style="{
                                    width: '52px', height: '52px', borderRadius: '50%', flex: '0 0 auto',
                                    background: i === steps.length - 1 ? 'linear-gradient(150deg, #e5262d, #c8151c)' : '#0b1e3b',
                                    color: '#fff', fontFamily: 'Archivo', fontWeight: 800, fontSize: '18px',
                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: i === steps.length - 1 ? 'rgba(224, 31, 38, 0.35) 0 10px 24px' : 'rgba(11, 30, 59, 0.22) 0 10px 24px',
                                }"
                            >
                                {{ i + 1 }}
                            </div>
                            <div v-if="i < steps.length - 1" style="width: 2px; flex: 1; background: linear-gradient(180deg, #d4dbe6, #e6eaf0); margin: 10px 0"></div>
                        </div>

                        <!-- Step content -->
                        <div :style="{ paddingBottom: i < steps.length - 1 ? '46px' : '0' }">
                            <div style="display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap">
                                <h2 style="font-family: Archivo; font-weight: 800; font-size: 25px; letter-spacing: -0.015em; color: #0b1e3b; margin: 0; line-height: 1.2">
                                    {{ s.title }}
                                </h2>
                                <span style="font-size: 13.5px; font-weight: 700; color: #8895ab">{{ s.timing }}</span>
                            </div>
                            <p style="font-size: 15.5px; line-height: 1.7; color: #5b6b82; font-weight: 500; margin-top: 10px; max-width: 640px">
                                {{ s.body }}
                            </p>
                            <ul style="list-style: none; padding: 0; margin: 16px 0 0; display: flex; flex-direction: column; gap: 9px">
                                <li v-for="c in s.checks" :key="c" style="display: flex; align-items: flex-start; gap: 10px">
                                    <span style="flex: 0 0 auto; width: 19px; height: 19px; border-radius: 50%; background: rgba(224, 31, 38, 0.1); display: inline-flex; align-items: center; justify-content: center; margin-top: 2px">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    </span>
                                    <span style="font-size: 14.5px; line-height: 1.55; color: #33445e; font-weight: 500">{{ c }}</span>
                                </li>
                            </ul>
                            <Link
                                v-if="s.link"
                                :href="s.link.href"
                                class="scp3"
                                style="display: inline-flex; align-items: center; gap: 8px; margin-top: 16px; font-size: 14.5px; font-weight: 800; color: #e01f26; text-decoration: none"
                            >
                                {{ s.link.label }}
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                            </Link>
                        </div>
                    </div>

                    <p style="font-size: 15px; font-weight: 500; color: #5b6b82; margin: 44px 0 0; text-align: center">
                        Still unsure about something?
                        <Link href="/faqs" style="color: #e01f26; font-weight: 800; text-decoration: none">Read the FAQ →</Link>
                    </p>
                </div>
            </section>

            <SectionDivider />

            <CantFindCta />
        </FrontLayout>
    </div>
</template>
