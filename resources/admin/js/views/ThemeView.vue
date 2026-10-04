<script setup lang="ts">
/**
 * A theme's details (D-383, the themes sketch's detail screen), at
 * `/themes/{vendor}/{name}`: its preview in both halves of its palette,
 * its details, its palette as swatches, and, for a folder theme the
 * active one doesn't use, **Delete theme**. A Composer theme says how
 * it's removed instead.
 *
 * **Activate** asks, as on the Themes screen (`useThemes()`), and a
 * failure is said under the header, leading with the site being
 * unchanged. A theme without a palette is sketched in the admin's own
 * colors, and says how to give it one.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ThemeSketch from '../components/ThemeSketch.vue';
import { PALETTE_ROLES, type PaletteRole } from '../api';
import { config } from '../config';
import { can } from '../session';
import { screenTitle } from '../screen';
import { copy, themeRoute, useThemes } from '../themes';

const route  = useRoute();
const router = useRouter();

const { appearance, error, busy, failed, active, load, find, label, installed, dependents, blockedMessage, activate, remove: removeTheme } = useThemes();

// What the account may do here (D-389).
const canActivate = can('extensions.themes.activate');
const canDelete   = can('extensions.themes.delete');

void load();

const name  = computed(() => `${String(route.params.vendor)}/${String(route.params.name)}`);
const theme = computed(() => find(name.value));
const deps  = computed(() => theme.value ? dependents(theme.value) : []);

watch(theme, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });

const installedBy = computed(() => {
	switch (theme.value?.source) {
		case 'framework':
			return 'Blush';
		case 'composer':
			return 'Composer';
		default:
			return 'A folder in extensions/';
	}
});

const ROLE_LABELS: Record<PaletteRole, string> = {
	background: 'Background',
	surface: 'Surface',
	text: 'Text',
	muted: 'Muted',
	accent: 'Accent',
	border: 'Border'
};

// The light half, then the dark one (or one group, when they're the
// same), each role's color with the ink that reads on it: whichever of
// the theme's own text and background colors contrasts more, so a swatch
// never adds a color the theme didn't declare.
const halves = computed(() => {
	const palette = theme.value?.preview?.palette;

	if (!palette) {
		return [];
	}

	const same     = PALETTE_ROLES.every((role) => palette[role][0] === palette[role][1]);
	const headings = same ? ['Light and dark'] : ['Light', 'Dark'];

	return headings.map((heading, half) => ({
		heading,
		swatches: PALETTE_ROLES.map((role) => {
			const fill = palette[role][half] ?? '';
			const text = palette.text[half] ?? '';
			const bg   = palette.background[half] ?? '';

			return { role, fill, ink: contrast(fill, text) >= contrast(fill, bg) ? text : bg };
		})
	}));
});

// WCAG relative luminance of a six-digit hex color.
function luminance(hex: string): number {
	const value   = Number.parseInt(hex.slice(1), 16);
	const channel = (part: number): number => {
		const c = part / 255;

		return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
	};

	return 0.2126 * channel(value >> 16 & 255) + 0.7152 * channel(value >> 8 & 255) + 0.0722 * channel(value & 255);
}

function contrast(a: string, b: string): number {
	const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);

	return ((high ?? 0) + 0.05) / ((low ?? 0) + 0.05);
}

// What deleting does, and how many themes fall back to this one.
const deleteNote = computed(() => {
	const count = deps.value.length;
	const note  = count === 0 ? '' : ` ${count === 1 ? '1 theme falls' : `${count} themes fall`} back to this one.`;

	return `Deleting removes the folder from the server.${note}`;
});

async function remove(): Promise<void> {
	const value = theme.value;

	if (value?.folder && await removeTheme(value.label, value.folder, deps.value)) {
		await router.push({ name: 'themes' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'themes' }"><AdminIcon name="chevron-left" />All themes</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ theme?.label ?? 'Theme' }}</h1>
			<p v-if="theme" class="page-header__hint">{{ theme.description || 'This theme has no description.' }}</p>
		</div>
		<div v-if="theme" class="page-header__actions">
			<template v-if="theme.active">
				<span class="pill pill--good">Active</span>
				<a class="button" :href="config.site.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />View site<span class="visually-hidden"> (new tab)</span></a>
			</template>
			<template v-else-if="theme.blocked">
				<span class="pill pill--warn">Can't activate</span>
				<button type="button" class="button" disabled>Activate</button>
			</template>
			<button v-else-if="busy === theme.name" type="button" class="button" disabled><span class="spin" aria-hidden="true" />Activating…</button>
			<button v-else-if="canActivate" type="button" class="button" :class="failed?.name === theme.name ? 'button--danger' : 'button--primary'" :disabled="busy !== null" @click="activate(theme)">
				{{ failed?.name === theme.name ? 'Try again' : `Activate ${theme.label}` }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="theme && appearance">
		<p v-if="failed?.name === theme.name" class="theme-message theme-message--danger" role="alert">
			<AdminIcon name="triangle-alert" /><span>Your site is still showing {{ active?.label ?? appearance.active }}; nothing changed. {{ failed.reason }}</span>
		</p>

		<div class="theme-detail">
			<div class="theme-detail__columns">
				<section class="panel" aria-labelledby="preview-heading">
					<header class="panel__header">
						<h2 id="preview-heading">Preview</h2>
						<p class="panel__hint">{{ theme.preview?.palette ? 'Drawn from the declared palette' : 'No palette declared' }}</p>
					</header>
					<div class="panel__body">
						<p v-if="theme.blocked && !theme.active" class="theme-message theme-message--warn">
							<AdminIcon name="triangle-alert" /><span>{{ blockedMessage(theme) }}</span>
						</p>
						<div class="theme-detail__previews">
							<figure>
								<ThemeSketch :preview="theme.preview" scheme="light" />
								<figcaption>Light</figcaption>
							</figure>
							<figure>
								<ThemeSketch :preview="theme.preview" scheme="dark" />
								<figcaption>Dark</figcaption>
							</figure>
						</div>
						<p v-if="!theme.preview?.palette" class="field__help">
							These use the admin's colors. A theme draws its own preview with a <code>preview</code> palette in its <code>theme.json</code>.
						</p>
					</div>
				</section>

				<section class="panel" aria-labelledby="details-heading">
					<header class="panel__header"><h2 id="details-heading">Details</h2></header>
					<div class="panel__body">
						<dl class="theme-facts">
							<dt>Name</dt>
							<dd class="mono">{{ theme.name }}</dd>
							<dt>{{ theme.authors.length > 1 ? 'Authors' : 'Author' }}</dt>
							<dd>
								<template v-if="theme.authors.length === 0">—</template>
								<span v-for="author in theme.authors" :key="author.name" class="theme-facts__author">
									<a v-if="author.homepage" :href="author.homepage" target="_blank" rel="noopener">{{ author.name }}<span class="visually-hidden"> (new tab)</span></a>
									<template v-else>{{ author.name }}</template>
									<span v-if="author.role" class="theme-facts__role">{{ author.role }}</span>
									<a v-if="author.email" class="theme-facts__email" :href="`mailto:${author.email}`" :aria-label="`Email ${author.name}`"><AdminIcon name="mail" /></a>
								</span>
							</dd>
							<dt>Version</dt>
							<dd :class="{ mono: theme.version }">{{ theme.version || '—' }}</dd>
							<dt>Installed by</dt>
							<dd>{{ installedBy }}</dd>
							<dt>Folder</dt>
							<dd>
								<template v-if="theme.folder">
									<span class="mono">{{ theme.folder }}</span>
									<button type="button" class="button button--ghost button--small button--icon theme-facts__copy" :aria-label="`Copy ${theme.folder}`" @click="copy(theme.folder, 'the folder path')"><AdminIcon name="copy" /></button>
								</template>
								<template v-else>Ships with Blush</template>
							</dd>
							<dt>Namespace</dt>
							<dd class="mono">{{ theme.namespace }}</dd>
							<dt>Type</dt>
							<dd>{{ theme.preview?.type || '—' }}</dd>
							<dt>Falls back to</dt>
							<dd>
								<template v-if="theme.source === 'framework'">Nothing: every theme falls back to this one</template>
								<template v-else-if="theme.parent && !installed(theme.parent)">
									<span class="mono is-warn">{{ theme.parent }}</span><span class="is-warn">, which isn't installed</span>
								</template>
								<RouterLink v-else :to="themeRoute(theme.parent ?? 'blush/default')">{{ label(theme.parent ?? 'blush/default') }}</RouterLink>
							</dd>
							<dt>Used as fallback by</dt>
							<dd>
								<template v-if="theme.source === 'framework'">Every theme</template>
								<template v-else-if="deps.length === 0">Nothing</template>
								<template v-for="(dep, index) in deps" v-else :key="dep.name">
									<RouterLink :to="themeRoute(dep.name)">{{ dep.label }}</RouterLink><template v-if="index < deps.length - 1">, </template>
								</template>
							</dd>
						</dl>
					</div>
				</section>
			</div>

			<section v-if="halves.length" class="panel" aria-labelledby="palette-heading">
				<header class="panel__header">
					<h2 id="palette-heading">Palette</h2>
					<p class="panel__hint">{{ halves.length === 1 ? 'Six roles, the same light and dark' : 'Six roles, each a light and a dark color' }}</p>
				</header>
				<div class="panel__body palette">
					<section v-for="half in halves" :key="half.heading" class="palette__half">
						<h3>{{ half.heading }}</h3>
						<div class="palette__grid">
							<div v-for="swatch in half.swatches" :key="swatch.role" class="palette__swatch" :style="{ background: swatch.fill, color: swatch.ink }">
								<b>{{ ROLE_LABELS[swatch.role] }}</b>
								<span class="mono">{{ swatch.fill }}</span>
							</div>
						</div>
					</section>
				</div>
			</section>

			<PreviousVersion kind="theme" :extension="theme" :live="appearance.chain.includes(theme.name)" @changed="load" />
			<p v-if="theme.source === 'composer'" class="notice">
				<span>Composer manages this theme, so it can't be deleted here. Remove it from the project with <code>composer remove {{ theme.name }}</code>, and it leaves this list.</span>
			</p>
			<p v-else-if="theme.source === 'local' && !theme.deletable" class="notice">
				<span>{{ theme.active ? 'This is the active theme' : `The active theme, ${active?.label ?? appearance.active}, falls back to it` }}, so it can't be deleted. Activate another theme first.</span>
			</p>
			<div v-else-if="canDelete && theme.deletable" class="danger-zone">
				<p>{{ deleteNote }}</p>
				<button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete theme</button>
			</div>
		</div>
	</template>

	<p v-else-if="appearance" class="notice notice--warn" role="alert">
		<span>No theme named <span class="mono">{{ name }}</span> is installed.</span>
	</p>

	<div v-else-if="!error" class="theme-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.theme-detail {
	display: grid;
	gap: var(--s-5);
}

.theme-detail__columns {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 380px;
	align-items: start;
	gap: var(--s-5);
}

.theme-detail__previews {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: var(--s-4);
}

.theme-detail__previews figure {
	margin: 0;
}

.theme-detail__previews .sketch {
	border: 1px solid var(--border);
	border-radius: var(--r-2);
}

.theme-detail__previews figcaption,
.palette h3 {
	margin: var(--s-2) 0 0;
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.theme-facts {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	align-items: baseline;
	gap: var(--s-3) var(--s-4);
	margin: 0;
	font-size: var(--text-sm);
}

.theme-facts dt {
	color: var(--fg-3);
}

.theme-facts dd {
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.theme-facts .is-warn {
	color: var(--warn);
}

.theme-facts__author {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 0 var(--s-2);
}

.theme-facts__author + .theme-facts__author {
	margin-top: var(--s-1);
}

.theme-facts__role {
	color: var(--fg-3);
}

.theme-facts__email {
	align-self: center;
	color: var(--fg-3);
}

.theme-facts__email:hover {
	color: var(--accent);
}

.theme-facts__email .icon {
	width: 14px;
	height: 14px;
}

.theme-facts__copy {
	margin-block: -6px;
	vertical-align: middle;
}

/* A problem is said where the action is, leading with what's safe. */
.theme-message {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: 0 0 var(--s-4);
	padding: var(--s-3);
	border-radius: var(--r-1);
	font-size: var(--text-xs);
	line-height: 1.45;
}

