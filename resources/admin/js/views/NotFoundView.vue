<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
	<header class="page-header">
		<h1 tabindex="-1">Not Found</h1>
	</header>
	<p>There's no admin screen here. <RouterLink :to="{ name: 'dashboard' }">Go to the dashboard</RouterLink>.</p>
</template>
