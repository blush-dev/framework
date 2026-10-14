<script setup lang="ts">
import { RouterLink } from 'vue-router';
import MessageText from '../components/MessageText.vue';
</script>

<template>
	<header class="page-header">
		<h1 tabindex="-1">{{ t('not_found_view.title', 'Not Found') }}</h1>
	</header>
	<p><MessageText :text="t('not_found_view.no_screen', 'There\'s no admin screen here. {link}.')"><template #link><RouterLink :to="{ name: 'dashboard' }">{{ t('not_found_view.go_to_dashboard', 'Go to the dashboard') }}</RouterLink></template></MessageText></p>
</template>