.theme-message .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.theme-message--danger {
	background: var(--danger-soft);
	color: var(--danger);
}

.theme-message--warn {
	background: var(--warn-soft);
	color: var(--warn);
}

/* The two halves side by side, each bounded by its own heading rule. */
.palette {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: var(--s-6);
}

.palette > * + * {
	margin-top: 0;
}

.palette h3 {
	margin: 0 0 var(--s-3);
	padding-bottom: var(--s-2);
	border-bottom: 1px solid var(--border);
}

.palette__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: var(--s-2);
}

/* The theme's own colors fill it; the frame is the admin's, so a white
   swatch on a white panel still has an edge. */
.palette__swatch {
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
	gap: 1px;
	min-width: 0;
	aspect-ratio: 3 / 2;
	padding: var(--s-3);
	overflow: hidden;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-2);
}

.palette__swatch b {
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
	opacity: .72;
}

.palette__swatch span {
	font-size: var(--text-xs);
}

.danger-zone {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
}

.danger-zone p {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.danger-zone .button {
	flex: none;
	margin-left: auto;
}

/* The spinner of a working button. */
.spin {
	flex: none;
	width: 13px;
	height: 13px;
	border: 2px solid currentColor;
	border-top-color: transparent;
	border-radius: 50%;
	animation: spin .7s linear infinite;
}

@keyframes spin {
	to {
		transform: rotate(360deg);
	}
}

.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}

@media (width <= 1180px) {
	.theme-detail__columns {
		grid-template-columns: minmax(0, 1fr);
	}
}

@media (width <= 760px) {
	.palette,
	.theme-detail__previews {
		grid-template-columns: minmax(0, 1fr);
	}
}

@media (prefers-reduced-motion: reduce) {
	.spin {
		animation: none;
	}
}
</style>
