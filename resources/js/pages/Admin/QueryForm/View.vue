<script setup>
import PageHeader from '@/components/admin/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Calendar, Mail, Phone, Percent, User } from 'lucide-vue-next';

const props = defineProps({
    query: Object,
});

const breadcrumbs = [
    { title: 'Queries', href: '/admin/query-form' },
    { title: `#${props.query.id}`, href: `/admin/query-form/view/${props.query.id}` },
];

const fields = [
    { label: 'Company', value: props.query.company, icon: Building2 },
    { label: 'Contact Name', value: props.query.contact_name, icon: User },
    { label: 'Email', value: props.query.email, icon: Mail },
    { label: 'Phone', value: props.query.phone, icon: Phone },
    { label: 'Wants Meeting', value: props.query.meeting, icon: Calendar },
    { label: 'Wants Visit', value: props.query.visit, icon: Calendar },
    { label: 'Closing %', value: props.query.closing, icon: Percent },
];
</script>

<template>
    <Head :title="`Query #${query.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-6">
            <PageHeader :title="query.company || query.contact_name" :subtitle="`Received ${new Date(query.created_at).toLocaleString('en-GB')}`">
                <template #actions>
                    <Link
                        href="/admin/query-form"
                        class="flex h-10 items-center gap-2 rounded-xl border border-zinc-200 px-4 text-sm font-bold text-zinc-700 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        <ArrowLeft class="h-4 w-4" /> Back
                    </Link>
                </template>
            </PageHeader>

            <div class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 sm:grid-cols-2 dark:border-zinc-800 dark:bg-zinc-900">
                <div v-for="f in fields" :key="f.label" class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 dark:bg-red-950">
                        <component :is="f.icon" class="h-4 w-4 text-[#8e2527]" />
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ f.label }}</div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ f.value ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-zinc-400">Message</div>
                <p class="whitespace-pre-wrap leading-relaxed text-zinc-700 dark:text-zinc-200">{{ query.message }}</p>
            </div>

            <a
                :href="`mailto:${query.email}`"
                class="mt-6 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#8e2527] font-bold text-white transition-colors hover:bg-[#a32c2f]"
            >
                <Mail class="h-4 w-4" /> Reply by Email
            </a>
        </div>
    </AppLayout>
</template>
