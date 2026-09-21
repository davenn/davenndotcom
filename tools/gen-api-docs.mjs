#!/usr/bin/env node
//
// Generates docs/api.md from api.php.
//
// api.php is the only backend on the site and every endpoint in it follows one
// shape, so the reference can be derived from the source instead of maintained
// by hand. Hand-written docs for 69 endpoints go stale within a week; these
// cannot, because CI regenerates them and fails if the result differs from
// what is committed.
//
//   node tools/gen-api-docs.mjs          write docs/api.md
//   node tools/gen-api-docs.mjs --check  exit 1 if docs/api.md is out of date
//
// What it reads, and the conventions it depends on:
//
//   * Dispatch branches, which must stay in the form
//         if ($method === 'GET' && $action === 'thing') {
//     starting at column 0 and closing with a '}' at column 0.
//   * The contiguous // comment block directly above a branch, which becomes
//     that endpoint's description. Write those comments for a reader — they
//     are the documentation. A first line of "GET ?action=thing ..." is
//     recognised and its trailing part is used as the parameter summary.
//   * CREATE TABLE IF NOT EXISTS blocks, which become the schema section.
//
// No dependencies, by design — this repo has no package manager.

import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SOURCE = join(ROOT, 'api.php');
const OUTPUT = join(ROOT, 'docs', 'api.md');

