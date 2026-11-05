import { existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';

// Run scripts from the repository root as before. A shared run ID lets separate
// producer/consumer processes use fresh fixtures without overwriting evidence.
const runId = process.env.QA_RUN_ID || 'latest';
if (!/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}$/.test(runId)) throw new Error('Invalid QA_RUN_ID');
const artifactRoot = `docs/figma/qa/artifacts/${runId}/screenshots`;
const historicalRoot = 'docs/figma/qa/screenshots';

function relativePath(value) {
    if (!value || value.includes('\\') || value.includes(':') || value.startsWith('/') || value.split('/').some(part => !part || part === '.' || part === '..')) {
        throw new Error(`Invalid QA relative path: ${value}`);
    }
    return value;
}

export function qaOutput(relative) {
    const output = `${artifactRoot}/${relativePath(relative)}`;
    mkdirSync(output, { recursive: true });
    return output;
}

export function qaInput(relative) {
    relativePath(relative);
    const fresh = `${artifactRoot}/${relative}`;
    return existsSync(fresh) ? fresh : `${historicalRoot}/${relative}`;
}

export function qaReferenceLink(fromDirectory, relative) {
    return path.posix.relative(fromDirectory, qaInput(relative));
}
