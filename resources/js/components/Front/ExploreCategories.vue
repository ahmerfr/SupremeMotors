<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: [Array, Object], default: () => [] },
});

const showAll = ref(false);
const sorted = computed(() => [...(props.categories || [])].sort((a, b) => b.products_count - a.products_count));
const visible = computed(() => (showAll.value ? sorted.value : sorted.value.slice(0, 7)));
const hiddenCount = computed(() => Math.max(0, sorted.value.length - 7));
</script>

<template>
    <section class="sm-body sm-sec">
        <div style="max-width: 1280px; margin: 0 auto">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; color: #e01f26; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em">
                    <span style="width: 22px; height: 2px; background: #e01f26"></span>OUR CATEGORIES
                </div>
                <h2 style="font-family: Archivo; font-weight: 800; font-size: 40px; letter-spacing: -0.025em; color: #0b1e3b; margin-top: 12px; line-height: 1.08">
                    Explore our categories
                </h2>
                <p style="font-size: 16px; line-height: 1.65; color: #5b6b82; font-weight: 500; margin-top: 14px; max-width: 520px">
                    Cars, trucks, machinery and parts — every corner of the yard, one click away.
                </p>
            </div>

            <div class="sm-catgrid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 36px">
                <Link
                    v-for="c in visible"
                    :key="c.id"
                    :href="`/inventory?category=${c.id}`"
                    class="scpd"
                    style="display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #eef1f6; border-radius: 16px; padding: 18px 20px; transition: 0.2s; text-decoration: none; box-shadow: rgba(11, 30, 59, 0.04) 0 4px 14px"
                >
                    <span style="flex: 0 0 auto; width: 52px; height: 52px; border-radius: 14px; background: #f4f6f9; display: flex; align-items: center; justify-content: center; overflow: hidden">
                        <img v-if="c.image" :src="'/storage/' + c.image" :alt="c.cat_title" loading="lazy" style="width: 34px; height: 34px; object-fit: contain" />
                        <svg v-else width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8494ab" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16.5V14a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2.5"/><path d="M2 16.5h20"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/><path d="M14 12V8a2 2 0 0 1 2-2h2.6L22 10v6.5"/></svg>
                    </span>
                    <span style="min-width: 0">
                        <span style="display: block; font-family: Archivo; font-weight: 700; font-size: 15.5px; color: #0b1e3b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ c.cat_title }}</span>
                        <span style="display: block; font-size: 12.5px; font-weight: 700; color: #8494ab; margin-top: 3px">{{ Number(c.products_count).toLocaleString() }} in stock</span>
                    </span>
                    <svg style="margin-left: auto; flex: 0 0 auto" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c3cdda" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
                </Link>

                <button
                    v-if="hiddenCount > 0"
                    type="button"
                    class="scpd"
                    style="display: flex; align-items: center; justify-content: center; gap: 9px; background: linear-gradient(150deg, #12284a, #0b1e3b); border: 1px solid #0b1e3b; border-radius: 16px; padding: 18px 20px; cursor: pointer; transition: 0.2s; font-family: Archivo; font-weight: 700; font-size: 15px; color: #fff"
                    @click="showAll = !showAll"
                >
                    {{ showAll ? 'Show less' : `View all categories` }}
                    <span v-if="!showAll" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.16); font-size: 12px; font-weight: 800; color: #cdd8e8; padding: 3px 10px; border-radius: 100px">+{{ hiddenCount }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path :d="showAll ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6'" /></svg>
                </button>
            </div>
        </div>
    </section>
</template>
