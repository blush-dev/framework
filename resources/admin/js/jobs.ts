/**
 * Following background jobs (D-621). The admin is a runner for the jobs
 * a person starts and waits on: `follow()` asks the server to run a
 * job's next chunk, again and again, passing each state to `update` (for
 * its progress), until it's finished, or waiting to try again after a
 * failure. A job another runner has is checked on every second.
 */

import { formatWhenInline } from './format';
import { request, type ActionResult, type Job } from './api';

const wait = (milliseconds: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, milliseconds));

export async function follow(id: string, update: (job: Job) => void = () => {}): Promise<Job> {
	for (;;) {
		const { job } = await request<{ job: Job }>('POST', `/jobs/${encodeURIComponent(id)}/run`);

		update(job);

		if (job.status === 'done' || job.status === 'failed' || (job.status === 'queued' && !job.due)) {
			return job;
		}

		await wait(job.status === 'running' ? 1000 : 100);
	}
}

/**
 * Follows a job to its end, as `follow()` does, and throws with its
 * message unless it's done, for a caller that only goes on when it is
 * (a Site Health fix or check, D-624, D-625).
 */
export async function finish(id: string, update: (job: Job) => void = () => {}): Promise<Job> {
	const job = await follow(id, update);

	if (job.status !== 'done') {
		throw new Error(job.status === 'queued' ? `It failed, and will try again: ${job.error ?? 'no reason given'}` : (job.message || job.error || 'It didn\'t finish.'));
	}

	return job;
}

/**
 * A job's end as an action's result: done is success; a job waiting to
 * try again says when.
 */
export function jobResult(job: Job): ActionResult {
	if (job.status === 'queued') {
		return {
			successful: false,
			message: `It failed, and will try again ${formatWhenInline(job.available)}: ${job.error ?? 'no reason given'}`,
			details: job.details
		};
	}

	return {
		successful: job.status === 'done',
		message: job.message || job.error || (job.status === 'done' ? 'Done.' : 'It failed.'),
		details: job.details
	};
}

/**
 * The pill class for a job's status.
 */
export const JOB_PILLS: Record<Job['status'], { label: string; kind: string }> = {
	queued: { label: 'Queued', kind: '' },
	running: { label: 'Running', kind: 'pill--accent' },
	done: { label: 'Done', kind: 'pill--good' },
	failed: { label: 'Failed', kind: 'pill--danger' }
};
