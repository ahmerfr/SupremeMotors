<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    size: { type: Number, default: 44 },
});

// Side-profile outlines, 48x28 viewBox, stroke inherits currentColor.
const bodies = {
    'Sedan': 'M3 20l1-4.5q.4-1.5 2-1.8L13 12.5l4.5-5Q18.5 6.5 20 6.5h8.5q1.6 0 2.8 1.2L35 12l7 1.4q1.7.4 2 2l.5 3q.2 1.6-1.5 1.6',
    'Coupe': 'M3 20l.8-3.5q.3-1.4 1.8-1.8l9-2.2 6.5-5Q22 6.7 23.7 6.7h4.6q1.5 0 2.7 1L36 12l6.8 2q1.6.5 1.8 2l.2 2.4q.1 1.6-1.6 1.6',
    'Convertible': 'M3 20l.8-3.6q.3-1.5 1.9-1.8l9.5-1.8 4-5.3 1.7 4.6 15-.3 6.5 1.9q1.7.5 2 2.1l.3 2.6q.2 1.6-1.5 1.6',
    'Hatchback': 'M3 20l1-4.4q.3-1.6 2-1.9l9-1.6 4.5-4.9q1-1.2 2.6-1.2h9.4q1.7 0 2.4 1.4L37 12.8l6 1.2q1.6.3 1.8 1.9l.2 2.5q.1 1.6-1.5 1.6',
    'Wagon': 'M3 20l.8-4.6q.3-1.5 1.9-1.7L11 13l3.8-5.4q.8-1.1 2.3-1.1h17.3q1.5 0 2.3 1.1L40 12.9l3.5.8q1.5.4 1.7 1.9l.3 2.8q.1 1.6-1.6 1.6',
    'SUV': 'M3 20l.6-5.5q.2-1.6 1.8-1.9l4.9-.8 3.5-5Q14.6 5.6 16.2 5.6h16.5q1.6 0 2.5 1.2l3.6 5 4.4.9q1.6.4 1.8 2l.2 3.7q0 1.6-1.6 1.6',
    'Van / Minivan': 'M3 20V9.6Q3 7 5.6 7h11.9l6.2-1.9q1-.3 2 0l14.7 4.2Q45 10 45 14.6v3.8q0 1.6-1.6 1.6',
    'Mini Vehicle': 'M6 20l.4-8.3Q6.6 8.4 9.8 8h4.7l4-3.2q1-.8 2.3-.8h12q3 0 3.4 3.2l.8 9.1q.2 3.7-3 3.7',
    'Truck': 'M3 20V7.8Q3 6.2 4.6 6.2H15q1.4 0 2.2 1.1l3.4 4.8h5V19M25.6 8h19.8v12',
    'Bus': 'M3 20V7.4Q3 4.8 5.6 4.8h36.8Q45 4.8 45 7.4V20M3 12h42M11 4.8V12m9-7.2V12m9-7.2V12m9-7.2V12',
};
const path = computed(() => bodies[props.type] || bodies['Sedan']);
</script>

<template>
    <svg
        :width="size"
        :height="Math.round(size * 0.58)"
        viewBox="0 0 48 28"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        style="display: block; margin: 0 auto"
    >
        <path :d="path" />
        <circle cx="13" cy="21.5" r="3.4" />
        <circle cx="35.5" cy="21.5" r="3.4" />
    </svg>
</template>
