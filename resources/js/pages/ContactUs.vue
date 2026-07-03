<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import Testimonials from '@/components/Front/Testimonials.vue';

defineProps({
    auth: Object,
});

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    consent: false,
});

const errors = ref({});
const submitting = ref(false);
const success = ref('');
const failure = ref('');

const submit = async () => {
    if (submitting.value) return;
    submitting.value = true;
    success.value = '';
    failure.value = '';
    errors.value = {};
    try {
        const { data } = await axios.post('/contact-save', {
            name: `${form.first_name} ${form.last_name}`.trim(),
            email: form.email,
            phone: form.phone,
            subject: form.subject,
            message: form.message,
            consent: form.consent,
        });
        success.value = data.message;
        Object.assign(form, { first_name: '', last_name: '', email: '', phone: '', subject: '', message: '', consent: false });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
            failure.value = 'Please correct the highlighted fields.';
        } else {
            failure.value = e.response?.data?.message || 'Network error — please try again.';
        }
    } finally {
        submitting.value = false;
    }
};

// name-field errors come back under "name"
const nameError = () => errors.value.name?.[0];

const contactRows = [
    {
        title: 'Our Email',
        lines: [{ text: 'info@suprememotors.ltd', href: 'mailto:info@suprememotors.ltd' }],
        d: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z M22 6l-10 7L2 6',
    },
    {
        title: 'Our Contact Numbers',
        lines: [
            { text: '+44 7516 916622', href: 'tel:+447516916622' },
            { text: '+1 647 846 3886', href: 'tel:+16478463886' },
        ],
        d: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z',
    },
    {
        title: 'Our Location',
        lines: [
            { text: 'Unit 1603, 16th Floor, The L. Plaza' },
            { text: "367–375 Queen's Road Central" },
            { text: 'Sheung Wan, Hong Kong' },
        ],
        d: 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    },
];

const socials = [
    {
        title: 'Facebook',
        href: 'https://www.facebook.com/people/Supreme-Motors/61575850660503/',
        d: 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 5 3.66 9.13 8.44 9.88v-6.99h-2.54V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99C18.34 21.13 22 17 22 12z',
    },
    {
        title: 'Instagram',
        href: 'https://www.instagram.com/suprememotors.ltd',
        d: 'M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 2.7.27.27 2.69.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95C23.73 2.7 21.31.27 16.95.07 15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84zm0 10.15A3.99 3.99 0 1 1 16 12a3.99 3.99 0 0 1-4 3.99zm6.41-11.85a1.44 1.44 0 1 0 1.44 1.44 1.44 1.44 0 0 0-1.44-1.44z',
    },
    {
        title: 'LinkedIn',
        href: 'https://www.linkedin.com/company/suprememotorsltd/',
        d: 'M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45zM22.23 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.46c.98 0 1.77-.77 1.77-1.72V1.72C24 .77 23.21 0 22.23 0z',
    },
];

const inputStyle =
    'width: 100%; border: 1px solid #e6eaf0; border-radius: 13px; padding: 14px 16px; font-size: 15px; font-weight: 600; font-family: Manrope; color: #0b1e3b; background: #f8fafc; outline: none; transition: border-color 0.16s';
const labelStyle = 'display: block; font-size: 12px; font-weight: 800; letter-spacing: 0.05em; color: #8494ab; margin-bottom: 8px';
</script>

