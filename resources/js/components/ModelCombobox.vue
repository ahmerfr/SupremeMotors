<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

// Searchable model picker. Models are make-specific and the catalogue holds
// ~62k make|model pairs, so options are fetched on demand for the selected
// make and filtered client-side. Free-typed values are kept (the admin form
// also creates brand-new products whose model may not exist yet).
const props = defineProps({
    makeId: { type: [String, Number], default: '' },
    modelValue: { type: String, default: '' },
    error: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const options = ref([]);
const query = ref(props.modelValue || '');
const open = ref(false);
const activeIndex = ref(-1);
const loading = ref(false);

const fetchModels = async (makeId) => {
    if (!makeId) { options.value = []; return; }
    loading.value = true;
    try {
        const { data } = await axios.get(route('admin.products.models'), { params: { make_id: makeId } });
        options.value = data.models || [];
    } catch (e) {
        options.value = [];
    } finally {
        loading.value = false;
    }
};

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q ? options.value.filter((m) => m.toLowerCase().includes(q)) : options.value;
    return list.slice(0, 100); // cap render for very large makes
});

const onInput = (e) => {
    query.value = e.target.value;
    open.value = true;
    activeIndex.value = -1;
    emit('update:modelValue', query.value); // free-typed value is valid
};

const select = (m) => {
    query.value = m;
    emit('update:modelValue', m);
    open.value = false;
};

const close = () => { setTimeout(() => { open.value = false; }, 150); };

const onKeydown = (e) => {
    if (!open.value && e.key === 'ArrowDown') { open.value = true; e.preventDefault(); return; }
    if (e.key === 'ArrowDown') {
        activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1);
        e.preventDefault();
    } else if (e.key === 'ArrowUp') {
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        e.preventDefault();
    } else if (e.key === 'Enter') {
        if (open.value && activeIndex.value >= 0 && filtered.value[activeIndex.value]) {
            select(filtered.value[activeIndex.value]);
            e.preventDefault();
        }
    } else if (e.key === 'Escape') {
        open.value = false;
    }
};

// parent may reset the model (e.g. when the make changes) — mirror it here
watch(() => props.modelValue, (v) => { if (v !== query.value) query.value = v || ''; });
watch(() => props.makeId, (id) => { fetchModels(id); });

onMounted(() => fetchModels(props.makeId));
</script>

<template>
    <div class="relative mt-1">
        <input
            type="text"
            :value="query"
            @input="onInput"
            @focus="open = true"
            @blur="close"
            @keydown="onKeydown"
            :disabled="!makeId"
            :placeholder="makeId ? 'Search or type model…' : 'Select a Make first'"
            autocomplete="off"
            class="p-2 w-full rounded-lg border border-zinc-200 bg-white text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white transition duration-300 disabled:opacity-50"
            :class="{ 'border-red-500': error }"
        />
        <ul
            v-if="open && filtered.length"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >
            <li
                v-for="(m, i) in filtered"
                :key="m"
                @mousedown.prevent="select(m)"
                :class="[
                    'cursor-pointer px-3 py-2 text-sm text-zinc-700 dark:text-zinc-200',
                    i === activeIndex ? 'bg-zinc-100 dark:bg-zinc-800' : 'hover:bg-zinc-50 dark:hover:bg-zinc-900',
                ]"
            >{{ m }}</li>
        </ul>
        <p
            v-else-if="open && makeId && !loading && query"
            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-400 shadow-lg dark:border-zinc-700 dark:bg-zinc-950"
        >No match — "{{ query }}" will be saved as a new model.</p>
    </div>
</template>
