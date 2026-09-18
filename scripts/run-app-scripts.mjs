#!/usr/bin/env node

import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawn } from 'node:child_process';

const script = process.argv[2];
const parallel = process.argv.includes('--parallel');

if (!script) {
	console.error('Usage: node scripts/run-app-scripts.mjs <script> [--parallel]');
	process.exit(2);
}

const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const apps = readdirSync('apps', { withFileTypes: true })
	.filter((entry) => entry.isDirectory())
	.map((entry) => entry.name)
	.filter((app) => {
		try {
			const packageJson = JSON.parse(readFileSync(join('apps', app, 'package.json'), 'utf8'));
			return typeof packageJson.scripts?.[script] === 'string';
		} catch {
			return false;
		}
	})
	.sort();

if (apps.length === 0) {
	console.log(`No apps define an npm ${script} script.`);
	process.exit(0);
}

const run = (app) => new Promise((resolve) => {
	console.log(`\n==> ${app}: npm run ${script}`);
	const child = spawn(npm, ['run', script], {
		cwd: join(process.cwd(), 'apps', app),
		stdio: 'inherit',
	});
	child.on('close', (code, signal) => resolve({ app, code: code ?? 1, signal }));
});

const results = parallel ? await Promise.all(apps.map(run)) : [];
if (!parallel) {
	for (const app of apps) {
		results.push(await run(app));
		if (results.at(-1).code !== 0) {
			break;
		}
	}
}

const failed = results.filter(({ code }) => code !== 0);
if (failed.length > 0) {
	console.error(`\n${failed.length} app script(s) failed: ${failed.map(({ app }) => app).join(', ')}`);
	process.exit(failed[0].code || 1);
}