<template>
    <Head title="Contact Us" />

    <div class="flex flex-col min-h-screen">
        <FrontLayout>
            <!-- Banner -->
            <section class="sm-body" style="padding: 40px 24px 0">
                <div style="max-width: 1280px; margin: 0 auto">
                    <div style="position: relative; overflow: hidden; border-radius: 28px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 64px 32px; text-align: center">
                        <!-- Corner ring decorations -->
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" style="position: absolute; top: -70px; left: -70px; width: 220px; height: 220px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" style="position: absolute; bottom: -70px; right: -70px; width: 220px; height: 220px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>
                        <div style="position: absolute; top: -120px; right: 10%; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>

                        <div style="position: relative">
                            <div style="display: inline-flex; align-items: center; gap: 9px; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); padding: 7px 16px; border-radius: 100px">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #e01f26"></span>
                                <span style="font-size: 12px; font-weight: 800; letter-spacing: 0.08em; color: #cdd8e8">WRITE TO US</span>
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                Get in touch
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 520px">
                                A real specialist answers every enquiry — usually within one working day.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Form + details -->
            <section class="sm-body" style="padding: 64px 24px 0">
                <div class="sm-congrid" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 1.35fr 1fr; gap: 20px; align-items: start">
                    <!-- Form card -->
                    <div style="background: #fff; border: 1px solid #eef1f6; border-radius: 18px; padding: 40px; box-shadow: rgba(11, 30, 59, 0.04) 0 4px 14px">
                        <h2 style="font-family: Archivo; font-weight: 800; font-size: 28px; letter-spacing: -0.02em; color: #0b1e3b">Let's talk</h2>
                        <p style="font-size: 15px; line-height: 1.6; color: #5b6b82; font-weight: 500; margin-top: 8px">
                            Tell us what you're looking for — a specific vehicle, a machine spec, or a full container plan.
                        </p>

                        <div v-if="success" style="display: flex; align-items: center; gap: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-size: 14px; font-weight: 700; border-radius: 13px; padding: 14px 16px; margin-top: 22px">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><path d="M22 4L12 14.01l-3-3" /></svg>
                            {{ success }}
                        </div>

                        <form style="margin-top: 26px" @submit.prevent="submit">
                            <div class="sm-connames" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px">
                                <div>
                                    <label :style="labelStyle">FIRST NAME</label>
                                    <input v-model="form.first_name" type="text" :style="inputStyle + (nameError() ? '; border-color: #e01f26' : '')" placeholder="John" />
                                </div>
                                <div>
                                    <label :style="labelStyle">LAST NAME</label>
                                    <input v-model="form.last_name" type="text" :style="inputStyle" placeholder="Smith" />
                                </div>
                            </div>
                            <p v-if="nameError()" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ nameError() }}</p>

                            <div class="sm-connames" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px">
                                <div>
                                    <label :style="labelStyle">EMAIL</label>
                                    <input v-model="form.email" type="email" :style="inputStyle + (errors.email ? '; border-color: #e01f26' : '')" placeholder="you@company.com" />
                                    <p v-if="errors.email" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ errors.email[0] }}</p>
                                </div>
                                <div>
                                    <label :style="labelStyle">PHONE</label>
                                    <input v-model="form.phone" type="tel" :style="inputStyle + (errors.phone ? '; border-color: #e01f26' : '')" placeholder="+44 …" />
                                    <p v-if="errors.phone" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ errors.phone[0] }}</p>
                                </div>
                            </div>

                            <div style="margin-top: 18px">
                                <label :style="labelStyle">SUBJECT</label>
                                <input v-model="form.subject" type="text" :style="inputStyle + (errors.subject ? '; border-color: #e01f26' : '')" placeholder="e.g. Quote for 2 × Hiace vans to Felixstowe" />
                                <p v-if="errors.subject" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ errors.subject[0] }}</p>
                            </div>

                            <div style="margin-top: 18px">
                                <label :style="labelStyle">MESSAGE</label>
                                <textarea v-model="form.message" rows="5" :style="inputStyle + '; resize: vertical; min-height: 120px' + (errors.message ? '; border-color: #e01f26' : '')" placeholder="Budget, destination port, timeline — the more detail, the faster the quote."></textarea>
                                <p v-if="errors.message" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ errors.message[0] }}</p>
                            </div>

                            <label style="display: flex; align-items: flex-start; gap: 11px; margin-top: 20px; cursor: pointer">
                                <input v-model="form.consent" type="checkbox" style="margin-top: 3px; width: 16px; height: 16px; accent-color: #e01f26" />
                                <span style="font-size: 13.5px; line-height: 1.55; color: #5b6b82; font-weight: 500">
                                    I agree that Supreme Motors Ltd may store my details to respond to this enquiry. <span style="color: #e01f26">*</span>
                                </span>
                            </label>
                            <p v-if="errors.consent" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ errors.consent[0] }}</p>

                            <p v-if="failure" style="font-size: 13.5px; color: #e01f26; font-weight: 700; margin-top: 16px">{{ failure }}</p>

                            <button
                                type="submit"
                                :disabled="submitting"
                                class="scp2"
                                style="display: inline-flex; align-items: center; gap: 9px; margin-top: 24px; font-size: 15px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); border: none; padding: 15px 30px; border-radius: 13px; cursor: pointer; box-shadow: rgba(224, 31, 38, 0.32) 0 12px 28px; transition: transform 0.18s"
                            >
                                {{ submitting ? 'Sending…' : 'Send Message' }}
                                <svg v-if="!submitting" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                            </button>
                        </form>
                    </div>

                    <!-- Details column -->
                    <div style="display: flex; flex-direction: column; gap: 20px">
                        <div style="border-radius: 18px; overflow: hidden; border: 1px solid #eef1f6; position: relative; height: 240px">
                            <img src="/assets/images/cta-vehicle.jpg" alt="Supreme Motors stock" style="width: 100%; height: 100%; object-fit: cover; object-position: 70% 55%" />
                            <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(8, 23, 48, 0) 45%, rgba(8, 23, 48, 0.55))"></div>
                            <div style="position: absolute; left: 18px; bottom: 14px; font-family: Archivo; font-weight: 700; font-size: 14px; color: #fff">
                                Sourced. Inspected. Shipped.
                            </div>
                        </div>

                        <div style="background: #fff; border: 1px solid #eef1f6; border-radius: 18px; padding: 26px; box-shadow: rgba(11, 30, 59, 0.04) 0 4px 14px; display: flex; flex-direction: column; gap: 20px">
                            <div v-for="row in contactRows" :key="row.title" style="display: flex; gap: 14px; align-items: flex-start">
                                <span style="flex: 0 0 auto; width: 44px; height: 44px; border-radius: 13px; background: #f4f6f9; border: 1px solid #eef1f6; display: flex; align-items: center; justify-content: center">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path :d="row.d" /></svg>
                                </span>
                                <div style="padding-top: 1px">
                                    <div style="font-family: Archivo; font-weight: 700; font-size: 15px; color: #0b1e3b">{{ row.title }}</div>
                                    <div style="margin-top: 4px; display: flex; flex-direction: column; gap: 2px">
                                        <component
                                            :is="line.href ? 'a' : 'div'"
                                            v-for="line in row.lines"
                                            :key="line.text"
                                            :href="line.href"
                                            style="font-size: 14px; font-weight: 600; color: #5b6b82; line-height: 1.5; text-decoration: none"
                                        >{{ line.text }}</component>
                                    </div>
                                </div>
                            </div>

                            <div style="border-top: 1px solid #f1f3f7; padding-top: 20px">
                                <div style="font-family: Archivo; font-weight: 700; font-size: 15px; color: #0b1e3b">Follow us</div>
                                <div style="display: flex; gap: 10px; margin-top: 12px">
                                    <a
                                        v-for="s in socials"
                                        :key="s.title"
                                        :href="s.href"
                                        :title="s.title"
                                        :aria-label="s.title"
                                        target="_blank"
                                        rel="noopener"
                                        class="scpe"
                                        style="width: 40px; height: 40px; border-radius: 11px; background: #f8fafc; border: 1px solid #e6eaf0; display: flex; align-items: center; justify-content: center; color: #33445e; transition: 0.18s; text-decoration: none"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path :d="s.d" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Testimonials />
        </FrontLayout>
    </div>
</template>
