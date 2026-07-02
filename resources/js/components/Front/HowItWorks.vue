<script setup>
const steps = [
    { num: '01', icon: '🔍', title: 'Search Inventory', desc: 'Browse curated listings and filter by brand, type, and price to shortlist your favourites.' },
    { num: '02', icon: '💬', title: 'Compare & Enquire', desc: 'Compare specs side by side and send a quick enquiry to our helpful support team.' },
    { num: '03', icon: '🚗', title: 'Drive With Confidence', desc: 'Finalise the details with clarity and drive away in a vehicle you can trust.' },
];
</script>

<template>
    <section id="how" class="sm-body" style="padding: 110px 24px 0">
        <div class="sm-howwrap" style="max-width: 1180px; margin: 0 auto; background: radial-gradient(120% 140% at 100% 0%, #12284a, #0b1e3b 60%, #081730); border-radius: 32px; padding: 64px 56px; position: relative; overflow: hidden">
            <div style="position: absolute; top: -100px; left: -60px; width: 360px; height: 360px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.14), transparent 70%)"></div>
            <div style="text-align: center; position: relative">
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #ff6b70; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; justify-content: center">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>HOW IT WORKS<span style="width: 22px; height: 2px; background: #e01f26"></span>
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #fff; margin-top: 12px">Three simple steps to your next car</h2>
            </div>
            <div class="sm-steps" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 48px; position: relative">
                <div v-for="s in steps" :key="s.num" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 32px 28px; backdrop-filter: blur(6px)">
                    <div style="display: flex; align-items: center; justify-content: space-between">
                        <div style="width: 54px; height: 54px; border-radius: 15px; background: linear-gradient(150deg, #e5262d, #c8151c); display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: rgba(224, 31, 38, 0.35) 0 10px 24px">{{ s.icon }}</div>
                        <div style="font-family: Archivo; font-weight: 900; font-size: 44px; color: rgba(255, 255, 255, 0.1); line-height: 1">{{ s.num }}</div>
                    </div>
                    <div style="font-family: Archivo; font-weight: 700; font-size: 21px; color: #fff; margin-top: 22px">{{ s.title }}</div>
                    <div style="font-size: 14.5px; line-height: 1.6; color: #a9b7cc; font-weight: 500; margin-top: 10px">{{ s.desc }}</div>
                </div>
            </div>
        </div>
    </section>
</template>
