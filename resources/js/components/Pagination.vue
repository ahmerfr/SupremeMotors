<script setup>
import { router, Link } from '@inertiajs/vue3';

defineProps({
	pageData: Object,
	total: Number,
});
</script>

<template>
    <div class="row flex align-items-center">
        <div class="col-md-4 mt-4">
            <div class="page-total">
                <p><em>Total <strong>{{ total }} records</strong> found.</em></p>
            </div>
        </div>
        <div class="col-md-8" style="margin-left: auto;">
            <nav aria-label="Page navigation">
                <ul v-if="pageData.length" class="pagination flex justify-end items-center mt-4 space-x-2">
                    <template v-for="(pg, inds) in pageData">
                        <li v-if="pg.url === null && pg.active === false" class="page-item" :data-check="JSON.stringify(pg)" :key="inds">
                            <a href="#" data-page="" class="btn page-link disabled bg-gray-800 text-gray-500 border border-gray-700 cursor-not-allowed px-4 py-2 rounded" role="button" v-html="pg.label"></a>
                        </li>
                        <li v-else-if="pg.url !== null && pg.active === true" :class="(pg.active == true) ? 'page-item active' : 'page-item'" :data-check="JSON.stringify(pg)" :key="pg.label">
                            <Link @click="pageChange" :href="pg.url" :data-page="pg.label" 
                                  class="btn page-link bg-[#782527] text-white border border-[#782527] hover:bg-[#9b3b3b] hover:border-[#9b3b3b] px-4 py-2 rounded" 
                                  role="button" v-html="pg.label"></Link>
                        </li>
                        <li v-else class="page-item" :data-check="JSON.stringify(pg)">
                            <Link @click="pageChange" :href="pg.url" :data-page="pg.label" 
                                  class="btn page-link bg-[#1b2b47] text-white border border-[#1b2b47] hover:bg-[#2f3f6b] hover:border-[#2f3f6b] px-4 py-2 rounded" 
                                  role="button" v-html="pg.label"></Link>
                        </li>
                    </template>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
export default {
	methods: {
		pageChange($e) {
			$e.preventDefault();
			let url = new URL($e.target.href);
			let page = url.searchParams.get('page');
			let newUrl = new URL(window.location.href);
			newUrl.searchParams.set('page', page);
			router.visit(newUrl);
		}
	}
}
</script>
