<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
	<h1 tabindex="-1">Not found</h1>
	<p>There's no admin screen here. <RouterLink :to="{ name: 'dashboard' }">Go to the dashboard</RouterLink>.</p>
</template>