const BRANCH = /^if \(\$method === '(\w+)' && \$action === '([a-z_0-9]+)'\) \{/;
const TABLE = /CREATE TABLE IF NOT EXISTS (\w+) \(([\s\S]*?)\n\s*\)"/g;

// Which app each endpoint belongs to. Prefixed actions are grouped by prefix;
// the unprefixed ones predate that convention and are listed explicitly.
const PREFIX_GROUPS = [
  ['tb_', 'Toolshare'],
  ['ft_', 'Flight Tracker'],
  ['bg_', 'Glucose'],
  ['dt_', 'Daily Tasks'],
  ['cp_', 'Confidence Pool'],
  ['fb_', 'Face Breaker'],
];

const EXPLICIT_GROUPS = {
  week: 'Meeting Cost Timer',
  save: 'Meeting Cost Timer',
  clear_week: 'Meeting Cost Timer',
  track_sessions: 'Track Timer',
  save_track_session: 'Track Timer',
  delete_track_session: 'Track Timer',
  clear_track_sessions: 'Track Timer',
  reaction_week: 'Reaction Test',
  save_reaction: 'Reaction Test',
  clear_reaction_week: 'Reaction Test',
  subscribe: 'Update Notifications',
  unsubscribe: 'Update Notifications',
  notify_subscribers: 'Update Notifications',
};

// Order groups appear in the output.
const GROUP_ORDER = [
  'Meeting Cost Timer', 'Track Timer', 'Toolshare', 'Flight Tracker',
  'Glucose', 'Confidence Pool', 'Daily Tasks', 'Face Breaker',
  'Reaction Test', 'Update Notifications',
];

// Table prefixes, for the schema section.
const TABLE_GROUPS = [
  ['tb_', 'Toolshare'], ['ft_', 'Flight Tracker'], ['bg_', 'Glucose'],
  ['dt_', 'Daily Tasks'], ['cp_', 'Confidence Pool'], ['fb_', 'Face Breaker'],
];

const EXPLICIT_TABLES = {
  meetings: 'Meeting Cost Timer',
  track_sessions: 'Track Timer',
  reaction_scores: 'Reaction Test',
  subscribers: 'Update Notifications',
};

function groupFor(action) {
  for (const [prefix, name] of PREFIX_GROUPS) {
    if (action.startsWith(prefix)) return name;
  }
  return EXPLICIT_GROUPS[action] ?? null;
}

function tableGroupFor(table) {
  for (const [prefix, name] of TABLE_GROUPS) {
    if (table.startsWith(prefix)) return name;
  }
  return EXPLICIT_TABLES[table] ?? 'Other';
}

// Auth is four unrelated schemes. Detect which one a branch body enforces.
function authFor(body) {
  if (/\bftRequireAuth\(/.test(body)) return 'Flight Tracker account — `X-Auth-Token`';
  if (/\brequireAuth\(/.test(body)) return 'Toolshare account — `X-Auth-Token`';
  if (/\bbgRequireRead\(/.test(body)) return 'Glucose read token — `X-BG-Token` or `?token=`';
  if (/HTTP_X_ADMIN_SECRET/.test(body)) return 'Admin — `X-Admin-Secret`';
  if (/\$_GET\['token'\]/.test(body)) return 'Single-use link token — `?token=`';
  if (/\bauthUser\(/.test(body)) return 'Optional Toolshare account — `X-Auth-Token`';
  return 'None';
}

function queryParams(body) {
  const found = new Set();
  for (const m of body.matchAll(/\$_GET\['(\w+)'\]/g)) {
    if (m[1] !== 'action') found.add(m[1]);
  }
  return [...found];
}

// GitHub's heading-anchor rules: lowercase, drop anything that is not a word
// character, space or hyphen, then spaces to hyphens. Duplicates get a suffix.
// Computed rather than guessed so the index links actually resolve.
function slugger() {
  const seen = new Map();
  return heading => {
    const base = heading
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-');
    const n = seen.get(base) ?? 0;
    seen.set(base, n + 1);
    return n === 0 ? base : `${base}-${n}`;
  };
}

const src = readFileSync(SOURCE, 'utf8');
const lines = src.split(/\r?\n/);

// ── Endpoints ──────────────────────────────────────────────────────────────
const endpoints = [];
const warnings = [];

for (let i = 0; i < lines.length; i++) {
  const m = lines[i].match(BRANCH);
  if (!m) continue;
  const [, method, action] = m;

  // Body runs to the first '}' at column 0.
  let end = lines.length - 1;
  for (let j = i + 1; j < lines.length; j++) {
    if (lines[j] === '}') { end = j; break; }
  }
  const body = lines.slice(i, end + 1).join('\n');

  // Walk back over the contiguous // comment block, skipping banner rules.
  const comment = [];
  for (let j = i - 1; j >= 0; j--) {
    const line = lines[j];
    if (!/^\s*\/\//.test(line)) break;
    const text = line.replace(/^\s*\/\/\s?/, '').trimEnd();
    if (/^[═─]+$/.test(text) || text === '') { if (comment.length) break; else continue; }
    comment.unshift(text);
  }

  // A leading "GET ?action=thing  extras" line is a signature, not prose.
  let params = '';
  if (comment.length) {
    const sig = comment[0].match(
      new RegExp(`^(?:GET|POST|PUT|DELETE)\\s+\\?action=${action}\\b(.*)$`)
    );
    if (sig) {
      params = sig[1].replace(/^[\s—-]+/, '').trim();
      comment.shift();
    }
  }

  const group = groupFor(action);
  if (!group) warnings.push(`no group mapped for action '${action}'`);

  endpoints.push({
    method, action, group: group ?? 'Other',
    params,
    description: comment.join('\n').trim(),
    auth: authFor(body),
    query: queryParams(body),
    line: i + 1,
    length: end - i,
  });
}

// ── Schema ─────────────────────────────────────────────────────────────────
const tables = [];
for (const m of src.matchAll(TABLE)) {
  const columns = m[2]
    .split('\n')
    .map(l => l.trim().replace(/,$/, ''))
    .filter(Boolean);
  tables.push({ name: m[1], columns, group: tableGroupFor(m[1]) });
}

// ── Render ─────────────────────────────────────────────────────────────────
const out = [];
const esc = s => s.replace(/\|/g, '\\|');

out.push('# API reference');
out.push('');
out.push('<!-- Generated from api.php by tools/gen-api-docs.mjs. Do not edit by hand.');
out.push('     Endpoint descriptions come from the comment block above each branch in');
out.push('     api.php — edit them there and regenerate. -->');
out.push('');
out.push(`Every app on davenn.com is served by a single \`api.php\`. Endpoints are selected`);
out.push('by an `action` query parameter and always return JSON.');
out.push('');
out.push('```');
out.push('GET|POST|DELETE  /api.php?action=<action>');
out.push('```');
out.push('');
out.push(`**${endpoints.length} endpoints · ${tables.length} tables**`);
out.push('');

const ordered = [...endpoints].sort((a, b) => {
  const ga = GROUP_ORDER.indexOf(a.group), gb = GROUP_ORDER.indexOf(b.group);
  if (ga !== gb) return ga - gb;
  return a.action.localeCompare(b.action) || a.method.localeCompare(b.method);
});

// Render the detail sections first so every heading's anchor is known by the
// time the index that links to them is built.
const slug = slugger();
const detail = [];
for (const group of GROUP_ORDER) {
  const inGroup = ordered.filter(e => e.group === group);
  if (!inGroup.length) continue;
  detail.push(`## ${group}`, '');
  for (const e of inGroup) {
    const heading = `${e.method} \`?action=${e.action}\``;
    e.anchor = slug(heading);
    detail.push(`### ${heading}`, '');
    if (e.description) detail.push(e.description, '');
    detail.push(`- **Auth:** ${e.auth}`);
    if (e.params) detail.push(`- **Takes:** ${esc(e.params)}`);
    if (e.query.length) {
      detail.push(`- **Query parameters:** ${e.query.map(q => `\`${q}\``).join(', ')}`);
    }
    detail.push(`- **Source:** [\`api.php:${e.line}\`](../api.php#L${e.line})`, '');
  }
}

// Index
out.push('## Endpoints at a glance');
out.push('');
out.push('| Action | Method | App | Auth |');
out.push('|---|---|---|---|');
for (const e of ordered) {
  out.push(`| [\`${e.action}\`](#${e.anchor}) | ${e.method} | ${e.group} | ${esc(e.auth)} |`);
}
out.push('');
out.push(...detail);

// Schema
out.push('## Schema');
out.push('');
out.push('All tables live in one database and are created on demand — the');
out.push('`CREATE TABLE IF NOT EXISTS` block at the top of `api.php` runs on every');
out.push('request. New tables appear automatically; column changes to an existing');
out.push('table need a manual `ALTER` against the live database.');
out.push('');
const tableGroupsSeen = [...GROUP_ORDER, 'Other'];
for (const group of tableGroupsSeen) {
  const inGroup = tables.filter(t => t.group === group);
  if (!inGroup.length) continue;
  out.push(`### ${group}`);
  out.push('');
  for (const t of inGroup) {
    out.push(`#### \`${t.name}\``);
    out.push('');
    out.push('```sql');
    for (const c of t.columns) out.push(c);
    out.push('```');
    out.push('');
  }
}

// Endpoints with no comment block above them. Listing these makes the gap
// actionable — each one is fixed by writing a comment in api.php, not here.
const undocumented = ordered.filter(e => !e.description && !e.params);
if (undocumented.length) {
  out.push('## Not yet described');
  out.push('');
  out.push('These endpoints have no comment block above them in `api.php`. To');
  out.push('document one, write the comment there and regenerate — do not edit');
  out.push('this file.');
  out.push('');
  for (const e of undocumented) {
    out.push(`- \`${e.method} ?action=${e.action}\` — [\`api.php:${e.line}\`](../api.php#L${e.line})`);
  }
  out.push('');
}

const rendered = out.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';

for (const w of warnings) console.error(`warning: ${w}`);

if (process.argv.includes('--check')) {
  let current = '';
  try { current = readFileSync(OUTPUT, 'utf8'); } catch {}
  if (current !== rendered) {
    console.error(
      'docs/api.md is out of date.\n' +
      'Run: node tools/gen-api-docs.mjs'
    );
    process.exit(1);
  }
  console.log(`docs/api.md is up to date (${endpoints.length} endpoints, ${tables.length} tables).`);
} else {
  mkdirSync(dirname(OUTPUT), { recursive: true });
  writeFileSync(OUTPUT, rendered);
  const described = endpoints.length - undocumented.length;
  console.log(
    `Wrote docs/api.md — ${endpoints.length} endpoints, ${tables.length} tables. ` +
    `${described}/${endpoints.length} described.`
  );
}
