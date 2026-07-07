<script setup lang="js">
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// Passed by AuthenticatedSessionController@create
defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const inputStyle =
    'width: 100%; border: 1px solid #e6eaf0; border-radius: 13px; padding: 14px 16px; font-size: 15px; font-weight: 600; font-family: Manrope; color: #0b1e3b; background: #f8fafc; outline: none; transition: border-color 0.16s';
const labelStyle = 'display: block; font-size: 12px; font-weight: 800; letter-spacing: 0.05em; color: #8494ab; margin-bottom: 8px';

const trustPoints = [
    'Live access to 250,000+ inspected vehicles',
    'One specialist from enquiry to delivery',
    'Sourced, inspected and shipped worldwide',
];
</script>

<template>
    <Head title="Login" />
    <FrontLayout>
        <section class="sm-body sm-sec">
            <div class="sm-logingrid" style="max-width: 1080px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: stretch">
                <!-- Brand panel -->
                <div class="sm-loginbrand" style="position: relative; overflow: hidden; border-radius: 24px; background: linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730); padding: 48px 40px; display: flex; flex-direction: column; justify-content: space-between">
                    <svg aria-hidden="true" viewBox="0 0 200 200" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" style="position: absolute; top: -70px; left: -70px; width: 220px; height: 220px">
                        <circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="72" /><circle cx="100" cy="100" r="94" />
                    </svg>
                    <div style="position: absolute; bottom: -120px; right: -60px; width: 360px; height: 360px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.18), transparent 70%)"></div>

                    <div style="position: relative">
                        <img src="/assets/images/site-logo.png" alt="Supreme Motors Ltd" style="height: 62px; width: auto; object-fit: contain" />
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: #cdd8e8; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; margin-top: 40px">
                            <span style="width: 22px; height: 2px; background: #e01f26"></span>MEMBER ACCESS
                        </div>
                        <h1 style="font-family: Archivo; font-weight: 800; font-size: 38px; letter-spacing: -0.025em; color: #fff; margin-top: 14px; line-height: 1.1">
                            Welcome back
                        </h1>
                        <p style="font-size: 15px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin-top: 12px; max-width: 380px">
                            Sign in to manage enquiries, track saved vehicles and pick up right where you left off.
                        </p>
                    </div>

                    <ul style="position: relative; list-style: none; padding: 0; margin: 32px 0 0; display: flex; flex-direction: column; gap: 14px">
                        <li v-for="p in trustPoints" :key="p" style="display: flex; align-items: flex-start; gap: 11px; font-size: 14px; font-weight: 600; color: #c2cfe2; line-height: 1.5">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e01f26" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px"><path d="M20 6L9 17l-5-5" /></svg>
                            {{ p }}
                        </li>
                    </ul>
                </div>

                <!-- Form card -->
                <div style="background: #fff; border: 1px solid #eef1f6; border-radius: 24px; padding: 44px 40px; box-shadow: rgba(11, 30, 59, 0.05) 0 8px 24px; display: flex; flex-direction: column; justify-content: center">
                    <h2 style="font-family: Archivo; font-weight: 800; font-size: 28px; letter-spacing: -0.02em; color: #0b1e3b">Sign in</h2>
                    <p style="font-size: 14.5px; line-height: 1.6; color: #5b6b82; font-weight: 500; margin-top: 6px">
                        Enter your details to access your account.
                    </p>

                    <div v-if="status" style="display: flex; align-items: center; gap: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-size: 14px; font-weight: 700; border-radius: 13px; padding: 13px 16px; margin-top: 20px">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><path d="M22 4L12 14.01l-3-3" /></svg>
                        {{ status }}
                    </div>

                    <form style="margin-top: 26px" @submit.prevent="submit">
                        <div>
                            <label :style="labelStyle">EMAIL ADDRESS</label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="you@company.com"
                                :style="inputStyle + (form.errors.email ? '; border-color: #e01f26' : '')"
                            />
                            <p v-if="form.errors.email" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ form.errors.email }}</p>
                        </div>

                        <div style="margin-top: 18px">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px">
                                <label :style="labelStyle + '; margin-bottom: 0'">PASSWORD</label>
                                <a v-if="canResetPassword" :href="route('password.request')" style="font-size: 12.5px; font-weight: 700; color: #e01f26; text-decoration: none">Forgot password?</a>
                            </div>
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Your password"
                                :style="inputStyle + (form.errors.password ? '; border-color: #e01f26' : '')"
                            />
                            <p v-if="form.errors.password" style="font-size: 12.5px; color: #e01f26; font-weight: 700; margin-top: 7px">{{ form.errors.password }}</p>
                        </div>

                        <label style="display: flex; align-items: center; gap: 10px; margin-top: 18px; cursor: pointer">
                            <input v-model="form.remember" type="checkbox" style="width: 16px; height: 16px; accent-color: #e01f26" />
                            <span style="font-size: 13.5px; color: #5b6b82; font-weight: 600">Remember me on this device</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="scp2"
                            style="display: inline-flex; align-items: center; justify-content: center; gap: 9px; width: 100%; margin-top: 24px; font-size: 15px; font-weight: 800; font-family: Manrope; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); border: none; padding: 15px 30px; border-radius: 13px; cursor: pointer; box-shadow: rgba(224, 31, 38, 0.3) 0 12px 28px; transition: transform 0.18s"
                        >
                            <svg v-if="form.processing" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="animation: sm-spin 0.7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56" stroke-linecap="round" /></svg>
                            {{ form.processing ? 'Signing in…' : 'Sign in' }}
                        </button>
                    </form>

                    <div style="display: flex; align-items: center; gap: 14px; margin: 22px 0">
                        <span style="flex: 1; height: 1px; background: #eef1f6"></span>
                        <span style="font-size: 12px; font-weight: 700; color: #8494ab">OR</span>
                        <span style="flex: 1; height: 1px; background: #eef1f6"></span>
                    </div>

                    <a
                        :href="route('auth.google.redirect')"
                        class="scpe"
                        style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; border: 1px solid #e6eaf0; background: #f8fafc; color: #33445e; font-size: 14.5px; font-weight: 700; font-family: Manrope; padding: 13px; border-radius: 13px; text-decoration: none; transition: 0.18s"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#EA4335" d="M5.266 9.765A7.077 7.077 0 0 1 12 4.909c1.69 0 3.218.6 4.418 1.582L19.91 3C17.782 1.145 15.055 0 12 0 7.27 0 3.198 2.698 1.24 6.65l4.026 3.115Z" />
                            <path fill="#34A853" d="M16.04 18.013c-1.09.703-2.474 1.078-4.04 1.078a7.077 7.077 0 0 1-6.723-4.823l-4.04 3.067A11.965 11.965 0 0 0 12 24c2.933 0 5.735-1.043 7.834-3l-3.793-2.987Z" />
                            <path fill="#4A90E2" d="M19.834 21c2.195-2.048 3.62-5.096 3.62-9 0-.71-.109-1.473-.272-2.182H12v4.637h6.436c-.317 1.559-1.17 2.766-2.395 3.558L19.834 21Z" />
                            <path fill="#FBBC05" d="M5.277 14.268A7.12 7.12 0 0 1 4.909 12c0-.782.125-1.533.357-2.235L1.24 6.65A11.934 11.934 0 0 0 0 12c0 1.92.445 3.73 1.237 5.335l4.04-3.067Z" />
                        </svg>
                        Continue with Google
                    </a>

                    <p style="text-align: center; font-size: 14px; font-weight: 600; color: #5b6b82; margin-top: 24px">
                        Don't have an account?
                        <a :href="route('register')" style="font-weight: 800; color: #0b1e3b; text-decoration: none">Register here</a>
                    </p>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>

<style scoped>
@keyframes sm-spin {
    to { transform: rotate(360deg); }
}
.sm-loginbrand :deep(input:focus),
input:focus {
    border-color: #0b1e3b;
}
@media (max-width: 860px) {
    .sm-logingrid {
        grid-template-columns: 1fr !important;
    }
    .sm-loginbrand {
        display: none !important;
    }
}
</style>
