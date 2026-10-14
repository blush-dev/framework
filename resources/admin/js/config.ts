/**
 * What the admin needs to start, from the JSON block the shell page
 * prints (`ShellController`). Everything else comes from the API.
 */

import type { AdminThemePreview } from './api';

export interface AdminConfig {
	base: string;
	api: string;
	site: { name: string; url: string };
	// The URL path the media library is served at (`MediaConfig`).
	media: { url: string };
	// The signed-in account's, or `null` when no one is (D-235).
	colorScheme: 'system' | 'light' | 'dark' | null;
	// The admin theme the signed-in account chose (D-827), by name, or
	// `null` when it follows the site's default or no one is signed in.
	adminTheme: string | null;
	// The admin themes an account may choose, Neutral first, and the
	// site's default (D-827).
	adminThemes: { default: string | null; offered: AdminThemeChoice[] };
	// The signed-in account's language, or `null` for the site's, and
	// the languages the admin has text in, each named in its own (D-831).
	language: string | null;
	adminLanguages: { code: string; label: string }[];
	// Whether `@name` links to a profile (`MarkdownConfig`, D-493).
	mentions: boolean;
	// Whether the site can send email (`MailConfig`, D-846).
	mail: boolean;
	// The site's language (a BCP 47 tag) and its date and time formats,
	// each a style or an ICU pattern (`AppConfig`, D-693).
	dates: { locale: string; date: string; time: string };
	// The languages content is written in, by code, the default first
	// (D-455, D-728).
	languages: { code: string; label: string }[];
	// What raw HTML each level may add (`HtmlRules`, D-495).
	html: HtmlRules;
}

export interface AdminThemeChoice {
	name: string;
	label: string;
	description: string;
	// Its stylesheet's URL, or `null` for Neutral, the admin's own look.
	stylesheet: string | null;
	// Its colors for a sketch, each as CSS writes it (`light-dark()`).
	preview: AdminThemePreview;
}

export interface HtmlRules {
	// The allowed list: tags, each with the attributes it may have
	// beyond the global ones.
	allowed: Record<string, string[]>;
	global: string[];
	refused: string[];
	refusedAttributes: string[];
	urlAttributes: string[];
}

function read(): AdminConfig {
	const element = document.getElementById('blush-admin-config');

	if (!element?.textContent) {
		throw new Error('The admin page has no start-up config.');
	}

	return JSON.parse(element.textContent) as AdminConfig;
}

export const config: AdminConfig = read();
