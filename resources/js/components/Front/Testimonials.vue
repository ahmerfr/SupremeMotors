<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const testimonials = [
    {
        name: 'Marcus Whitfield',
        role: 'Fleet manager',
        place: 'Birmingham, UK',
        quote:
            'The inspection sheet listed a scratch on the rear door that I could not even find in the photos. When a seller volunteers the bad news, you can trust everything else they tell you.',
        purchased: '3 × Toyota Hiace Van',
    },
    {
        name: 'Elaine Cheung',
        role: 'Trading company director',
        place: 'Hong Kong',
        quote:
            'We consolidate mixed containers every quarter and they are the only supplier who has never missed a loading date at Kwai Chung. Documentation arrives before the vessel does.',
        purchased: 'Mixed machinery container',
    },
    {
        name: 'David Reyes',
        role: 'Landscaping business owner',
        place: 'Houston, Texas, USA',
        quote:
            'I sent a spec list on a Tuesday and had three mini excavator options with running hours and undercarriage photos by Friday. It cleared Houston customs with zero paperwork drama.',
        purchased: 'Kubota U27 Mini Excavator',
    },
    {
        name: 'Li Wei',
        role: 'Construction firm buyer',
        place: 'Shenzhen, China',
        quote:
            'European cranes with real service history are hard to verify from here. They arranged a third-party inspection and sent the full report before asking for a deposit.',
        purchased: 'Liebherr Mobile Crane',
    },
    {
        name: 'James Callahan',
        role: 'Classic JDM importer',
        place: 'Portland, Oregon, USA',
        quote:
            'They understand the US 25-year rule better than most stateside brokers. The Prado arrived with every document EPA and DOT wanted, first try.',
        purchased: '1995 Land Cruiser Prado',
    },
    {
        name: 'Priya Shah',
        role: 'Logistics coordinator',
        place: 'London, UK',
        quote:
            'The customs valuation matched their invoice to the pound. Our clearing agent at Felixstowe said he had never seen European truck paperwork arrive that clean.',
        purchased: 'DAF XF 480 Tractor Unit',
    },
    {
        name: 'Kenneth Lau',
        role: 'Plant hire company owner',
        place: 'Hong Kong',
        quote:
            'One container, machinery from two different countries, one delivery schedule. They even found me a backhoe that was never listed anywhere on the website.',
        purchased: 'JCB 3CX + Genie Lift',
    },
    {
        name: 'Sofia Ramirez',
        role: 'Farm operations manager',
        place: 'Fresno, California, USA',
        quote:
            'We compared six tractors over video calls with cold-start footage for each one. No sales pressure at any point — just straight answers to working questions.',
        purchased: 'John Deere 6120M',
    },
    {
        name: 'Zhang Min',
        role: 'Mining services manager',
        place: 'Kunming, China',
        quote:
            'Both tippers arrived with full service records and new batteries already fitted. They were hauling the same week they cleared the port.',
        purchased: '2 × HOWO 371 Dump Truck',
    },
    {
        name: 'Tommy Ho',
        role: 'First-time importer',
        place: 'Hong Kong',
        quote:
            'My first imported car. I must have asked forty questions and they answered every one without ever making me feel like a small customer.',
        purchased: 'Honda Vezel Hybrid Z',
    },
];

const active = ref(0);
const current = computed(() => testimonials[active.value]);
const initials = (name) => name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();

