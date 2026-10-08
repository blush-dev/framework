<script setup lang="ts">
/**
 * A theme's details (D-383; drawn as the extensions sketch's in D-565),
 * at `/themes/{vendor}/{name}`: its label with Active or Can't activate
 * beside it, its Appearance (its preview in both halves of its palette,
 * each with the six colors listed under it), then its Details
 * (`ExtensionFacts`, with its type) beside its Dependencies
 * (`ExtensionDependencies`: the theme it falls back to, its
 * requirements, checked as if it were active, D-431, its conflicts,
 * replaces, provides, and suggests, D-434 to D-439, and those on the
 * other side, with the themes that fall back to it, D-440), and, for a
 * folder theme the active one doesn't use, **Delete theme**. An active
 * theme whose requirements aren't met says it isn't running. A Composer
 * theme says how it's removed instead.
 *
 * **Activate** asks, as on the Themes screen (`useThemes()`), and a
 * failure is said under the header, leading with the site being
 * unchanged. A theme without a palette is sketched in the admin's own
 * colors, and says how to give it one.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionDependencies from '../components/ExtensionDependencies.vue';
import ExtensionDependents from '../components/ExtensionDependents.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ThemeSketch from '../components/ThemeSketch.vue';
import { PALETTE_ROLES, type PaletteRole } from '../api';
import { config } from '../config';
import { extensionRoute } from '../extensions';
import { can } from '../session';
import { screenTitle } from '../screen';
import { useThemes } from '../themes';

const route  = useRoute();
const router = useRouter();

const { answer, error, busy, failed, active, load, find, label, installed, dependents, blockedMessage, fallbackMessage, activate, remove: removeTheme } = useThemes();

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

// Each half's six colors, listed under its preview.
const halves = computed(() => {
	const palette = theme.value?.preview?.palette ?? null;

	return [0, 1].map((half) => palette === null ? [] : PALETTE_ROLES.map((role) => ({ role, fill: palette[role][half] ?? '' })));
});

// The themes that fall back to it, as the Dependencies panel lists them.
const fallingBack = computed(() => deps.value.map((dep) => ({ name: dep.name, label: dep.label, kind: 'theme' as const })));

// The theme it falls back to, by name, if any (D-632), and whether it's missing.
const parentName    = computed(() => theme.value?.parent ?? '');
const missingParent = computed(() => parentName.value !== '' && !installed(parentName.value));

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
			<div class="page-header__title">
				<h1 tabindex="-1">{{ theme?.label ?? 'Theme' }}</h1>
				<template v-if="theme">
					<span v-if="theme.active" class="pill pill--good">Active</span>
					<span v-if="fallbackMessage(theme)" class="pill pill--warn">Not running</span>
					<span v-else-if="theme.blocked && !theme.active" class="pill pill--warn">Can't activate</span>
				</template>
			</div>
			<p v-if="theme" class="page-header__hint">{{ theme.description || 'This theme has no description.' }}</p>
		</div>
		<div v-if="theme" class="page-header__actions">
			<a v-if="theme.active" class="button" :href="config.site.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />View Site<span class="visually-hidden"> (new tab)</span></a>
			<button v-else-if="theme.blocked" type="button" class="button" disabled>Activate</button>
			<button v-else-if="busy === theme.name" type="button" class="button" disabled><span class="spin" aria-hidden="true" />Activating…</button>
			<button v-else-if="canActivate" type="button" class="button" :class="failed?.name === theme.name ? 'button--danger' : 'button--primary'" :disabled="busy !== null" @click="activate(theme)">
				{{ failed?.name === theme.name ? 'Try Again' : `Activate ${theme.label}` }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="theme && answer" class="extension-detail">
		<p v-if="fallbackMessage(theme)" class="notice notice--small notice--warn">
			<AdminIcon name="triangle-alert" /><span>{{ fallbackMessage(theme) }}</span>
		</p>
		<p v-if="failed?.name === theme.name" class="notice notice--small notice--error" role="alert">
			<AdminIcon name="triangle-alert" /><span>Your site is still showing {{ active?.label ?? answer.active }}; nothing changed. {{ failed.reason }}</span>
		</p>
		<p v-else-if="theme.blocked && !theme.active" class="notice notice--small notice--warn">
			<AdminIcon name="triangle-alert" /><span>{{ blockedMessage(theme) }}</span>
		</p>
		<AbandonedNotice noun="theme" :abandoned="theme.abandoned" :replacement="theme.replacement" />

		<section class="panel" aria-labelledby="appearance-heading">
			<header class="panel__header">
				<h2 id="appearance-heading">Appearance</h2>
				<p class="panel__hint">{{ theme.preview?.palette ? 'The six colors it declares, drawn and listed' : 'No palette declared' }}</p>
			</header>
			<div class="panel__body">
				<div class="theme-appearance">
					<figure v-for="(half, index) in halves" :key="index">
						<ThemeSketch :preview="theme.preview" :scheme="index === 0 ? 'light' : 'dark'" />
						<figcaption class="eyebrow">{{ index === 0 ? 'Light' : 'Dark' }}</figcaption>
						<dl v-if="half.length" class="palette-legend">
							<div v-for="swatch in half" :key="swatch.role">
								<i :style="{ background: swatch.fill }" aria-hidden="true" />
								<dt>{{ ROLE_LABELS[swatch.role] }}</dt>
								<dd class="mono">{{ swatch.fill }}</dd>
							</div>
						</dl>
					</figure>
				</div>
				<p v-if="!theme.preview?.palette" class="field__help">
					These use the admin's colors. A theme draws its own preview with a <code>preview</code> palette in its <code>theme.json</code>.
				</p>
			</div>
		</section>

		<div class="extension-detail__columns">
			<ExtensionFacts :extension="theme" :installed-by="installedBy" :folder="theme.folder">
				<template #kind>
					<dt>Type</dt>
					<dd>{{ theme.preview?.type || '—' }}</dd>
				</template>
			</ExtensionFacts>

			<ExtensionDependencies :extension="theme" noun="theme" replaces-hint="It can't run" :as-if-active="!theme.active">
				<template v-if="parentName !== ''" #own>
					<section class="panel__section" aria-labelledby="fallback-heading">
						<div class="panel__section-head"><h3 id="fallback-heading" class="eyebrow">Falls back to</h3><span>Where what it doesn't define comes from</span></div>
						<ul class="dependencies">
							<li>
								<AdminIcon :name="missingParent ? 'circle-x' : 'circle-check'" :class="missingParent ? 'is-unmet' : 'is-met'" />
								<div class="dependencies__ref">
									<template v-if="missingParent">
										<span class="mono">{{ parentName }}</span>
										<span class="dependencies__note is-unmet">isn't installed</span>
									</template>
									<template v-else>
										<RouterLink :to="extensionRoute('theme', parentName)">{{ label(parentName) }}</RouterLink>
										<span class="mono dependencies__name">{{ parentName }}</span>
									</template>
								</div>
							</li>
						</ul>
					</section>
				</template>
				<template v-if="deps.length" #others>
					<section class="panel__section" aria-labelledby="fallback-by-heading">
						<div class="panel__section-head"><h3 id="fallback-by-heading" class="eyebrow">Used as fallback by</h3></div>
						<ExtensionDependents :dependents="fallingBack" />
					</section>
				</template>
			</ExtensionDependencies>
		</div>

		<PreviousVersion kind="theme" :extension="theme" :live="answer.chain.includes(theme.name)" @changed="load" />
		<p v-if="theme.source === 'composer'" class="notice">
			<span>Composer manages this theme, so it can't be deleted here. Remove it from the project with <code>composer remove {{ theme.name }}</code>, and it leaves this list.</span>
		</p>
		<p v-else-if="theme.source === 'local' && !theme.deletable" class="notice">
			<span>{{ theme.active ? 'This is the active theme' : `The active theme, ${active?.label ?? answer.active}, falls back to it` }}, so it can't be deleted. Activate another theme first.</span>
		</p>
		<DangerZone v-else-if="canDelete && theme.deletable">
			{{ deleteNote }}
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete Theme</button></template>
		</DangerZone>
	</div>

	<p v-else-if="answer" class="notice notice--warn" role="alert">
		<span>No theme named <span class="mono">{{ name }}</span> is installed.</span>
	</p>

	<div v-else-if="!error" class="extension-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
/* Both halves of its palette side by side, each listed under its picture. */
.theme-appearance {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: var(--s-5);
}

.theme-appearance figure {
	margin: 0;
}

.theme-appearance .sketch {
	border: 1px solid var(--border);
	border-radius: var(--r-2);
}

.theme-appearance figcaption {
	margin: var(--s-3) 0 0;
}

/* A theme's palette as a legend under its preview: each role's color as
   a chip, framed so white still has an edge, with its name and value in
   the admin's own ink. */
.palette-legend {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1px var(--s-4);
	margin: var(--s-3) 0 0;
}

.palette-legend > div {
	display: grid;
	grid-template-columns: auto 78px auto;
	justify-content: start;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: 2px 0;
	font-size: var(--text-xs);
}

.palette-legend i {
	width: 16px;
	height: 16px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
}

.palette-legend dt {
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.palette-legend dd {
	margin: 0;
	color: var(--fg-3);
}

@media (width <= 600px) {
	.palette-legend {
		grid-template-columns: minmax(0, 1fr);
	}
}

.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}

@media (width <= 760px) {
	.theme-appearance {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
