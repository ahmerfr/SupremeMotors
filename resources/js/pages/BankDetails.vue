<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import SectionDivider from '@/components/Front/SectionDivider.vue';

const fields = [
    { label: 'Account Name', value: 'Supreme Motor Equipments Limited', note: 'If the name field is too short, continue it in the address line.' },
    { label: 'Account Number', value: '63003711840' },
    { label: 'SWIFT / BIC', value: 'CHASHKHH', note: 'Use CHASHKHHXXX where 11 characters are required.' },
    { label: 'Bank Name', value: 'JPMorgan Chase Bank N.A., Hong Kong Branch' },
    { label: 'Bank Address', value: '18/F, 20/F, 22-29/F, Chater House, 8 Connaught Road Central, Hong Kong' },
];

const smallFields = [
    { label: 'Bank Code', value: '007' },
    { label: 'Branch Code', value: '863' },
    { label: 'Country / Region', value: 'Hong Kong' },
    { label: 'Account Type', value: 'Business Account' },
];

const steps = [
    {
        title: 'Get your invoice',
        body: 'Every payment starts with a commercial invoice from our team stating the vehicle, price, incoterm and destination.',
    },
    {
        title: 'Send the wire',
        body: 'Pay by SWIFT (T/T), or CHATS/ACH if you bank in Hong Kong. Include the memo: [Buyer Name][Invoice Number][Product].',
    },
    {
        title: 'Share the receipt',
        body: 'Email the transfer copy to info@suprememotors.ltd. Documents are issued to your name once funds clear — typically 1–2 business days.',
    },
];

const currencies = [
    { code: 'USD', name: 'US Dollar' },
    { code: 'HKD', name: 'Hong Kong Dollar' },
    { code: 'EUR', name: 'Euro' },
    { code: 'GBP', name: 'British Pound' },
    { code: 'JPY', name: 'Japanese Yen' },
    { code: 'CNY', name: 'Chinese Yuan' },
    { code: 'CAD', name: 'Canadian Dollar' },
    { code: 'AUD', name: 'Australian Dollar' },
    { code: 'CHF', name: 'Swiss Franc' },
    { code: 'SGD', name: 'Singapore Dollar' },
    { code: 'NZD', name: 'New Zealand Dollar' },
    { code: 'SEK', name: 'Swedish Krona' },
    { code: 'NOK', name: 'Norwegian Krone' },
    { code: 'DKK', name: 'Danish Krone' },
    { code: 'ZAR', name: 'South African Rand' },
];

const toast = ref('');
let toastTimer = null;
const copy = (text, label) => {
    navigator.clipboard.writeText(text);
    toast.value = `${label} copied`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = ''), 2200);
};

const copyAll = () => {
    const all = [...fields, ...smallFields].map((f) => `${f.label}: ${f.value}`).join('\n');
    copy(all, 'All bank details');
};
</script>

