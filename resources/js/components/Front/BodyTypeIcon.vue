<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    size: { type: Number, default: 56 },
});

// Side-profile outlines with window detail, 64x40 viewBox, stroke inherits
// currentColor. Each entry: body/window paths + per-type wheel x positions.
const bodies = {
    'Sedan': {
        d: 'M10 30.5H7Q4 30.5 4 27.5V26Q4 23.5 7 23l6.5-1.2 6.5-7.2Q21.5 13 23.7 13h12.8q2.2 0 3.7 1.6l6.3 6.7 10.3 1.7q3.2.6 3.2 3.4v1.1q0 3-3 3h-3.8M22.2 30.5h19.6M21 21l4.7-5.3q.8-.8 1.9-.8h2.2V21ZM32.8 14.9h3.1q1.1 0 1.9.8l4.5 4.9-9.5.4Z',
        w: [16.5, 47.5],
    },
    'Coupe': {
        d: 'M10.5 30.5H7.5Q4.5 30.5 4.5 27.5v-.9q0-2.4 2.9-3l8.1-1.6 9.5-7.3q2.8-2 6.3-2h3q2.5 0 4.5 1.7l5.5 5.5 12.5 2.8q3.2.7 3.2 3.5v1.3q0 3-3 3h-3.7M22.7 30.5h19.1M26.7 20.7l4.7-5.8h2.8l5.6 5Z',
        w: [17, 47.5],
    },
    'Convertible': {
        d: 'M10 30.5H7Q4 30.5 4 27.5v-1q0-2.5 3-3l7-1.2h31.5l11.5 1q3 .6 3 3.2v1q0 3-3 3h-3.8M22.2 30.5h19.6M28.5 22.3l5.1-7.1M31.6 22.3l4.6-6.3M40 22.3q2-2.7 4.4-.2',
        w: [16.5, 47.5],
    },
    'Hatchback': {
        d: 'M10 30.5H7Q4 30.5 4 27.5V26q0-2.4 3-3l6-1.1 6.3-7.7q1.4-1.6 3.6-1.6h12.5q2.2 0 3.2 1.7l5 7.7 6.8 1q2.9.5 3.1 3.2l.1 1.3q.2 3-2.8 3H51M22.2 30.5h17.5M20.3 20.6l4.4-5.6q.7-.6 1.7-.6h2.4v6.2ZM31.8 14.4H35q1 0 1.6.9l3.4 5.3h-8.2Z',
        w: [16.5, 45.5],
    },
    'Wagon': {
        d: 'M10 30.5H7Q4 30.5 4 27.5V26q0-2.4 3-3l6-1.1 6-7.7q1.4-1.6 3.6-1.6h20.9q2.1 0 3.1 1.8l4 7.2 6.3 1.2q3.1.6 3.1 3.4v1.3q0 3-3 3h-3.8M22.2 30.5h19.6M20 20.6l4.4-5.6q.7-.6 1.7-.6h2.5v6.2ZM31.4 14.4h6.4v6.2h-6.4ZM40.6 14.4H43q1.1 0 1.7 1l2.9 5.2h-7Z',
        w: [16.5, 47.5],
    },
    'SUV': {
        d: 'M10 30H7.5Q4.5 30 4.5 27v-2.2q0-2.4 2.9-2.9l3.6-.7 4.6-8.5Q16.6 11 18.7 11H41q2.1 0 3.3 1.8l5.3 7.8 7.4 1.3q3 .6 3 3.3V27q0 3-3 3h-3.8M22.2 30h19.6M17.5 9.3h24M16.7 19.4l3.3-6.1h6.3v6.1ZM29 13.3h6.8v6.1H29ZM38.5 13.3h2.1q1.1 0 1.7 1l3.3 5.1h-7.1Z',
        w: [16.5, 47.5],
    },
    'Van / Minivan': {
        d: 'M8.5 30.5H6.5Q4 30.5 4 28v-7.5q0-1.9 1-3.4l3.6-5.2q1.3-1.7 3.5-1.7h42.4q5 0 5 5v12.3q0 3-3 3h-3.7M19.8 30.5h23.4M6.8 18.6 10.6 13q.6-.8 1.6-.8h4.2v6.4ZM19.4 12.2h12.1v6.4H19.4ZM34.5 12.2h12v6.4h-12Z',
        w: [15, 48],
    },
    'Mini Vehicle': {
        d: 'M11 30.5H9Q6.5 30.5 6.5 28v-4.4q0-2.2 1.4-3.7l4.7-7.5q1.1-1.6 3.2-1.6H44q3.5 0 4.3 3.4l1.6 6.4 3.5.9q2.5.7 2.5 3.3V28q0 2.5-2.5 2.5h-2.5M23.3 30.5h15.4M11.3 19.2 15.2 13h6.4v6.2ZM24.6 13H42l1.5 6.2H24.6Z',
        w: [17.5, 44.5],
    },
    'Truck': {
        d: 'M8 30.5H6.5Q4 30.5 4 28V15.4Q4 12 7.4 12h7.9q2.1 0 3.3 1.7l4.1 6h2.1v10.8M24.8 30.5V9.3H57q2.8 0 2.8 2.8v15.4q0 3-3 3h-4.4M18.5 30.5h23.1M6.8 19.3v-4.2q0-1.1 1.1-1.1h6.7l3.6 5.3Z',
        w: [13.5, 46.5],
    },
    'Bus': {
        d: 'M8.5 30.5H6.5Q4 30.5 4 28V12.4q0-3.2 3.2-3.2h49.6q3.2 0 3.2 3.2V28q0 2.5-2.5 2.5h-2.7M19.8 30.5h21.4M7 12.8h5.8v6.8H7ZM16.2 12.8H23v6.8h-6.8ZM26.4 12.8h6.8v6.8h-6.8ZM36.6 12.8h6.8v6.8h-6.8ZM46.8 12.8H53v6.8h-6.2Z',
        w: [15, 48],
    },
};

const icon = computed(() => bodies[props.type] || bodies['Sedan']);
</script>

<template>
    <svg
        :width="size"
        :height="Math.round(size * 0.625)"
        viewBox="0 0 64 40"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        style="display: block; margin: 0 auto"
    >
        <path :d="icon.d" />
        <template v-for="x in icon.w" :key="x">
            <circle :cx="x" cy="31.5" r="4.8" />
            <circle :cx="x" cy="31.5" r="1.9" />
        </template>
    </svg>
</template>
