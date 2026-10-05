/**
 * Restoring from the trash (D-237, D-481). A trashed entry comes back
 * with its id, unless another entry has that id now (such as a copy made
 * before it was trashed). Then the person chooses: restore it with a new
 * id, or leave it in the trash.
 */

import { ApiError, request } from './api';
import { confirmAction } from './confirm';

interface IdConflict {
	id: string;
	title: string;
	path: string;
}

/**
 * Restores a trashed entry, by the trash's name for it, as a draft, and
 * returns its id, or `null` when it's left in the trash.
 */
export async function restoreFromTrash(name: string): Promise<string | null> {
	try {
		return (await request<{ id: string }>('POST', '/trash/restore', { name })).id;
	} catch (caught) {
		const conflict = caught instanceof ApiError && caught.status === 409 ? conflictOf(caught.data) : null;

		if (conflict === null) {
			throw caught;
		}

		const restore = await confirmAction({
			title: 'Restore With a New ID?',
			body: [
				`**${conflict.title || conflict.path}** has this entry's id now, so it can't come back with it.`,
				'Restore it with a new id, or leave it in the trash.'
			],
			confirm: 'Restore with a new ID',
			cancel: 'Leave in the trash'
		});

		return restore ? (await request<{ id: string }>('POST', '/trash/restore', { name, newId: true })).id : null;
	}
}

/**
 * The conflict a refused restore names, if it names one.
 */
function conflictOf(data: unknown): IdConflict | null {
	const conflict = typeof data === 'object' && data !== null && 'conflict' in data ? data.conflict : null;

	return typeof conflict === 'object' && conflict !== null && 'id' in conflict && 'path' in conflict
		? { id: String(conflict.id), title: 'title' in conflict ? String(conflict.title) : '', path: String(conflict.path) }
		: null;
}
