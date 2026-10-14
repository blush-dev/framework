<script setup lang="ts">
/**
 * The signed-in account's look (D-235, D-273, D-317): the admin's theme
 * and color scheme, shown on Your Account (D-369). The themes are the
 * admin themes that are on (D-827), each drawn as a small swatch of
 * itself in the current color scheme (the profiles sketch's,
 * `AdminThemeSketch`), from its own colors, whichever theme is showing. Choosing the site's default
 * follows it, so the account changes with it. Both belong to the
 * account, not the browser, so they follow it to any device and never
 * change what anyone else sees; they aren't the site's theme.
 */

import { ref } from 'vue';
import { useAction } from '../action';
import type { ColorScheme } from '../api';
import AdminIcon from './AdminIcon.vue';
import AdminThemeSketch from './AdminThemeSketch.vue';
import { adminTheme, adminThemes, defaultAdminTheme, saveAdminTheme } from '../admin-theme';
import type { AdminThemeChoice } from '../config';
import { colorScheme, saveColorScheme } from '../color-scheme';
import type { IconName } from '../icons';
import { t } from '../messages';

const schemes: { value: ColorScheme; label: string; hint: string; icon: IconName }[] = [
	{ value: 'system', label: t('account_preferences.system', 'System'), hint: t('account_preferences.system_hint', 'Match your device\'s setting'), icon: 'monitor' },
	{ value: 'light', label: t('account_preferences.light', 'Light'), hint: t('account_preferences.light_hint', 'Always light'), icon: 'sun' },
	{ value: 'dark', label: t('account_preferences.dark', 'Dark'), hint: t('account_preferences.dark_hint', 'Always dark'), icon: 'moon' }
];

// What a theme is, and whether it's the site's default.
function hint(theme: AdminThemeChoice): string {
	return [theme.description, theme.name === defaultAdminTheme.value ? t('account_preferences.site_default', 'Site default') : ''].filter(Boolean).join(' · ');
}

const message = ref('');

const { busy: saving, error, run } = useAction();

async function chooseTheme(theme: AdminThemeChoice): Promise<void> {
	message.value = '';

	await run(t('account_preferences.theme_failed', 'Your theme couldn\'t be saved.'), async () => {
		await saveAdminTheme(theme.name === defaultAdminTheme.value ? null : theme.name);
		message.value = t('account_preferences.theme_saved', 'Saved. The admin is {theme} on every device you sign in on.', { theme: theme.label });
	});
}

async function choose(scheme: ColorScheme): Promise<void> {
	message.value = '';

	await run(t('account_preferences.scheme_failed', 'Your color scheme couldn\'t be saved.'), async () => {
		await saveColorScheme(scheme);
		message.value = t('account_preferences.scheme_saved', 'Saved. The admin is {scheme, select, system {following your device} light {light} dark {dark} other {{scheme}}} on every device you sign in on.', { scheme });
	});
}
</script>

<template>
	<section class="panel" aria-labelledby="display-heading">
		<header class="panel__header">
			<h2 id="display-heading">{{ t('account_preferences.heading', 'Theme and Color Scheme') }}</h2>
			<p class="panel__hint">{{ t('account_preferences.hint', 'Just for you, on any device') }}</p>
		</header>
		<div class="panel__body">
			<fieldset class="schemes" :disabled="saving">
				<legend class="schemes__legend">{{ t('account_preferences.theme', 'Theme') }}</legend>
				<label v-for="theme in adminThemes" :key="theme.name" class="scheme">
					<input class="visually-hidden" type="radio" name="admin-theme" :value="theme.name" :checked="adminTheme?.name === theme.name" @change="chooseTheme(theme)">
					<AdminThemeSketch class="swatch" :preview="theme.preview" />
					<span class="scheme__text">
						<span class="scheme__label">{{ theme.label }}</span>
						<span v-if="hint(theme)" class="scheme__hint">{{ hint(theme) }}</span>
					</span>
				</label>
			</fieldset>
			<fieldset class="schemes" :disabled="saving">
				<legend class="schemes__legend">{{ t('account_preferences.color_scheme', 'Color scheme') }}</legend>
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
		<p class="panel__note">{{ t('account_preferences.note', 'Yours alone, and nobody else\'s to set. Both follow you to any device, because they live on the account rather than in this browser. They aren\'t the site\'s theme, which is under Config.') }}</p>
	</section>
</template>

<style scoped>
/* The panel's full width, three cards a row at most. */
.schemes {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(max(190px, calc((100% - 2 * var(--s-2)) / 3)), 1fr));
	gap: var(--s-2);
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
	color: var(--color-text);
	font-size: var(--font-size-base);
}

.scheme {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-3);
	min-width: 0;
	padding: var(--s-3) var(--s-4);
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface);
	color: var(--color-text-secondary);
	cursor: pointer;
}

.scheme:hover {
	background: var(--color-surface-subtle);
}

.scheme:has(:checked) {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.scheme:has(:focus-visible) {
	outline: 2px solid var(--color-accent);
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
	color: var(--color-text);
	font-weight: 600;
}

.scheme__hint {
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
}

/* A theme in miniature (`AdminThemeSketch`), a swatch's size. */
.swatch {
	flex: none;
	width: 46px;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
}

.scheme:has(:checked) .swatch {
	border-color: var(--color-accent);
}

.profile__status:empty {
	display: none;
}

.profile__status {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}
</style>
