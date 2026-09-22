#!/usr/bin/env node

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawn } from 'node:child_process';

const script = process.argv[2];
const parallel = process.argv.includes('--parallel');
const skipIfToolsMissing = process.argv.includes('--skip-if-tools-missing');

if (!script) {
	console.error('Usage: node scripts/run-app-scripts.mjs <script> [--parallel]');
	process.exit(2);
}

// Some deployment builders run `npm prune --omit=dev` before `npm run build`.
// Frontend build tools are dev dependencies, while this repository already
// contains the compiled app assets, so production-only installs should not
// fail merely because Vite/Webpack are unavailable.
if (script === 'build' && skipIfToolsMissing) {
	const tools = ['vite', 'webpack'];
	const hasBuildTool = tools.some((tool) => existsSync(join(process.cwd(), 'node_modules', '.bin', tool)));
	if (!hasBuildTool) {
		console.log('Frontend build tools are not installed; keeping the committed frontend assets.');
		process.exit(0);
	}
}

// Vite apps in this repository may ship only their compiled assets in `js/`,
// without the `index.html` entry point required to re-run the bundler.
// Rebuilding those apps is both impossible and unnecessary, so skip them.
const hasViteEntry = (app) => existsSync(join('apps', app, 'index.html'));

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
	// Skip apps without a bundler entry point; their compiled assets in `js/`
	// are committed to the repository already.
	.filter((app) => script !== 'build' || hasViteEntry(app))
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
