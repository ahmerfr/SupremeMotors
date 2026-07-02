<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Car, Folder, LayoutGrid, Mail, MessageSquareText, Newspaper, Users } from 'lucide-vue-next';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Products',
        href: '/admin/products',
        icon: Car,
    },
    {
        title: 'Categories/Makes',
        href: '/admin/categories',
        icon: Folder,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Newsletter',
        href: '/admin/newsletter',
        icon: Mail,
    },
    {
        title: 'Queries',
        href: '/admin/query-form',
        icon: MessageSquareText,
    },
    {
        title: 'Blogs',
        href: '/admin/blogs',
        icon: Newspaper,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <a :href="route('admin.dashboard')" class="flex flex-col items-center gap-1 py-3">
                        <img src="/assets/images/site-logo.png" alt="Site Logo" class="h-14 w-32 object-contain" />
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-[#8e2527]">Admin Panel</span>
                    </a>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
