<script setup>
import { Link } from '@inertiajs/vue3';

const benefits = [
    { icon: '✦', bg: '#FDECEC', fg: '#E01F26', title: 'Curated Vehicle Selection', desc: 'Only quality cars make it to our inventory.' },
    { icon: '✉', bg: '#EAF0FB', fg: '#2A5BD7', title: 'Simple Online Enquiry', desc: 'Reach out in seconds, no friction.' },
    { icon: '☎', bg: '#EAF6EF', fg: '#1E9E5A', title: 'Helpful Support Team', desc: 'Real people ready to guide you.' },
    { icon: '◈', bg: '#F3EDFB', fg: '#7A3FD1', title: 'Transparent Experience', desc: 'Clear details at every step.' },
];
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1180px; margin: 0 auto">
            <div class="sm-whygrid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; align-items: center">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                        <span style="width: 22px; height: 2px; background: #e01f26"></span>WHY SUPREME MOTORS
                    </div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">A buying experience<br />built on trust</h2>
                    <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 18px; max-width: 440px">
                        We focus on quality, clarity and helpful support, so every step from browsing to driving away feels confident and simple.
                    </p>
                    <Link href="/inventory" class="scp2" style="display: inline-flex; align-items: center; gap: 9px; font-size: 14.5px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #12284a, #0b1e3b); padding: 15px 26px; border-radius: 13px; margin-top: 26px; box-shadow: rgba(11, 30, 59, 0.25) 0 12px 28px; transition: transform 0.18s">Start Browsing →</Link>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px">
                    <div v-for="b in benefits" :key="b.title" class="scpd" style="background: #f8fafc; border: 1px solid #eef1f6; border-radius: 18px; padding: 24px 22px; transition: 0.2s">
                        <div :style="{ width: '46px', height: '46px', borderRadius: '12px', background: b.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '21px', color: b.fg }">{{ b.icon }}</div>
                        <div style="font-family: Archivo; font-weight: 700; font-size: 16.5px; color: #0b1e3b; margin-top: 16px; line-height: 1.25">{{ b.title }}</div>
                        <div style="font-size: 13px; line-height: 1.55; color: #6b7c95; font-weight: 600; margin-top: 8px">{{ b.desc }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
