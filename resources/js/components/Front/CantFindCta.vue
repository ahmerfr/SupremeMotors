<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="sm-body" style="padding: 104px 24px 0">
        <div style="max-width: 1280px; margin: 0 auto">
            <div style="position: relative; border-radius: 28px; overflow: hidden; background: #081730">
                <img
                    src="/assets/images/hero-truck.jpg"
                    alt=""
                    aria-hidden="true"
                    style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 75% center"
                />
                <div style="position: absolute; inset: 0; background: linear-gradient(92deg, rgba(8, 23, 48, 0.97) 30%, rgba(8, 23, 48, 0.82) 55%, rgba(8, 23, 48, 0.45))"></div>

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
