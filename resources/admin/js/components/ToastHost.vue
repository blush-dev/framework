<script setup lang="ts">
/**
 * Shows the current toast (`toast.ts`) at the bottom right, in a polite
 * live region, so it's read out without taking focus.
 */

import { currentToast } from '../toast';
</script>

<template>
	<div class="toasts" role="status" aria-live="polite">
		<Transition name="toast">
			<p v-if="currentToast" :key="currentToast.id" class="toast">{{ currentToast.message }}</p>
		</Transition>
	</div>
</template>

<style scoped>
.toasts {
	position: fixed;
	right: 16px;
	bottom: max(16px, env(safe-area-inset-bottom));
	z-index: 70;
	pointer-events: none;
}

.toast {
	max-width: min(360px, calc(100vw - 32px));
	padding: 13px 18px;
	border-radius: var(--r-2);
	background: var(--fg);
	box-shadow: var(--shadow-2);
	color: var(--bg);
	font-size: var(--text-sm);
}

.toast-enter-active,
.toast-leave-active {
	transition: opacity 150ms ease-out, transform 150ms ease-out;
}

.toast-enter-from,
.toast-leave-to {
	opacity: 0;
	transform: translateY(6px);
}

@media (prefers-reduced-motion: reduce) {
	.toast-enter-active,
	.toast-leave-active {
		transition: none;
	}
}
</style>
