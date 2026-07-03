<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const testimonials = [
    {
        name: 'Joseph Mwakalinga',
        role: 'Logistics company owner · Tanzania',
        quote:
            'I ordered three trucks after only seeing photos and inspection reports. When they arrived in Dar es Salaam, every detail matched exactly what was promised. That kind of honesty is rare in this business.',
        purchased: 'Hino Dutro — Light Duty Truck',
    },
    {
        name: 'Ahmed Raza',
        role: 'Construction contractor · Pakistan',
        quote:
            'The team helped me compare excavators across three sources and negotiated a better price than I could get myself. Shipping documents were handled end to end — I just received the machine.',
        purchased: 'Doosan DX300LC Excavator',
    },
    {
        name: 'Grace Wanjiru',
        role: 'Vehicle dealer · Kenya',
        quote:
            'As a reseller I need reliable supply, not surprises. Supreme Motors has shipped me four batches of Hiace vans and every single unit cleared inspection at the port. My customers ask for their stock by name now.',
        purchased: '4 × Toyota Hiace Van',
    },
    {
        name: 'Omar Khalid',
        role: 'Equipment trader · UAE',
        quote:
            'A full container of machinery, sourced from two different countries, consolidated and delivered on one schedule. Their specialists found me stock that was never even listed on the website.',
        purchased: 'JCB 3CX + Forklift package',
    },
];

const active = ref(0);
const current = computed(() => testimonials[active.value]);
const initials = (name) => name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();

let timer = null;
const restart = () => {
    clearInterval(timer);
    timer = setInterval(() => (active.value = (active.value + 1) % testimonials.length), 7000);
};
const select = (i) => {
    active.value = i;
    restart();
};
onMounted(restart);
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>WHAT OUR CLIENTS SAY
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                    Trusted by buyers worldwide
                </h2>
                <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 14px; max-width: 520px">
                    From single vehicles to full containers — hear it from the people who've shipped with us.
                </p>
            </div>

            <!-- Featured quote -->
            <div style="background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); border-radius: 28px; padding: 48px 56px; margin-top: 36px; position: relative; overflow: hidden">
                <div style="position: absolute; bottom: -120px; left: -60px; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.14), transparent 70%)"></div>
                <div style="position: relative">
                    <div style="display: flex; gap: 4px">
                        <svg v-for="i in 5" :key="i" width="17" height="17" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    </div>
                    <p style="font-family: Archivo; font-weight: 600; font-size: 22px; line-height: 1.5; color: #fff; margin-top: 20px; max-width: 900px; letter-spacing: -0.01em">
                        “{{ current.quote }}”
                    </p>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-top: 28px; flex-wrap: wrap">
                        <div style="display: flex; align-items: center; gap: 14px">
                            <div style="width: 46px; height: 46px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 15px; color: #fff">
                                {{ initials(current.name) }}
                            </div>
                            <div>
                                <div style="font-family: Archivo; font-weight: 700; font-size: 15.5px; color: #fff">{{ current.name }}</div>
                                <div style="font-size: 13px; font-weight: 600; color: #8494ab; margin-top: 2px">{{ current.role }}</div>
                            </div>
                        </div>
                        <div style="text-align: right">
                            <div style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.06em; color: #ff8085">PURCHASED</div>
                            <div style="font-size: 13.5px; font-weight: 700; color: #cdd8e8; margin-top: 3px">{{ current.purchased }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selector cards -->
            <div class="sm-testrow" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 16px">
                <button
                    v-for="(t, i) in testimonials"
                    :key="t.name"
                    type="button"
                    :style="{
                        textAlign: 'left', cursor: 'pointer', borderRadius: '16px', padding: '16px 18px', transition: '0.2s',
                        background: i === active ? '#fff' : '#f8fafc',
                        border: i === active ? '1px solid #e01f26' : '1px solid #eef1f6',
                        boxShadow: i === active ? 'rgba(224, 31, 38, 0.10) 0 10px 24px' : 'none',
                    }"
                    @click="select(i)"
                >
                    <div style="display: flex; align-items: center; gap: 10px">
                        <div style="flex: 0 0 auto; width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(150deg, #12284a, #0b1e3b); display: flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 700; font-size: 12px; color: #fff">
                            {{ initials(t.name) }}
                        </div>
                        <div style="min-width: 0">
                            <div style="font-family: Archivo; font-weight: 700; font-size: 13.5px; color: #0b1e3b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ t.name }}</div>
                            <div style="display: flex; gap: 2px; margin-top: 3px">
                                <svg v-for="s in 5" :key="s" width="10" height="10" viewBox="0 0 24 24" fill="#ffc24b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            </div>
                        </div>
                    </div>
                    <p style="font-size: 12.5px; line-height: 1.55; color: #5b6b82; font-weight: 500; margin-top: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">
                        {{ t.quote }}
                    </p>
                </button>
            </div>
        </div>
    </section>
</template>
