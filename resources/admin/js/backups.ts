/**
 * The site's backups (D-881 to D-886), on Tools → Backups: the list, the
 * parts a backup can hold, and the calls that start, note, delete, put
 * back, check, and restore a backup, and save the schedule, with its test
 * email and preview. Downloading is a plain link (`downloadUrl()`).
 */

import { config } from './config';
import { series } from './format';
import { t } from './messages';
import { request } from './api';

export type BackupStatus = 'running' | 'done' | 'failed';

export type BackupKind = 'manual' | 'scheduled' | 'before-restore' | 'uploaded';

export interface BackupInfo {
	name: string;
	file: string;
	status: BackupStatus;
	kind: BackupKind;
	created: string;
	// The account that made it, by name, or `null` for the command line
	// or the schedule.
	by: string | null;
	note: string;
	parts: string[];
	size: number;
	reason: string | null;
	job: string | null;
}

export interface BackupPart {
	name: string;
	group: string;
	label: string;
	description: string;
	count: number;
	bytes: number;
}

export interface BackupGroup {
	key: string;
	label: string;
}

export interface BackupsOverview {
	backups: BackupInfo[];
	parts: BackupPart[];
	groups: BackupGroup[];
	used: number;
	free: number | null;
	folder: string;
	// The restore running, if one is.
	restoring: { job: string; backup: string; parts: string[] } | null;
	schedule: BackupSchedule;
	// The largest file an upload may be, or `null` when the server won't say.
	upload: { limit: number | null };
}

export type BackupFrequency = 'off' | 'daily' | 'weekly';

export type BackupNotice = 'failures' | 'always' | 'never';

// When the site backs itself up (D-887), as it's saved.
export interface BackupScheduleValues {
	frequency: BackupFrequency;
	day: number;
	hour: number;
	keep: number;
	// `null` for every part.
	parts: string[] | null;
	notice: BackupNotice;
	// An address, or '' for the site's.
	to: string;
}

export interface BackupSchedule extends BackupScheduleValues {
	// When it next runs, or `null` when it's off.
	next: string | null;
	timezone: string;
	siteEmail: string;
	canMail: boolean;
}

export interface BackupEmail {
	from: string;
	to: string;
	subject: string;
	text: string;
}

export interface RestoreCheck {
	problems: string[];
	warnings: string[];
}

const path = (name: string): string => `/backups/${encodeURIComponent(name)}`;

export function loadBackups(): Promise<BackupsOverview> {
	return request<BackupsOverview>('GET', '/backups');
}

export async function startBackup(parts: string[], note: string): Promise<BackupInfo> {
	return (await request<{ backup: BackupInfo }>('POST', '/backups', { parts, note })).backup;
}

export async function noteBackup(name: string, note: string): Promise<BackupInfo> {
	return (await request<{ backup: BackupInfo }>('PATCH', path(name), { note })).backup;
}

export function deleteBackup(name: string): Promise<{ deleted?: true; canceled?: true }> {
	return request('DELETE', path(name));
}

export async function undeleteBackup(name: string): Promise<BackupInfo> {
	return (await request<{ backup: BackupInfo }>('POST', `${path(name)}/undelete`)).backup;
}

export function checkRestore(name: string, parts: string[]): Promise<RestoreCheck> {
	return request<RestoreCheck>('POST', `${path(name)}/check`, { parts });
}

export async function startRestore(name: string, parts: string[]): Promise<string> {
	return (await request<{ job: string }>('POST', `${path(name)}/restore`, { parts })).job;
}

export async function saveSchedule(values: BackupScheduleValues): Promise<BackupSchedule> {
	return (await request<{ schedule: BackupSchedule }>('PUT', '/backups/schedule', values)).schedule;
}

export async function sendTestEmail(to: string): Promise<string> {
	return (await request<{ sent: true; to: string }>('POST', '/backups/schedule/test', { to })).to;
}

export async function previewEmail(stage: 'done' | 'failed', to: string): Promise<BackupEmail> {
	return (await request<{ email: BackupEmail }>('POST', '/backups/schedule/preview', { stage, to })).email;
}

export function downloadUrl(name: string): string {
	return `${config.api}${path(name)}/download`;
}

/**
 * What a backup holds, in a few words (the sketch's Holds column):
 * Everything, Everything but one or two parts, or the parts it holds.
 */
export function holds(backup: BackupInfo, parts: BackupPart[]): string[] {
	const missing = parts.filter((part) => !backup.parts.includes(part.name));

	if (!missing.length) {
		return [t('backups.everything', 'Everything')];
	}

	if (missing.length <= 2 && missing.length < parts.length - 1) {
		return [t('backups.everything_but', 'Everything but {parts}', { parts: series(missing.map((part) => part.label)) })];
	}

	return backup.parts.map((name) => parts.find((part) => part.name === name)?.label ?? name);
}
