<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const testimonials = [
    {
        name: 'Joseph Mwakalinga',
        role: 'Logistics company owner',
        place: 'Dar es Salaam, Tanzania',
        quote:
            'The inspection sheet listed a scratch on the rear gate that I could not even find in the photos. When a seller volunteers the bad news, you can trust everything else they tell you.',
        purchased: 'Hino Dutro — Light Duty Truck',
    },
    {
        name: 'Ahmed Raza',
        role: 'Site contractor',
        place: 'Karachi, Pakistan',
        quote:
            'I sent a spec list on a Tuesday and had three excavator options with running hours and undercarriage photos by Friday. The machine cleared Karachi port with zero paperwork drama.',
        purchased: 'Doosan DX300LC Excavator',
    },
    {
        name: 'Grace Wanjiru',
        role: 'Vehicle dealer',
        place: 'Nairobi, Kenya',
        quote:
            'Fourth batch of Hiaces from them this year. Same auction grade every single time — that consistency is why my own customers now ask for their stock by name.',
        purchased: '4 × Toyota Hiace Van',
    },
    {
        name: 'Omar Khalid',
        role: 'Equipment trader',
        place: 'Dubai, UAE',
        quote:
            'One container, machinery from two different countries, one delivery schedule. They even found me a backhoe that was never listed anywhere on the website.',
        purchased: 'JCB 3CX + Forklift package',
    },
    {
        name: 'Chanda Mwansa',
        role: 'Mining subcontractor',
        place: 'Kitwe, Zambia',
        quote:
            'Both tippers arrived with full service records and new batteries already fitted. They were hauling ore the same week they cleared the border.',
        purchased: '2 × HOWO 371 Dump Truck',
    },
    {
        name: 'Nuwan Perera',
        role: 'Taxi fleet operator',
        place: 'Colombo, Sri Lanka',
        quote:
            'Auction grade 4.5, exactly as promised, and they sent the hybrid battery health report before I paid the balance. My mechanic found nothing to argue with.',
        purchased: 'Toyota Corolla Fielder Hybrid',
    },
    {
        name: 'Sarah Nakato',
        role: 'School administrator',
        place: 'Kampala, Uganda',
        quote:
            'They walked our entire board through every invoice line on a video call before we committed. The bus arrived two days ahead of the estimate.',
        purchased: 'Toyota Coaster — 29 Seater',
    },
    {
        name: 'Tinashe Moyo',
        role: 'Farm co-operative manager',
        place: 'Harare, Zimbabwe',
        quote:
            'We compared six tractors over WhatsApp with cold-start videos for each one. No sales pressure at any point — just straight answers to farmer questions.',
        purchased: 'Massey Ferguson 385 4WD',
    },
    {
        name: 'Kwame Boateng',
        role: 'Haulage operator',
        place: 'Tema, Ghana',
        quote:
            'The customs valuation matched their invoice to the dollar. My clearing agent said he had never seen European truck paperwork arrive that clean.',
        purchased: 'DAF XF 480 Tractor Unit',
    },
    {
        name: 'Farida Hassan',
        role: 'First-time importer',
        place: 'Zanzibar, Tanzania',
        quote:
            'My first imported car. I must have asked forty questions and they answered every one without ever making me feel like a small customer.',
        purchased: 'Honda Vezel Hybrid Z',
    },
];

const active = ref(0);
const current = computed(() => testimonials[active.value]);
const initials = (name) => name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();

let timer = null;
const restart = () => {
    clearInterval(timer);
    timer = setInterval(() => step(1), 7000);
};
const step = (dir) => {
    active.value = (active.value + dir + testimonials.length) % testimonials.length;
    scrollActiveIntoView();
};
const select = (i) => {
    active.value = i;
    restart();
    scrollActiveIntoView();
};

const stripEl = ref(null);
const scrollActiveIntoView = () => {
    const el = stripEl.value?.children[active.value];
    el?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
};