// Auto-rotation runs ONLY while the section is on screen in a focused tab —
// off-screen rotation must never scroll anything near the reader.
let inView = false;
let timer = null;
const restart = () => {
    clearInterval(timer);
    if (inView && !document.hidden) {
        timer = setInterval(() => step(1), 7000);
    }
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
// Scroll the strip only (scrollIntoView may scroll the page itself), and to
// an absolute centered target: reading scrollTop for a "nearest" check races
// with still-running smooth scrolls and goes stale.
const scrollActiveIntoView = () => {
    const strip = stripEl.value;
    const el = strip?.children[active.value];
    if (!strip || !el) return;
    const target = el.offsetTop - (strip.clientHeight - el.offsetHeight) / 2;
    strip.scrollTo({ top: Math.max(0, Math.min(target, strip.scrollHeight - strip.clientHeight)), behavior: 'smooth' });
};

const sectionEl = ref(null);
const onVisibility = () => restart();
onMounted(() => {
    document.addEventListener('visibilitychange', onVisibility);
    const io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                e.target.classList.toggle('is-in', e.isIntersecting);
                inView = e.isIntersecting;
                restart();
            }),
        { threshold: 0.15 },
    );
    if (sectionEl.value) io.observe(sectionEl.value);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div ref="sectionEl" class="sm-reveal" style="max-width: 1280px; margin: 0 auto">
            <!-- Theme-standard left header -->
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>TESTIMONIALS
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                    What our <span style="color: #e01f26">clients say</span>
                </h2>
                <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 14px; max-width: 520px">
                    Don't take our word for it — hear it from buyers who've shipped with us across three continents.
                </p>
            </div>

            <!-- Featured quote left · vertical voices right -->
            <div class="sm-testgrid" style="display: grid; grid-template-columns: 1.65fr 1fr; gap: 20px; margin-top: 44px; align-items: stretch">
                <!-- Featured quote in a bezel shell: fixed height, content is
                     absolutely positioned so quote length can never resize
                     the card (and therefore never the section). -->
                <div style="background: rgba(11, 30, 59, 0.05); border: 1px solid rgba(11, 30, 59, 0.07); border-radius: 32px; padding: 8px">
                    <div class="sm-tqcard" style="position: relative; overflow: hidden; display: flex; flex-direction: column; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); border-radius: 24px; padding: 42px 48px 28px; box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.08)">
                        <div style="position: absolute; bottom: -140px; right: -80px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.13), transparent 70%)"></div>

                        <!-- Decorative quote badge: absolute + half opacity, out of the layout flow -->
                        <div style="position: absolute; top: 30px; left: 36px; z-index: 0; opacity: 0.5; width: 64px; height: 64px; border-radius: 18px; background: linear-gradient(150deg, #e5262d, #c8151c); display: flex; align-items: center; justify-content: center">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="#fff"><path d="M10 7H6.5C5.1 7 4 8.1 4 9.5V13c0 1.4 1.1 2.5 2.5 2.5H9c0 2-1.5 3.2-3.4 3.5l.6 1.9C9.4 20.4 11 18.2 11 15V8c0-.6-.4-1-1-1zm9 0h-3.5C14.1 7 13 8.1 13 9.5V13c0 1.4 1.1 2.5 2.5 2.5H18c0 2-1.5 3.2-3.4 3.5l.6 1.9c3.2-.5 4.8-2.7 4.8-5.9V8c0-.6-.4-1-1-1z" /></svg>
                        </div>

                        <div style="position: relative; z-index: 1; flex: 1; min-height: 0; display: flex; padding-top: 58px">
                            <div style="flex: 1; min-width: 0; position: relative; align-self: stretch">
                                <Transition name="tq" mode="out-in">
                                    <div :key="active" style="position: absolute; inset: 0; display: flex; flex-direction: column">
                                        <div style="display: flex; gap: 4px; flex: 0 0 auto">
                                            <svg v-for="i in 5" :key="i" width="17" height="17" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                                        </div>
                                        <p class="sm-tquote" style="font-family: Archivo; font-weight: 600; font-size: 24px; line-height: 1.5; color: #fff; margin-top: 16px; letter-spacing: -0.012em; flex: 1; min-height: 0; overflow: hidden">
                                            “{{ current.quote }}”
                                        </p>
                                        <div style="flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: nowrap; padding-bottom: 2px">
                                            <div style="display: flex; align-items: center; gap: 14px; min-width: 0">
                                                <div style="flex: 0 0 auto; width: 48px; height: 48px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 15px; color: #fff">
                                                    {{ initials(current.name) }}
                                                </div>
                                                <div style="min-width: 0">
                                                    <div style="font-family: Archivo; font-weight: 700; font-size: 16.5px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ current.name }}</div>
                                                    <div style="font-size: 13.5px; font-weight: 600; color: #8494ab; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ current.role }} · {{ current.place }}</div>
                                                </div>
                                            </div>
                                            <div style="flex: 0 0 auto; text-align: right">
                                                <div style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.07em; color: #ff8085">PURCHASED</div>
                                                <div style="font-size: 14px; font-weight: 700; color: #cdd8e8; margin-top: 3px; white-space: nowrap">{{ current.purchased }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>
                        </div>

                        <!-- Controls pinned to card bottom -->
                        <div style="position: relative; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; gap: 18px; margin-top: 22px; padding-top: 22px; border-top: 1px solid rgba(255, 255, 255, 0.08)">
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

                <!-- Vertical scroller of all voices -->
                <div class="sm-testcol" style="position: relative">
                    <div ref="stripEl" class="sm-teststrip" style="position: absolute; inset: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 2px 6px 8px 2px; scroll-padding-top: 2px; scroll-snap-type: y proximity">
                        <button
                            v-for="(t, i) in testimonials"
                            :key="t.name"
                            type="button"
                            :style="{
                                flex: '0 0 auto', scrollSnapAlign: 'start', textAlign: 'left', cursor: 'pointer',
                                borderRadius: '16px', padding: '16px 18px',
                                transition: 'all 0.45s cubic-bezier(0.32, 0.72, 0, 1)',
                                background: i === active ? '#fff' : '#f8fafc',
                                border: i === active ? '1px solid rgba(224, 31, 38, 0.45)' : '1px solid #eef1f6',
                                boxShadow: i === active ? 'rgba(224, 31, 38, 0.08) 0 8px 20px' : 'none',
                            }"
                            @click="select(i)"
                        >
                            <div style="display: flex; align-items: center; gap: 11px">
                                <div style="flex: 0 0 auto; width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(150deg, #12284a, #0b1e3b); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 12px; color: #fff">
                                    {{ initials(t.name) }}
                                </div>
                                <div style="min-width: 0">
                                    <div style="font-family: Archivo; font-weight: 700; font-size: 14.5px; color: #0b1e3b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ t.name }}</div>
                                    <div style="font-size: 12px; font-weight: 600; color: #8494ab; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ t.place }}</div>
                                </div>
                                <div style="margin-left: auto; display: flex; gap: 2px; flex: 0 0 auto">
                                    <svg v-for="s in 5" :key="s" width="10" height="10" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                                </div>
                            </div>
                            <p style="font-size: 13px; line-height: 1.6; color: #5b6b82; font-weight: 500; margin-top: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">
                                {{ t.quote }}
                            </p>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