<template>
    <Head title="Bank Details" />

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
                                <span style="width: 22px; height: 2px; background: #e01f26"></span>PAYMENTS<span style="width: 22px; height: 2px; background: #e01f26"></span>
                            </div>
                            <h1 style="font-family: Archivo; font-weight: 800; font-size: 44px; letter-spacing: -0.025em; color: #fff; margin-top: 16px; line-height: 1.08">
                                Bank Details
                            </h1>
                            <p style="font-size: 16px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin: 12px auto 0; max-width: 540px">
                                Wire securely to our registered Hong Kong business account — the same details printed on every invoice we issue.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Wire details + how to pay -->
            <section class="sm-body sm-sec">
                <div class="sm-faqgrid" style="max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 1.35fr 0.85fr; gap: 48px; align-items: start">
                    <!-- Account card -->
                    <div style="position: relative; overflow: hidden; border-radius: 24px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 36px 38px">
                        <div style="position: absolute; top: -140px; right: -80px; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.14), transparent 70%)"></div>
                        <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1.5" style="position: absolute; bottom: -80px; left: -80px; width: 240px; height: 240px">
                            <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                        </svg>

                        <div style="position: relative">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap">
                                <div>
                                    <div style="font-family: Archivo; font-weight: 800; font-size: 24px; letter-spacing: -0.01em; color: #fff">Wire transfer details</div>
                                    <p style="font-size: 14px; font-weight: 500; color: #a9b7cc; margin-top: 5px">SWIFT (T/T) international &middot; CHATS / ACH for Hong Kong local</p>
                                </div>
                                <button
                                    type="button"
                                    class="scp2"
                                    style="display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 800; color: #fff; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.16); padding: 10px 18px; border-radius: 100px; cursor: pointer; transition: transform 0.18s"
                                    @click="copyAll"
                                >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                                    Copy all
                                </button>
                            </div>

                            <div style="margin-top: 26px; display: flex; flex-direction: column">
                                <div
                                    v-for="f in fields"
                                    :key="f.label"
                                    style="display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 15px 0; border-top: 1px solid rgba(255, 255, 255, 0.09)"
                                >
                                    <div>
                                        <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc">{{ f.label.toUpperCase() }}</div>
                                        <div style="font-family: Archivo; font-weight: 700; font-size: 16.5px; color: #fff; margin-top: 4px; line-height: 1.45">{{ f.value }}</div>
                                        <div v-if="f.note" style="font-size: 12.5px; font-weight: 500; color: #7487a3; margin-top: 3px">{{ f.note }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="scp2"
                                        :aria-label="`Copy ${f.label}`"
                                        style="flex: 0 0 auto; width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.18s"
                                        @click="copy(f.value, f.label)"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#cdd8e8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                                    </button>
                                </div>

                                <div class="sm-bankpairs" style="display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid rgba(255, 255, 255, 0.09)">
                                    <div v-for="f in smallFields" :key="f.label" style="padding: 15px 0">
                                        <div style="font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: #8ea0bc">{{ f.label.toUpperCase() }}</div>
                                        <div style="font-family: Archivo; font-weight: 700; font-size: 16.5px; color: #fff; margin-top: 4px">{{ f.value }}</div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 10px; display: flex; align-items: flex-start; gap: 10px; background: rgba(224, 31, 38, 0.1); border: 1px solid rgba(224, 31, 38, 0.3); border-radius: 14px; padding: 14px 16px">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f2777c" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex: 0 0 auto; margin-top: 2px"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                                <p style="font-size: 13.5px; line-height: 1.6; color: #e8b8ba; font-weight: 500">
                                    Payment memo is required — include <span style="color: #fff; font-weight: 700">[Buyer Name][Invoice Number][Product]</span> so your transfer is matched to your order without delay.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- How to pay rail -->
                    <div class="sm-faqside">
                        <h2 style="font-family: Archivo; font-weight: 800; font-size: 26px; letter-spacing: -0.02em; color: #0b1e3b; margin: 0">How paying works</h2>
                        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 18px">
                            <div v-for="(s, i) in steps" :key="s.title" style="display: flex; gap: 16px">
                                <div style="flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; background: #0b1e3b; color: #fff; font-family: Archivo; font-weight: 800; font-size: 14px; display: inline-flex; align-items: center; justify-content: center">
                                    {{ i + 1 }}
                                </div>
                                <div>
                                    <div style="font-family: Archivo; font-weight: 800; font-size: 16.5px; color: #0b1e3b">{{ s.title }}</div>
                                    <p style="font-size: 14.5px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 4px">{{ s.body }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Verify warning -->
                        <div style="margin-top: 26px; border-left: 3px solid #e01f26; background: #fdf1f1; border-radius: 0 16px 16px 0; padding: 18px 20px">
                            <div style="font-family: Archivo; font-weight: 800; font-size: 15.5px; color: #0b1e3b">Verify before you pay</div>
                            <p style="font-size: 14px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 6px">
                                These details change only on a re-issued invoice — never by chat, email or phone. If anything you receive differs from this page, stop and contact us first.
                            </p>
                        </div>

                        <!-- Help card -->
                        <div style="position: relative; overflow: hidden; margin-top: 20px; border-radius: 22px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 26px">
                            <div style="position: absolute; bottom: -100px; right: -60px; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>
                            <div style="position: relative">
                                <div style="font-family: Archivo; font-weight: 800; font-size: 19px; color: #fff">Need help with a payment?</div>
                                <p style="font-size: 14px; line-height: 1.6; color: #a9b7cc; font-weight: 500; margin-top: 6px">Our finance team confirms receipt and answers wire questions within one working day.</p>
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
                </div>
            </section>

            <SectionDivider />

            <!-- Accepted currencies -->
            <section class="sm-body sm-sec">
                <div style="max-width: 1280px; margin: 0 auto">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; color: #8895ab">
                        <span style="width: 22px; height: 2px; background: #e01f26"></span>CURRENCIES
                    </div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 32px; letter-spacing: -0.02em; color: #0b1e3b; margin-top: 12px">We accept 15 currencies</h2>
                    <p style="font-size: 15.5px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 8px; max-width: 560px">
                        Wire in whichever is easiest for you — your invoice states the currency and the exact amount due.
                    </p>

                    <div class="sm-curgrid" style="margin-top: 28px">
                        <div
                            v-for="c in currencies"
                            :key="c.code"
                            style="display: flex; align-items: center; gap: 13px; background: #fff; border: 1px solid #e6eaf0; border-radius: 16px; padding: 15px 17px"
                        >
                            <span style="flex: 0 0 auto; width: 40px; height: 40px; border-radius: 50%; background: #eef2f7; display: inline-flex; align-items: center; justify-content: center; font-family: Archivo; font-weight: 800; font-size: 12.5px; letter-spacing: 0.03em; color: #33445e">
                                {{ c.code.slice(0, 2) }}
                            </span>
                            <div>
                                <div style="font-family: Archivo; font-weight: 800; font-size: 15.5px; color: #0b1e3b">{{ c.code }}</div>
                                <div style="font-size: 12.5px; font-weight: 600; color: #8895ab">{{ c.name }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Copy toast -->
            <Transition name="sm-toast">
                <div
                    v-if="toast"
                    style="position: fixed; bottom: 26px; left: 50%; transform: translateX(-50%); z-index: 90; display: flex; align-items: center; gap: 10px; background: #0b1e3b; color: #fff; font-family: Archivo; font-weight: 700; font-size: 14px; padding: 13px 22px; border-radius: 100px; box-shadow: rgba(11, 30, 59, 0.35) 0 14px 34px"
                >
                    <span style="width: 20px; height: 20px; border-radius: 50%; background: #22a06b; display: inline-flex; align-items: center; justify-content: center">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                    </span>
                    {{ toast }}
                </div>
            </Transition>
        </FrontLayout>
    </div>
</template>

<style scoped>
.sm-toast-enter-active,
.sm-toast-leave-active {
    transition: all 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}
.sm-toast-enter-from,
.sm-toast-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(14px);
}
</style>
