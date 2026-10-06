<script setup lang="ts">
/**
 * The card a screen outside the admin's shell is drawn in (D-509): Sign
 * in and Choose a Password, centered on the page, with the site's
 * initial over the title. A form in it is `.auth-card__form`: its fields
 * close together, and its button a little taller.
 */

import { config } from '../config';

defineProps<{
	title: string;
	// A little wider, for a screen that says more.
	wide?: boolean;
}>();
</script>

<template>
	<div class="auth-card" :class="{ 'auth-card--wide': wide }">
		<header class="auth-card__header">
			<span class="auth-card__mark" aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
			<h1 tabindex="-1">{{ title }}</h1>
		</header>
		<slot />
	</div>
</template>

<style scoped>
.auth-card {
	display: grid;
	gap: 20px;
	width: min(100%, 22rem);
	padding: 28px 24px;
	background: var(--surface);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	box-shadow: var(--shadow-2);
}

.auth-card--wide {
	width: min(100%, 24rem);
}

.auth-card__header {
	display: grid;
	justify-items: start;
	gap: 14px;
}

.auth-card__mark {
	display: grid;
	place-items: center;
	width: 32px;
	height: 32px;
	border-radius: var(--r-1);
	background: var(--accent);
	color: var(--accent-fg);
	font-family: var(--font-display);
	font-weight: 600;
	text-transform: uppercase;
}

:slotted(.auth-card__form) {
	display: grid;
	gap: 14px;
}

:slotted(.auth-card__form) .button {
	height: 34px;
	margin-top: 4px;
}
</style>
