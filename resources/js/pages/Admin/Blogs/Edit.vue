<script setup>
import PageHeader from '@/components/admin/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Image as ImageIcon, Loader2 } from 'lucide-vue-next';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { onMounted, ref } from 'vue';

const props = defineProps({
    blog: Object,
});

const breadcrumbs = [
    { title: 'Blogs', href: '/admin/blogs' },
    { title: 'Edit', href: `/admin/blogs/edit/${props.blog.id}` },
];

const form = useForm({
    id: props.blog.id,
    title: props.blog.title || '',
    short_description: props.blog.short_description || '',
    content: props.blog.content || '',
    cover_image: null,
    category: props.blog.category || '',
    tags: props.blog.tags || [],
    publish_status: props.blog.publish_status || 'draft',
    meta_title: props.blog.meta_title || '',
    meta_description: props.blog.meta_description || '',
    meta_keywords: props.blog.meta_keywords || '',
});

const tagsInput = ref((props.blog.tags || []).join(', '));
const coverPreview = ref(props.blog.cover_image ? `/storage/${props.blog.cover_image}` : null);
let editor = null;

onMounted(() => {
    editor = new Quill('#blog-content', {
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'link'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'image'],
            ],
        },
        theme: 'snow',
    });
    if (props.blog.content) editor.root.innerHTML = props.blog.content;
});

const previewCover = (event) => {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = () => (coverPreview.value = reader.result);
        reader.readAsDataURL(file);
        form.cover_image = file;
    }
};

const submit = () => {
    form.content = editor.getSemanticHTML();
    form.tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean);
    form.post(route('admin.blogs.update'), { forceFormData: true });
};

const inputClass =
    'mt-1 h-11 w-full rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-900 focus:border-[#8e2527] focus:outline-none focus:ring-1 focus:ring-[#8e2527] dark:border-zinc-700 dark:bg-zinc-950 dark:text-white';
</script>

<template>
    <Head title="Blogs - Edit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-6">
            <PageHeader :title="`Edit: ${blog.title}`" :subtitle="`/${blog.slug}`" />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Title</label>
                            <input v-model="form.title" type="text" :class="[inputClass, form.errors.title && 'border-red-500']" />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Short Description</label>
                            <textarea v-model="form.short_description" rows="2" :class="[inputClass, 'h-auto py-2']"></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Category</label>
                            <input v-model="form.category" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Tags (comma separated)</label>
                            <input v-model="tagsInput" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Status</label>
                            <select v-model="form.publish_status" :class="inputClass">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Cover Image</label>
                            <label class="mt-1 flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-dashed border-zinc-300 px-3 text-sm text-zinc-500 transition-colors hover:border-[#8e2527] dark:border-zinc-700">
                                <ImageIcon class="h-4 w-4" /> {{ form.cover_image ? form.cover_image.name : 'Replace cover…' }}
                                <input type="file" accept="image/*" class="hidden" @change="previewCover" />
                            </label>
                            <p v-if="form.errors.cover_image" class="mt-1 text-sm text-red-500">{{ form.errors.cover_image }}</p>
                        </div>
                    </div>
                    <img v-if="coverPreview" :src="coverPreview" alt="" class="h-48 w-full rounded-xl object-cover" />
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <label class="mb-2 block text-sm font-bold text-zinc-700 dark:text-zinc-300">Content</label>
                    <div id="blog-content" class="min-h-64 rounded-b-xl [&_.ql-editor]:min-h-64"></div>
                    <p v-if="form.errors.content" class="mt-1 text-sm text-red-500">{{ form.errors.content }}</p>
                </div>

                <div class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <h3 class="text-sm font-black uppercase tracking-wider text-zinc-400">SEO (optional)</h3>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Meta Title</label>
                            <input v-model="form.meta_title" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Meta Keywords</label>
                            <input v-model="form.meta_keywords" type="text" :class="inputClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Meta Description</label>
                            <textarea v-model="form.meta_description" rows="2" :class="[inputClass, 'h-auto py-2']"></textarea>
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#8e2527] font-bold text-white transition-colors hover:bg-[#a32c2f] disabled:opacity-60"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Save Changes
                </button>
            </form>
        </div>
    </AppLayout>
</template>