const sectionEl = ref(null);
onMounted(() => {
    restart();
    const io = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.target.classList.toggle('is-in', e.isIntersecting)),
        { threshold: 0.15 },
    );
    if (sectionEl.value) io.observe(sectionEl.value);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div ref="sectionEl" class="sm-reveal" style="max-width: 1280px; margin: 0 auto">
            <!-- Centered header, accent on the closing words -->
            <div style="text-align: center">
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>TESTIMONIALS<span style="width: 22px; height: 2px; background: #e01f26"></span>
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                    What our <span style="color: #e01f26">clients say</span>
                </h2>
                <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin: 14px auto 0; max-width: 480px">
                    Don't take our word for it — hear it from buyers who've shipped with us across three continents.
                </p>
            </div>

            <!-- Featured quote: bezel shell around the navy card -->
            <div style="max-width: 980px; margin: 44px auto 0; position: relative">
                <div style="background: rgba(11, 30, 59, 0.05); border: 1px solid rgba(11, 30, 59, 0.07); border-radius: 32px; padding: 8px">
                    <div class="sm-tqcard" style="position: relative; overflow: hidden; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); border-radius: 24px; padding: 52px 56px 40px; box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.08)">
                        <div style="position: absolute; bottom: -140px; right: -80px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.13), transparent 70%)"></div>

                        <!-- Quote badge -->
                        <div style="position: absolute; top: 26px; left: 32px; width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(150deg, #e5262d, #c8151c); box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px; display: flex; align-items: center; justify-content: center">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M10 7H6.5C5.1 7 4 8.1 4 9.5V13c0 1.4 1.1 2.5 2.5 2.5H9c0 2-1.5 3.2-3.4 3.5l.6 1.9C9.4 20.4 11 18.2 11 15V8c0-.6-.4-1-1-1zm9 0h-3.5C14.1 7 13 8.1 13 9.5V13c0 1.4 1.1 2.5 2.5 2.5H18c0 2-1.5 3.2-3.4 3.5l.6 1.9c3.2-.5 4.8-2.7 4.8-5.9V8c0-.6-.4-1-1-1z" /></svg>
                        </div>

                        <div style="position: relative; padding-left: 78px" class="sm-tqwrap">
                            <Transition name="tq" mode="out-in">
                                <div :key="active">
                                    <div style="display: flex; gap: 4px">
                                        <svg v-for="i in 5" :key="i" width="16" height="16" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                                    </div>
                                    <p style="font-family: Archivo; font-weight: 600; font-size: 21px; line-height: 1.55; color: #fff; margin-top: 16px; letter-spacing: -0.01em; min-height: 98px">
                                        “{{ current.quote }}”
                                    </p>
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-top: 24px; flex-wrap: wrap">
                                        <div style="display: flex; align-items: center; gap: 13px">
                                            <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 14px; color: #fff">
                                                {{ initials(current.name) }}
                                            </div>
                                            <div>
                                                <div style="font-family: Archivo; font-weight: 700; font-size: 15px; color: #fff">{{ current.name }}</div>
                                                <div style="font-size: 12.5px; font-weight: 600; color: #8494ab; margin-top: 2px">{{ current.role }} · {{ current.place }}</div>
                                            </div>
                                        </div>
                                        <div style="text-align: right">
                                            <div style="font-size: 11px; font-weight: 800; letter-spacing: 0.07em; color: #ff8085">PURCHASED</div>
                                            <div style="font-size: 13px; font-weight: 700; color: #cdd8e8; margin-top: 3px">{{ current.purchased }}</div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Controls: arrow · dots · arrow -->
                            <div style="display: flex; align-items: center; justify-content: center; gap: 18px; margin-top: 30px; padding-top: 24px; border-top: 1px solid rgba(255, 255, 255, 0.08)">
                                <button type="button" class="sm-tbtn" aria-label="Previous testimonial" @click="step(-1); restart()">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                                </button>
                                <div style="display: flex; gap: 7px; align-items: center">
                                    <button
                                        v-for="(t, i) in testimonials"
                                        :key="i"
                                        type="button"
                                        :aria-label="`Show testimonial ${i + 1}`"
                                        :style="{
                                            height: '7px', borderRadius: '100px', border: 'none', cursor: 'pointer', padding: 0,
                                            width: i === active ? '26px' : '7px',
                                            background: i === active ? '#e01f26' : 'rgba(255,255,255,0.22)',
                                            transition: 'all 0.45s cubic-bezier(0.32, 0.72, 0, 1)',
                                        }"
                                        @click="select(i)"
                                    ></button>
                                </div>
                                <button type="button" class="sm-tbtn" aria-label="Next testimonial" @click="step(1); restart()">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scrollable strip of all voices -->
            <div ref="stripEl" class="sm-strip" style="display: flex; gap: 14px; margin-top: 20px; overflow-x: auto; padding: 4px 4px 10px; scroll-snap-type: x mandatory">
                <button
                    v-for="(t, i) in testimonials"
                    :key="t.name"
                    type="button"
                    :style="{
                        flex: '0 0 288px', scrollSnapAlign: 'center', textAlign: 'left', cursor: 'pointer',
                        borderRadius: '16px', padding: '15px 17px',
                        transition: 'all 0.45s cubic-bezier(0.32, 0.72, 0, 1)',
                        background: i === active ? '#fff' : '#f8fafc',
                        border: i === active ? '1px solid rgba(224, 31, 38, 0.45)' : '1px solid #eef1f6',
                        boxShadow: i === active ? 'rgba(224, 31, 38, 0.08) 0 12px 26px' : 'none',
                        transform: i === active ? 'translateY(-2px)' : 'none',
                    }"
                    @click="select(i)"
                >
                    <div style="display: flex; align-items: center; gap: 10px">
                        <div style="flex: 0 0 auto; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(150deg, #12284a, #0b1e3b); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 11px; color: #fff">
                            {{ initials(t.name) }}
                        </div>
                        <div style="min-width: 0">
                            <div style="font-family: Archivo; font-weight: 700; font-size: 13px; color: #0b1e3b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ t.name }}</div>
                            <div style="font-size: 11px; font-weight: 600; color: #8494ab; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ t.place }}</div>
                        </div>
                        <div style="margin-left: auto; display: flex; gap: 1.5px; flex: 0 0 auto">
                            <svg v-for="s in 5" :key="s" width="9" height="9" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                        </div>
                    </div>
                    <p style="font-size: 12px; line-height: 1.55; color: #5b6b82; font-weight: 500; margin-top: 9px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">
                        {{ t.quote }}
                    </p>
                </button>
            </div>
        </div>
    </section>
</template>
