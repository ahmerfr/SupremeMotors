<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <div style="position: relative; border-radius: 28px; overflow: hidden; background: linear-gradient(115deg, #12284a, #0b1e3b 55%, #081730)">
                <!-- Red glow + giant line-art truck, echoing the body-type icon language -->
                <div style="position: absolute; top: -140px; right: -80px; width: 480px; height: 480px; border-radius: 50%; background: radial-gradient(circle, rgba(224, 31, 38, 0.22), transparent 68%)"></div>
                <svg
                    aria-hidden="true"
                    viewBox="0 0 64 40"
                    fill="none"
                    stroke="rgba(255, 255, 255, 0.09)"
                    stroke-width="1.1"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="position: absolute; right: 60px; bottom: -66px; width: 560px; height: 350px"
                >
                    <path d="M8 30.5H6.5Q4 30.5 4 28V15.4Q4 12 7.4 12h7.9q2.1 0 3.3 1.7l4.1 6h2.1v10.8M24.8 30.5V9.3H57q2.8 0 2.8 2.8v15.4q0 3-3 3h-4.4M18.5 30.5h23.1M6.8 19.3v-4.2q0-1.1 1.1-1.1h6.7l3.6 5.3Z" />
                    <circle cx="13.5" cy="31.5" r="4.8" /><circle cx="13.5" cy="31.5" r="1.9" />
                    <circle cx="46.5" cy="31.5" r="4.8" /><circle cx="46.5" cy="31.5" r="1.9" />
                </svg>

                <div class="sm-ctarow" style="position: relative; display: flex; align-items: center; justify-content: space-between; gap: 36px; padding: 62px 56px; flex-wrap: wrap">
                    <div style="max-width: 620px">
                        <h2 style="font-family: Archivo; font-weight: 800; font-size: 34px; letter-spacing: -0.02em; color: #fff; line-height: 1.12">
                            Can't find what you're looking for?
                        </h2>
                        <p style="font-size: 15.5px; line-height: 1.65; color: #a9b7cc; font-weight: 500; margin-top: 14px">
                            Tell our specialists what you need. We source cars, trucks and heavy machinery directly from Japan, China and Europe — including stock that never makes it to the site.
                        </p>
                    </div>
                    <Link
                        href="/contact-us"
                        class="scp2"
                        style="flex: 0 0 auto; display: inline-flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 800; color: #fff; background: linear-gradient(150deg, #e5262d, #c8151c); padding: 16px 30px; border-radius: 13px; box-shadow: rgba(224, 31, 38, 0.35) 0 12px 28px; transition: transform 0.18s; text-decoration: none"
                    >
                        Contact Us
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
