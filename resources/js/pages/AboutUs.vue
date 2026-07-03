<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import SectionDivider from '@/components/Front/SectionDivider.vue';
import Testimonials from '@/components/Front/Testimonials.vue';

const props = defineProps({
    makesCount: { type: Number, default: 0 },
});

const page = usePage();
const stockTotal = computed(() => page.props.headerData?.total ?? 0);
const addedToday = computed(() => page.props.headerData?.addedToday ?? 0);

const stats = computed(() => [
    { value: stockTotal.value.toLocaleString('en-US'), label: 'Vehicles in stock', live: true },
    { value: addedToday.value.toLocaleString('en-US'), label: 'Added today alone', live: true },
    { value: String(props.makesCount || 110), label: 'Makes to choose from' },
    { value: '1 day', label: 'Enquiry response' },
]);

const promises = [
    {
        title: 'If the seller notes a defect, you see it.',
        body: 'Every listing carries the source inspection data untouched. Bad news travels to you as fast as good news — that’s what makes the good news worth something.',
    },
    {
        title: 'One specialist, start to finish.',
        body: 'The person who quotes your vehicle books your vessel and answers your calls — and still picks up after the keys are in your hand.',
    },
    {
        title: 'One landed price. No surprises.',
        body: 'Vehicle, freight and insurance broken down line by line to your port, FOB or CIF. What customs sees is what you were quoted.',
    },
    {
        title: 'Wire only against an invoice.',
        body: 'Payment goes to our registered Hong Kong account, and those details never change outside a re-issued invoice — never by chat, email or phone.',
    },
];

let io = null;
onMounted(() => {
    io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in');
                    io.unobserve(e.target);
                }
            }),
        { threshold: 0.15 },
    );
    document.querySelectorAll('.sm-about .sm-reveal').forEach((el) => io.observe(el));
});
onBeforeUnmount(() => io?.disconnect());
</script>

