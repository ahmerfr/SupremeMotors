<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';
import { BookOpen, Folder, LayoutGrid, User } from 'lucide-vue-next';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Categories/Makes',
        href: '/admin/categories',
        icon: Folder,
    },
    {
        title: 'Products',
        href: '/admin/products',
        icon: Folder,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: User, 
    },
];



</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <!-- <SidebarMenuButton size="lg" as-child> -->
                        <a :href="route('admin.dashboard')">
                            <img src="/assets/images/site-logo.png" alt="Site Logo" class="img-fluid" style="margin:auto;height: 80px;width: 150px;object-fit: contain;">
                        </a>
                    <!-- </SidebarMenuButton> -->
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
