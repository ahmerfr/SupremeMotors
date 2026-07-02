<script setup>
import axios from 'axios';
import { ref } from 'vue';

const email = ref('');
const btnLabel = ref('Subscribe');
const error = ref('');
const busy = ref(false);

const subscribe = async () => {
    if (!email.value.trim() || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await axios.post('/newsletter/subscribe', { email: email.value });
        btnLabel.value = 'Subscribed ✓';
        email.value = '';
        setTimeout(() => (btnLabel.value = 'Subscribe'), 2500);
    } catch (e) {
        error.value = e.response?.data?.message || 'Something went wrong. Try again.';
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1180px; margin: 0 auto">
            <div class="sm-newsgrid" style="background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); border-radius: 28px; padding: 56px; position: relative; overflow: hidden; display: grid; grid-template-columns: 1.1fr 1fr; gap: 40px; align-items: center">
                <div style="position: absolute; bottom: -120px; right: -60px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.16), transparent 70%)"></div>
                <div style="position: relative">
                    <div style="display: inline-flex; align-items: center; gap: 9px; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); padding: 7px 14px; border-radius: 100px">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #e01f26"></span>
                        <span style="font-size: 12px; font-weight: 700; color: #cdd8e8">STAY IN THE LOOP</span>
                    </div>
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 34px; letter-spacing: -0.02em; color: #fff; margin-top: 18px; line-height: 1.12">Receive pricing updates,<br />shopping tips &amp; more</h2>
                    <p style="font-size: 15.5px; line-height: 1.6; color: #a9b7cc; font-weight: 500; margin-top: 14px; max-width: 400px">
                        Join our list for new arrivals, price drops and helpful buying guidance, straight to your inbox.
                    </p>
                </div>
                <div style="position: relative">
                    <div style="background: #fff; border-radius: 20px; padding: 28px; box-shadow: rgba(0, 0, 0, 0.35) 0 30px 60px">
                        <label style="display: block; font-size: 12px; font-weight: 800; letter-spacing: 0.05em; color: #8494ab; margin-bottom: 9px">EMAIL ADDRESS</label>
                        <input
                            v-model="email"
                            type="email"
                            placeholder="you@example.com"
                            style="width: 100%; border: 1px solid #e6eaf0; border-radius: 13px; padding: 15px 16px; font-size: 15px; font-weight: 600; font-family: Manrope; color: #0b1e3b; background: #f8fafc; outline: none"
                            @keyup.enter="subscribe"
                        />
                        <button
                            class="scp2"
                            :disabled="busy"
                            style="width: 100%; margin-top: 12px; height: 52px; font-size: 15px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); border: none; border-radius: 13px; cursor: pointer; box-shadow: rgba(224, 31, 38, 0.32) 0 12px 28px; transition: transform 0.18s"
                            @click="subscribe"
                        >{{ btnLabel }}</button>
                        <div v-if="error" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 12px; text-align: center">{{ error }}</div>
                        <div v-else style="font-size: 12px; color: #9aa8bd; font-weight: 600; margin-top: 12px; text-align: center">No spam. Unsubscribe anytime.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