<template>
    <Head title="About Us" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <div class="sm-about">
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
                                    <span style="width: 22px; height: 2px; background: #e01f26"></span>WHO WE ARE<span style="width: 22px; height: 2px; background: #e01f26"></span>
                                </div>
                                <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                    About Supreme Motors
                                </h1>
                                <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 560px">
                                    A Hong Kong vehicle export house moving cars, trucks and machinery from Japan, China and Europe to buyers worldwide.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Story + image -->
                <section class="sm-body sm-sec" style="padding-bottom: 0">
                    <div class="sm-abgrid sm-reveal" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 56px; align-items: center">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #8895ab">
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>OUR STORY
                            </div>
                            <h2 style="font-family: Archivo; font-weight: 800; font-size: 38px; letter-spacing: -0.02em; color: #0b1e3b; line-height: 1.12; margin-top: 14px">
                                Built to make importing a vehicle feel simple.
                            </h2>
                            <p style="font-size: 16px; line-height: 1.75; color: #5b6b82; font-weight: 500; margin-top: 18px">
                                Supreme Motors started in 2005 with a simple conviction: buying a vehicle from another country should feel as safe as buying one down the road. What began as a small dealership is now Supreme Motor Equipments Limited — a Hong Kong registered export house connecting buyers on every continent to auction stock, dealer inventory and machinery across Japan, China and Europe.
                            </p>
                            <p style="font-size: 16px; line-height: 1.75; color: #5b6b82; font-weight: 500; margin-top: 14px">
                                Everything that used to make importing painful — condition you can't verify, prices that shift, paperwork that stalls at customs — is our day job. Source inspection data on every listing, one landed quote to your port, and documents that reach your clearing agent before the vessel does.
                            </p>
                        </div>
                        <div style="position: relative">
                            <div style="border-radius: 24px; overflow: hidden; box-shadow: rgba(11, 30, 59, 0.18) 0 24px 60px">
                                <img src="/assets/images/cta-vehicle.jpg" alt="Supreme Motors vehicle stock" style="display: block; width: 100%; height: 420px; object-fit: cover" />
                            </div>
                            <div style="position: absolute; left: -18px; bottom: -18px; background: linear-gradient(150deg, #12284a, #081730); border-radius: 18px; padding: 18px 24px; box-shadow: rgba(11, 30, 59, 0.3) 0 16px 38px">
                                <div style="display: flex; align-items: center; gap: 8px">
                                    <span class="sm-livedot"></span>
                                    <span style="font-family: Archivo; font-weight: 800; font-size: 24px; color: #fff">{{ stockTotal.toLocaleString('en-US') }}</span>
                                </div>
                                <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc; margin-top: 3px">VEHICLES LIVE RIGHT NOW</div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats band -->
                    <div class="sm-abstats sm-reveal" style="max-width: 1280px; margin: 56px auto 0; display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid #e6eaf0">
                        <div v-for="(s, i) in stats" :key="s.label" :style="{ padding: '26px 24px 0', borderLeft: i > 0 ? '1px solid #e6eaf0' : 'none' }">
                            <div style="display: flex; align-items: center; gap: 9px">
                                <span v-if="s.live" class="sm-livedot"></span>
                                <span style="font-family: Archivo; font-weight: 800; font-size: 38px; letter-spacing: -0.02em; color: #0b1e3b">{{ s.value }}</span>
                            </div>
                            <div style="font-size: 13.5px; font-weight: 700; color: #8895ab; margin-top: 4px">{{ s.label }}</div>
                        </div>
                    </div>
                </section>

                <!-- Showroom panel: promises bento + registered office -->
                <section class="sm-body sm-sec">
                    <div style="max-width: 1280px; margin: 0 auto">
                        <div class="sm-reveal" style="position: relative; overflow: hidden; border-radius: 30px; background: linear-gradient(155deg, #13294c, #0b1e3b 48%, #081730); padding: 56px">
                            <div style="position: absolute; top: -180px; left: 22%; width: 560px; height: 560px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.14), transparent 68%)"></div>
                            <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1.5" style="position: absolute; top: -90px; right: -90px; width: 300px; height: 300px">
                                <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                            </svg>

                            <div class="sm-phead" style="position: relative; display: flex; align-items: flex-end; justify-content: space-between; gap: 32px; flex-wrap: wrap">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #cdd8e8">
                                        <span style="width: 22px; height: 2px; background: #e01f26"></span>HOW WE WORK
                                    </div>
                                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 38px; letter-spacing: -0.02em; color: #fff; margin-top: 12px; max-width: 560px; line-height: 1.1">
                                        Four promises we run the company on<span style="color: #e01f26">.</span>
                                    </h2>
                                </div>
                                <p style="font-size: 14.5px; line-height: 1.65; color: #8ea0bc; font-weight: 500; max-width: 300px; margin: 0 0 6px">
                                    No fine print. These are the terms we hold ourselves to on every unit we ship.
                                </p>
                            </div>

                            <div class="sm-pbento" style="position: relative; margin-top: 34px">
                                <div
                                    v-for="(p, i) in promises"
                                    :key="p.title"
                                    class="sm-pcard sm-reveal"
                                    :class="[i === 0 || i === 3 ? 'sm-pwide' : 'sm-pnarrow', { 'sm-pred': i === 3 }]"
                                    :style="{ transitionDelay: `${i * 60}ms` }"
                                >
                                    <div class="sm-pcore">
                                        <div class="sm-pnum" aria-hidden="true">0{{ i + 1 }}</div>
                                        <h3 class="sm-ptitle">{{ p.title }}</h3>
                                        <p class="sm-pbody">{{ p.body }}</p>
                                    </div>
                                </div>
                            </div>

                            <div style="position: relative; height: 1px; background: rgba(255, 255, 255, 0.1); margin: 44px 0"></div>

                            <div class="sm-abhq" style="position: relative; display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 44px">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #cdd8e8">
                                        <span style="width: 22px; height: 2px; background: #e01f26"></span>REGISTERED IN HONG KONG
                                    </div>
                                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 30px; letter-spacing: -0.02em; color: #fff; line-height: 1.15; margin-top: 14px">
                                        A real company, at a real address.
                                    </h2>
                                    <p style="font-size: 15px; line-height: 1.7; color: #a9b7cc; font-weight: 500; margin-top: 12px; max-width: 460px">
                                        Supreme Motor Equipments Limited is registered in Hong Kong and banks with JPMorgan Chase, Hong Kong. Every invoice, document and payment traces back to this office.
                                    </p>
                                    <Link
                                        href="/contact-us"
                                        class="scp2"
                                        style="display: inline-flex; align-items: center; gap: 9px; margin-top: 22px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 13px 24px; border-radius: 100px; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; transition: transform 0.18s; text-decoration: none"
                                    >
                                        Get in touch
                                        <span style="width: 26px; height: 26px; border-radius: 50%; background: rgba(255, 255, 255, 0.18); display: inline-flex; align-items: center; justify-content: center">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                        </span>
                                    </Link>
                                </div>
                                <div class="sm-abhqinfo" style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-content: center">
                                    <div style="grid-column: 1 / -1; display: flex; gap: 13px; align-items: flex-start; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 18px 20px">
                                        <span style="flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); display: inline-flex; align-items: center; justify-content: center">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cdd8e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" /><circle cx="12" cy="10" r="3" /></svg>
                                        </span>
                                        <div>
                                            <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc">REGISTERED OFFICE</div>
                                            <div style="font-size: 14.5px; font-weight: 600; color: #e6ecf5; line-height: 1.6; margin-top: 3px">
                                                Unit 1603, 16th Floor, The L. Plaza<br />367–375 Queen's Road Central, Sheung Wan, Hong Kong
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 13px; align-items: flex-start; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 18px 20px">
                                        <span style="flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); display: inline-flex; align-items: center; justify-content: center">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cdd8e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92Z" /></svg>
                                        </span>
                                        <div>
                                            <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc">PHONE</div>
                                            <div style="font-size: 14.5px; font-weight: 600; color: #e6ecf5; margin-top: 3px">+852 5322 1678</div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 13px; align-items: flex-start; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 18px 20px">
                                        <span style="flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); display: inline-flex; align-items: center; justify-content: center">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cdd8e8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-10 6L2 7" /></svg>
                                        </span>
                                        <div>
                                            <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc">EMAIL</div>
                                            <div style="font-size: 14.5px; font-weight: 600; color: #e6ecf5; margin-top: 3px">info@suprememotors.ltd</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <SectionDivider />

                <Testimonials />
            </div>
        </FrontLayout>
    </div>
</template>
