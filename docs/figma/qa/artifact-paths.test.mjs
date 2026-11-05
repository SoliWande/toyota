import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync, spawnSync } from 'node:child_process';

process.env.QA_RUN_ID = `routing-test-20261105-${process.pid}`;
const { qaOutput, qaInput, qaReferenceLink } = await import('./artifact-paths.mjs');
const historical = 'docs/figma/qa/screenshots/competition-leaderboard/final-qa/matrix.json';
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const before = hash(historical);

test('producer and consumer share a run; old matrix remains an immutable fallback', () => {
    assert.equal(qaInput('competition-leaderboard/final-qa/matrix.json'), historical);
    const output = qaOutput('competition-leaderboard/final-qa');
    const generated = `${output}/matrix.json`;
    fs.writeFileSync(generated, JSON.stringify({ source: 'fresh isolated routing test' }));
    assert.equal(qaInput('competition-leaderboard/final-qa/matrix.json'), generated);
    assert.equal(JSON.parse(fs.readFileSync(generated)).source, 'fresh isolated routing test');
    assert.equal(hash(historical), before);
});

test('PHP producer writes to the exact directory consumed by Node', () => {
    const php = "require 'docs/figma/qa/artifact-paths.php'; echo qa_artifact_output('competition-leaderboard/final-qa');";
    const output = execFileSync('php', ['-r', php], { encoding: 'utf8', env: process.env }).trim();
    assert.equal(fs.realpathSync(output), fs.realpathSync(qaOutput('competition-leaderboard/final-qa')));
    assert.equal(hash(historical), before);
});

test('gallery references resolve to retained Figma files across the new directory depth', () => {
    const output = qaOutput('gallery-test');
    const link = qaReferenceLink(output, 'competition-awards/reference/desktop.png');
    assert.equal(fs.realpathSync(path.resolve(output, link)), fs.realpathSync('docs/figma/qa/screenshots/competition-awards/reference/desktop.png'));
});

test('path and run ID traversal cannot create output outside the ignored directory', () => {
    for (const value of ['../escape', '/absolute', 'C:/outside', 'a\\b', 'a//b']) {
        assert.throws(() => qaOutput(value), /Invalid QA/);
        assert.throws(() => qaInput(value), /Invalid QA/);
    }
    const child = spawnSync(process.execPath, ['--input-type=module', '-e', "await import('./docs/figma/qa/artifact-paths.mjs');"], { env: { ...process.env, QA_RUN_ID: '../escape' }, encoding: 'utf8' });
    assert.notEqual(child.status, 0);
    assert.match(child.stderr, /Invalid QA_RUN_ID/);
});

test('every visual writer using the helper imports it explicitly', () => {
    for (const name of fs.readdirSync('docs/figma/qa').filter(name => name.endsWith('.mjs') && !name.startsWith('artifact-paths'))) {
        const text = fs.readFileSync(`docs/figma/qa/${name}`, 'utf8');
        if (text.includes('qaOutput(')) assert.ok(text.includes("from './artifact-paths.mjs'"), name);
    }
});
