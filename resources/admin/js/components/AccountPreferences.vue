<script setup lang="ts">
/**
 * The signed-in account's look (D-235, D-273, D-317): the admin's theme
 * and color scheme, shown on Your Account (D-369). Each theme is drawn
 * as a small swatch of itself in the current color scheme (the profiles
 * sketch's), from its `--preview-*` tokens, whichever theme is showing. Both belong to the
 * account, not the browser, so they follow it to any device and never
 * change what anyone else sees; they aren't the site's theme.
 */

import { ref } from 'vue';
import { useAction } from '../action';
import type { AdminTheme, ColorScheme } from '../api';
import AdminIcon from './AdminIcon.vue';
import { adminTheme, saveAdminTheme } from '../admin-theme';
import { colorScheme, saveColorScheme } from '../color-scheme';
import type { IconName } from '../icons';

const schemes: { value: ColorScheme; label: string; hint: string; icon: IconName }[] = [
	{ value: 'system', label: 'System', hint: 'Match your device\'s setting', icon: 'monitor' },
	{ value: 'light', label: 'Light', hint: 'Always light', icon: 'sun' },
	{ value: 'dark', label: 'Dark', hint: 'Always dark', icon: 'moon' }
];

// The admin's two looks (D-317): a per-account choice, like the scheme.
const themes: { value: AdminTheme; label: string; hint: string }[] = [
	{ value: 'neutral', label: 'Neutral', hint: 'Cool gray, a cobalt accent' },
	{ value: 'editorial', label: 'Editorial', hint: 'Warm paper, a teal accent, serif titles' }
];

const message = ref('');

const { busy: saving, error, run } = useAction();

async function chooseTheme(theme: AdminTheme): Promise<void> {
	message.value = '';

	await run('Your theme couldn\'t be saved.', async () => {
		await saveAdminTheme(theme);
		message.value = `Saved. The admin is ${theme === 'neutral' ? 'Neutral' : 'Editorial'} on every device you sign in on.`;
	});
}

async function choose(scheme: ColorScheme): Promise<void> {
	message.value = '';

	await run('Your color scheme couldn\'t be saved.', async () => {
		await saveColorScheme(scheme);
		message.value = `Saved. The admin is ${scheme === 'system' ? 'following your device' : scheme} on every device you sign in on.`;
	});
}
</script>

<template>
	<section class="panel" aria-labelledby="display-heading">
		<header class="panel__header">
			<h2 id="display-heading">Theme and Color Scheme</h2>
			<p class="panel__hint">Just for you, on any device</p>
		</header>
		<div class="panel__body">
			<fieldset class="schemes" :disabled="saving">
				<legend class="schemes__legend">Theme</legend>
				<label v-for="theme in themes" :key="theme.value" class="scheme">
					<input class="visually-hidden" type="radio" name="admin-theme" :value="theme.value" :checked="adminTheme === theme.value" @change="chooseTheme(theme.value)">
					<span class="swatch" :class="`swatch--${theme.value}`" aria-hidden="true"><span class="swatch__rail" /><span class="swatch__card" /><span class="swatch__dot" /><span class="swatch__line" /><span class="swatch__line swatch__line--short" /></span>
					<span class="scheme__text">
						<span class="scheme__label">{{ theme.label }}</span>
						<span class="scheme__hint">{{ theme.hint }}</span>
					</span>
				</label>
			</fieldset>
			<fieldset class="schemes" :disabled="saving">
				<legend class="schemes__legend">Color scheme</legend>
				<label v-for="scheme in schemes" :key="scheme.value" class="scheme">
					<input class="visually-hidden" type="radio" name="color-scheme" :value="scheme.value" :checked="colorScheme === scheme.value" @change="choose(scheme.value)">
					<AdminIcon :name="scheme.icon" />
					<span class="scheme__text">
						<span class="scheme__label">{{ scheme.label }}</span>
						<span class="scheme__hint">{{ scheme.hint }}</span>
					</span>
				</label>
			</fieldset>
			<p class="profile__status" aria-live="polite">{{ message }}</p>
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		</div>
		<p class="panel__note">Yours alone, and nobody else's to set. Both follow you to any device, because they live on the account rather than in this browser. They aren't the site's theme, which is under Config.</p>
	</section>
</template>

<style scoped>
.schemes {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: var(--s-2);
	max-width: 760px;
	margin: 0;
	padding: 0;
	border: 0;
}

.schemes + .schemes {
	margin-top: var(--s-4);
}

/* Each choice is named, so the two rows of cards read apart. */
.schemes__legend {
	margin-bottom: var(--s-2);
	padding: 0;
	color: var(--fg);
	font-size: var(--base);
}

.scheme {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-3);
	min-width: 0;
	padding: var(--s-3) var(--s-4);
	border: 1px solid var(--border-strong);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg-2);
	cursor: pointer;
}

.scheme:hover {
	background: var(--surface-2);
}

.scheme:has(:checked) {
	border-color: var(--accent);
	background: var(--accent-soft);
	color: var(--accent);
}

.scheme:has(:focus-visible) {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.schemes:disabled .scheme {
	cursor: progress;
}

.scheme__text {
	display: grid;
	min-width: 0;
}

.scheme__label {
	color: var(--fg);
	font-weight: 600;
}

.scheme__hint {
	color: var(--fg-2);
	font-size: var(--text-xs);
}

/* A theme in miniature: its rail, a card, an accent, and two lines of
   ink, in the theme's own colors. */
.swatch {
	position: relative;
	flex: none;
	overflow: hidden;
	width: 46px;
	height: 34px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--sw-bg);
}

.swatch--neutral {
	--sw-bg: var(--preview-neutral-bg);
	--sw-surface: var(--preview-neutral-surface);
	--sw-accent: var(--preview-neutral-accent);
	--sw-fg: var(--preview-neutral-fg);
}

.swatch--editorial {
	--sw-bg: var(--preview-editorial-bg);
	--sw-surface: var(--preview-editorial-surface);
	--sw-accent: var(--preview-editorial-accent);
	--sw-fg: var(--preview-editorial-fg);
}

.scheme:has(:checked) .swatch {
	border-color: var(--accent);
}

.swatch > span {
	position: absolute;
	display: block;
}

.swatch__rail {
	top: 0;
	bottom: 0;
	left: 0;
	width: 11px;
	border-right: 1px solid color-mix(in srgb, var(--sw-fg) 14%, transparent);
	background: var(--sw-surface);
}

.swatch__card {
	top: 5px;
	right: 4px;
	bottom: 5px;
	left: 15px;
	border-radius: 3px;
	background: var(--sw-surface);
}

.swatch__dot {
	top: 9px;
	left: 19px;
	width: 13px;
	height: 3px;
	border-radius: 2px;
	background: var(--sw-accent);
}

.swatch__line {
	top: 16px;
	left: 19px;
	width: 17px;
	height: 2px;
	border-radius: 1px;
	background: var(--sw-fg);
	opacity: .5;
}

.swatch__line--short {
	top: 21px;
	width: 11px;
	opacity: .28;
}

.profile__status:empty {
	display: none;
}

.profile__status {
	color: var(--fg-2);
	font-size: var(--text-sm);
}
</style>
