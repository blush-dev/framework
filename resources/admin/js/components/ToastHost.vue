<script setup lang="ts">
/**
 * Shows the standing toasts (`toast.ts`) at the bottom right, as the toast
 * sketch draws them: a chip with the kind's glyph, the message, an Undo
 * where there is one, and a 2px rule along the bottom edge that counts it
 * down. The message is read from one shared polite live region rather
 * than the chips, which carry buttons.
 */

import AdminIcon from './AdminIcon.vue';
import { announcement, dismissToast, holdToast, toasts, undoToast, type Toast, type ToastKind } from '../toast';
import type { IconName } from '../icons';

const glyphs: Record<ToastKind, IconName> = {
	good: 'circle-check',
	warn: 'triangle-alert',
	danger: 'triangle-alert',
	info: 'info',
};

// Escape takes the toast focus is inside, and nothing else: a toast isn't
// an overlay the way the menus and the drawer are.
function keydown(event: KeyboardEvent, item: Toast): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		dismissToast(item.id);
	}
}
</script>

<template>
	<div class="toasts">
		<TransitionGroup name="toast">
			<div
				v-for="item in toasts"
				:key="item.id"
				:class="['toast', `toast--${item.kind}`, { 'is-held': item.held }]"
				:style="{ '--life': `${item.life}ms` }"
				role="group"
				:aria-label="item.message"
				@pointerenter="holdToast(item.id, true)"
				@pointerleave="holdToast(item.id, false)"
				@focusin="holdToast(item.id, true)"
				@focusout="holdToast(item.id, false)"
				@keydown="keydown($event, item)"
			>
				<span class="toast__bar" aria-hidden="true"><i /></span>
				<AdminIcon :name="glyphs[item.kind]" />
				<span class="toast__message">{{ item.message }}</span>
				<template v-if="item.undo">
					<span class="toast__divider" aria-hidden="true" />
					<button class="toast__undo" type="button" @click="undoToast(item.id)">
						<AdminIcon name="undo-2" />Undo
					</button>
				</template>
			</div>
		</TransitionGroup>
	</div>
	<p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true">{{ announcement.text }}</p>
</template>

<style scoped>
.toasts {
	position: fixed;
	right: 16px;
	bottom: calc(16px + env(safe-area-inset-bottom, 0px));
	z-index: 70;
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: var(--s-2);
	pointer-events: none;
}

/* The chip keeps the ordinary overlay hairline; the countdown is a
   separate bar, so the edge never changes as the time runs out. */
.toast {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-3);
	max-width: min(430px, calc(100vw - 32px));
	overflow: hidden;
	padding: 12px 12px 12px 16px;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
	color: var(--fg);
	font-size: var(--base);
	pointer-events: auto;
}

.toast > .icon {
	color: var(--good-dot);
}

.toast--warn > .icon {
	color: var(--warn-dot);
}

.toast--danger > .icon {
	color: var(--danger-dot);
}

.toast--info > .icon {
	color: var(--accent);
}

.toast__message {
	flex: 1;
	min-width: 0;
	line-height: 1.45;
}

.toast__divider {
	flex: none;
	align-self: stretch;
	width: 1px;
	margin-block: 3px;
	background: var(--border);
}

.toast__undo {
	display: inline-flex;
	flex: none;
	align-items: center;
	gap: 6px;
	height: var(--ctl-sm);
	padding: 0 11px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--accent);
	cursor: pointer;
	font-size: var(--text-sm);
	font-weight: 500;
}

.toast__undo:hover {
	background: var(--accent-soft);
}

.toast__undo .icon {
	width: 14px;
	height: 14px;
}

/* The chip clips to its radius, so the bar's ends follow the corners. Its
   width is the chip's, so the rate is the same whatever the message. */
.toast__bar {
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	height: 2px;
	pointer-events: none;
}

.toast__bar i {
	display: block;
	height: 100%;
	background: var(--good-dot);
	transform-origin: left center;
}

.toast--warn .toast__bar i {
	background: var(--warn-dot);
}

.toast--danger .toast__bar i {
	background: var(--danger-dot);
}

.toast--info .toast__bar i {
	background: var(--accent);
}

/* The clock that dismisses a toast is a timer in `toast.ts`, never the
   animation ending, so under reduced motion the bar stays whole and the
   toast still goes at its time. Held, the bar pauses where it is. */
@media (prefers-reduced-motion: no-preference) {
	.toast__bar i {
		animation: toast-count var(--life, 2600ms) linear forwards;
	}

	.toast.is-held .toast__bar i {
		animation-play-state: paused;
	}

	.toast-enter-active {
		transition: opacity 160ms ease-out, transform 160ms ease-out;
	}

	.toast-leave-active {
		transition: opacity 150ms ease-out;
	}

	.toast-move {
		transition: transform 160ms ease-out;
	}

	.toast-enter-from {
		opacity: 0;
		transform: translateY(8px);
	}

	.toast-leave-to {
		opacity: 0;
	}
}

@keyframes toast-count {
	to {
		transform: scaleX(0);
	}
}
</style>
