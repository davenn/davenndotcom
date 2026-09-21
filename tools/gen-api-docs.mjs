#!/usr/bin/env node
//
// Generates the API reference from api.php, in two forms:
//
//   docs/api.md    in-repo reference, read on GitHub or in an editor
//   docs/api.html  public page on davenn.com/docs/, in the site's house style
//
// api.php is the only backend on the site and every endpoint in it follows one
// shape, so the reference can be derived from the source instead of maintained
// by hand. Hand-written docs for 69 endpoints go stale within a week; these
// cannot, because CI regenerates them and fails if the result differs from
// what is committed.
//
//   node tools/gen-api-docs.mjs          write both files
//   node tools/gen-api-docs.mjs --check  exit 1 if either is out of date
//
// Both outputs come from one parse, so the two can never drift apart. Nothing
// here may depend on the clock or the environment — the output must be
// byte-identical between runs or --check is meaningless.
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
const OUT_MD = join(ROOT, 'docs', 'api.md');
const OUT_HTML = join(ROOT, 'docs', 'api.html');

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

// ── Parse ──────────────────────────────────────────────────────────────────

const src = readFileSync(SOURCE, 'utf8');
const lines = src.split(/\r?\n/);

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
  });
}

const tables = [];
for (const m of src.matchAll(TABLE)) {
  const columns = m[2]
    .split('\n')
    .map(l => l.trim().replace(/,$/, ''))
    .filter(Boolean);
  tables.push({ name: m[1], columns, group: tableGroupFor(m[1]) });
}

const ordered = [...endpoints].sort((a, b) => {
  const ga = GROUP_ORDER.indexOf(a.group), gb = GROUP_ORDER.indexOf(b.group);
  if (ga !== gb) return ga - gb;
  return a.action.localeCompare(b.action) || a.method.localeCompare(b.method);
});

const undocumented = ordered.filter(e => !e.description && !e.params);
const usedGroups = GROUP_ORDER.filter(g => ordered.some(e => e.group === g));
const tableGroupsUsed = [...GROUP_ORDER, 'Other']
  .filter(g => tables.some(t => t.group === g));

// Operator endpoints are left out of the public page. This is not what keeps
// them safe — the shared secret does — but there is no reason to hand a
// stranger the list of things worth guessing a secret for. The in-repo
// markdown stays complete.
// Escape hatch for endpoints that are operator tools but are not admin-gated
// in code, so authFor() cannot recognise them. Empty is the healthy state:
// needing an entry here means an endpoint is exposed in a way the code does
// not express, which is worth fixing rather than papering over. cp_diag lived
// here until it was given the guard it needed.
const OPERATOR_ACTIONS = new Set();

const isOperator = e => e.auth.startsWith('Admin') || OPERATOR_ACTIONS.has(e.action);
const publicEndpoints = ordered.filter(e => !isOperator(e));
const publicGroups = GROUP_ORDER.filter(g => publicEndpoints.some(e => e.group === g));
const operatorCount = ordered.length - publicEndpoints.length;

// ── Markdown ───────────────────────────────────────────────────────────────

function renderMarkdown() {
  const out = [];
  const esc = s => s.replace(/\|/g, '\\|');

  out.push('# API reference');
  out.push('');
  out.push('<!-- Generated from api.php by tools/gen-api-docs.mjs. Do not edit by hand.');
  out.push('     Endpoint descriptions come from the comment block above each branch in');
  out.push('     api.php — edit them there and regenerate. -->');
  out.push('');
  out.push('Every app on davenn.com is served by a single `api.php`. Endpoints are selected');
  out.push('by an `action` query parameter and always return JSON.');
  out.push('');
  out.push('```');
  out.push('GET|POST|DELETE  /api.php?action=<action>');
  out.push('```');
  out.push('');
  out.push(`**${endpoints.length} endpoints · ${tables.length} tables**`);
  out.push('');

  // Render detail first so every heading's anchor is known by the time the
  // index that links to them is built.
  const slug = slugger();
  const detail = [];
  for (const group of usedGroups) {
    detail.push(`## ${group}`, '');
    for (const e of ordered.filter(x => x.group === group)) {
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

  out.push('## Endpoints at a glance');
  out.push('');
  out.push('| Action | Method | App | Auth |');
  out.push('|---|---|---|---|');
  for (const e of ordered) {
    out.push(`| [\`${e.action}\`](#${e.anchor}) | ${e.method} | ${e.group} | ${esc(e.auth)} |`);
  }
  out.push('');
  out.push(...detail);

  out.push('## Schema');
  out.push('');
  out.push('All tables live in one database and are created on demand — the');
  out.push('`CREATE TABLE IF NOT EXISTS` block at the top of `api.php` runs on every');
  out.push('request. New tables appear automatically; column changes to an existing');
  out.push('table need a manual `ALTER` against the live database.');
  out.push('');
  for (const group of tableGroupsUsed) {
    out.push(`### ${group}`, '');
    for (const t of tables.filter(x => x.group === group)) {
      out.push(`#### \`${t.name}\``, '', '```sql');
      for (const c of t.columns) out.push(c);
      out.push('```', '');
    }
  }

  // Endpoints with no comment block above them. Listing these makes the gap
  // actionable — each one is fixed by writing a comment in api.php, not here.
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

  return out.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
}

// ── HTML ───────────────────────────────────────────────────────────────────

const h = s => String(s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;');

// Comments are plain prose with occasional `inline code`. Escape first, then
// promote backticks, so nothing in the source can inject markup.
const prose = s => h(s)
  .replace(/`([^`]+)`/g, '<code class="inline">$1</code>')
  .split(/\n{2,}/)
  .map(p => `<p class="op-desc">${p.replace(/\n/g, ' ')}</p>`)
  .join('\n');

const inlineCode = s => h(s).replace(/`([^`]+)`/g, '<code class="inline">$1</code>');

function renderHtml() {
  const o = [];
  o.push(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>API Reference — davenn.com</title>
<meta name="description" content="Every endpoint on davenn.com: actions, authentication and schema.">
<meta name="theme-color" content="#111110">
<!-- Generated from api.php by tools/gen-api-docs.mjs. Do not edit by hand. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>
    (function () {
        var t;
        try { t = localStorage.getItem('docs_theme'); } catch (e) {}
        if (t !== 'light' && t !== 'dark') {
            t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
    })();
</script>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root, [data-theme="light"] {
        color-scheme: light;
        --page: #fafafa; --surface: #ffffff; --surface-2: #f7f7f7; --border: #e4e4e0;
        --text-primary: #3b4151; --text-body: #454750; --text-muted: #8a8f98;
        --code-bg: #41444e; --code-fg: #ffffff;
    }
    [data-theme="dark"] {
        color-scheme: dark;
        --page: #0a0a0a; --surface: #131313; --surface-2: #1a1a1a; --border: #2a2a2a;
        --text-primary: #e8e8e6; --text-body: #c3c2b7; --text-muted: #8a8f98;
        --code-bg: #1e1f24; --code-fg: #e8e8e6;
    }
    :root {
        --nav-bg: #111110; --nav-border: rgba(255,255,255,0.08);
        /* Method hues are fixed, never themed, and always paired with the word
           itself — the badge never carries the meaning on colour alone. */
        --get: #61affe; --post: #49cc90; --del: #f93e3e;
        --font-ui: system-ui, -apple-system, 'Segoe UI', sans-serif;
        --font-mono: 'DM Mono', ui-monospace, monospace;
        --nav-h: 52px;
    }

    body {
        font-family: var(--font-ui); background: var(--page); color: var(--text-primary);
        -webkit-font-smoothing: antialiased;
        padding-top: calc(var(--nav-h) + env(safe-area-inset-top));
        transition: background 0.2s, color 0.2s;
    }

    nav {
        position: fixed; top: 0; left: 0; right: 0;
        height: calc(var(--nav-h) + env(safe-area-inset-top));
        padding: env(safe-area-inset-top) 1.5rem 0;
        background: var(--nav-bg); border-bottom: 1px solid var(--nav-border);
        display: flex; align-items: center; justify-content: space-between; z-index: 200;
    }
    .nav-logo { font-family: var(--font-mono); font-size: 13px; color: rgba(255,255,255,0.4); text-decoration: none; }
    .nav-logo strong { color: #fff; }
    .nav-right { display: flex; align-items: center; gap: 1.2rem; }
    .nav-back { font-family: var(--font-mono); font-size: 11px; color: rgba(255,255,255,0.45); text-decoration: none; letter-spacing: 0.05em; text-transform: uppercase; }
    .nav-back:hover { color: #fff; }
    .theme-toggle { width: 44px; height: 24px; cursor: pointer; background: none; border: none; padding: 0; flex: none; }
    .theme-toggle-track { width: 44px; height: 24px; border-radius: 12px; background: #333; border: 1px solid #555; display: flex; align-items: center; padding: 0 3px; transition: background 0.2s; }
    [data-theme="light"] .theme-toggle-track { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.35); }
    .theme-toggle-thumb { width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; transition: transform 0.2s, background 0.2s; }
    [data-theme="light"] .theme-toggle-thumb { transform: translateX(20px); background: #fff; }
    [data-theme="dark"]  .theme-toggle-thumb { transform: translateX(0);    background: #666; }

    main { max-width: 820px; margin: 0 auto; padding: 36px 1.25rem 88px; }

    h1 { font-size: 1.9rem; font-weight: 700; letter-spacing: -0.02em; }
    .lede { font-size: 14px; color: var(--text-body); margin-top: 8px; line-height: 1.6; }
    .base {
        font-family: var(--font-mono); font-size: 13px; color: var(--text-body);
        background: var(--surface); border: 1px solid var(--border); border-radius: 4px;
        padding: 9px 12px; margin-top: 16px; overflow-x: auto; white-space: nowrap;
    }
    .counts { font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); margin-top: 12px; }

    .filter-row { display: flex; gap: 8px; align-items: center; margin: 22px 0 4px; }
    .filter-row input {
        flex: 1; min-width: 0; font-family: var(--font-mono); font-size: 13px;
        padding: 8px 11px; border: 1px solid var(--border); border-radius: 4px;
        background: var(--surface); color: var(--text-primary);
    }
    .filter-row input::placeholder { color: var(--text-muted); }
    .no-match { font-size: 13px; color: var(--text-muted); padding: 18px 2px; }

    .tag {
        font-size: 1.15rem; font-weight: 600; margin: 32px 0 10px;
        padding-bottom: 8px; border-bottom: 1px solid var(--border);
    }

    .op { border-radius: 4px; margin-bottom: 10px; border: 1px solid; }
    .op.get    { border-color: var(--get);  background: rgba(97,175,254,0.08); }
    .op.post   { border-color: var(--post); background: rgba(73,204,144,0.08); }
    .op.delete { border-color: var(--del);  background: rgba(249,62,62,0.08); }

    .op-head {
        width: 100%; display: flex; align-items: center; gap: 10px;
        padding: 8px 10px; background: none; border: none; cursor: pointer; text-align: left;
    }
    .method {
        flex: none; min-width: 62px; text-align: center; color: #fff;
        font-family: var(--font-ui); font-size: 12px; font-weight: 700;
        padding: 6px 0; border-radius: 3px;
    }
    .get .method    { background: var(--get); }
    .post .method   { background: var(--post); }
    .delete .method { background: var(--del); }
    .op-path { font-family: var(--font-mono); font-size: 13px; color: var(--text-primary); word-break: break-all; }
    .op-sum { flex: 1; font-size: 13px; color: var(--text-muted); text-align: right; }
    .caret { flex: none; color: var(--text-muted); font-size: 11px; transition: transform 0.15s; }
    .op.open .caret { transform: rotate(90deg); }
    @media (max-width: 620px) {
        .op-head { flex-wrap: wrap; }
        .op-sum { flex-basis: 100%; text-align: left; }
    }

    .op-body { padding: 4px 14px 16px; border-top: 1px solid var(--border); background: var(--surface); border-radius: 0 0 3px 3px; }
    .op-desc { font-size: 13.5px; line-height: 1.65; color: var(--text-body); margin: 14px 0; }
    h4 {
        font-family: var(--font-ui); font-size: 12px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--text-muted); margin: 18px 0 8px;
    }

    table { border-collapse: collapse; width: 100%; font-size: 13px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 600; }
    td { color: var(--text-body); }
    .pname { font-family: var(--font-mono); color: var(--text-primary); white-space: nowrap; }
    .table-wrap { overflow-x: auto; }

    pre {
        background: var(--code-bg); color: var(--code-fg); border-radius: 4px;
        padding: 12px 14px; margin: 8px 0 4px; overflow-x: auto;
    }
    pre code { font-family: var(--font-mono); font-size: 12.5px; line-height: 1.6; display: block; white-space: pre; }
    code.inline {
        font-family: var(--font-mono); font-size: 0.9em; background: var(--surface-2);
        border: 1px solid var(--border); border-radius: 3px; padding: 1px 5px; color: var(--text-primary);
    }
    .src { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-muted); margin-top: 14px; }

    .schema h3 { font-size: 13px; font-family: var(--font-mono); margin: 22px 0 6px; color: var(--text-primary); }
    .schema .group { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-top: 30px; font-weight: 600; }

    .todo { margin-top: 40px; }
    .todo ul { padding-left: 20px; margin-top: 10px; }
    .todo li { font-family: var(--font-mono); font-size: 12.5px; line-height: 1.8; color: var(--text-body); }
    .note { font-size: 13.5px; line-height: 1.7; color: var(--text-body); }

    footer {
        border-top: 1px solid var(--border); margin-top: 48px; padding-top: 18px;
        font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);
    }
    footer a { color: var(--text-muted); }
    [hidden] { display: none !important; }
</style>
</head>
<body>

<nav>
    <a class="nav-logo" href="/"><strong>davenn</strong>dotcom</a>
    <div class="nav-right">
        <button class="theme-toggle" id="theme-toggle" type="button" title="Toggle theme" aria-label="Toggle theme">
            <div class="theme-toggle-track"><div class="theme-toggle-thumb" id="toggle-icon">🌙</div></div>
        </button>
        <a class="nav-back" href="/docs/">← Docs</a>
    </div>
</nav>

<main>
    <h1>API Reference</h1>
    <p class="lede">Every app on davenn.com is served by a single <code class="inline">api.php</code>. Endpoints are selected by an <code class="inline">action</code> query parameter and always return JSON.</p>
    <div class="base">https://davenn.com/api.php?action=&lt;action&gt;</div>
    <p class="counts">${publicEndpoints.length} endpoints · ${tables.length} tables</p>

    <div class="filter-row">
        <input type="search" id="filter" placeholder="Filter endpoints…" aria-label="Filter endpoints">
    </div>
    <p class="no-match" id="no-match" hidden>Nothing matches that.</p>
`);

  for (const group of publicGroups) {
    o.push(`    <section class="grp">`);
    o.push(`        <h2 class="tag">${h(group)}</h2>`);
    for (const e of publicEndpoints.filter(x => x.group === group)) {
      const cls = e.method.toLowerCase();
      const id = `${cls}-${e.action}`;
      const summary = e.description ? e.description.split('\n')[0] : '';
      o.push(`        <div class="op ${cls}" id="${h(id)}" data-search="${h((e.action + ' ' + e.method + ' ' + group + ' ' + summary).toLowerCase())}">`);
      o.push(`            <button class="op-head" type="button" aria-expanded="false">`);
      o.push(`                <span class="method">${h(e.method)}</span>`);
      o.push(`                <span class="op-path">?action=${h(e.action)}</span>`);
      o.push(`                <span class="op-sum">${h(summary)}</span>`);
      o.push(`                <span class="caret">▶</span>`);
      o.push(`            </button>`);
      o.push(`            <div class="op-body" hidden>`);
      if (e.description) o.push(`                ${prose(e.description)}`);
      o.push(`                <h4>Authentication</h4>`);
      o.push(`                <p class="op-desc">${inlineCode(e.auth)}</p>`);
      if (e.params) {
        o.push(`                <h4>Takes</h4>`);
        o.push(`                <p class="op-desc">${inlineCode(e.params)}</p>`);
      }
      if (e.query.length) {
        o.push(`                <h4>Query parameters</h4>`);
        o.push(`                <div class="table-wrap"><table><tbody>`);
        for (const q of e.query) {
          o.push(`                    <tr><td class="pname">${h(q)}</td></tr>`);
        }
        o.push(`                </tbody></table></div>`);
      }
      o.push(`            </div>`);
      o.push(`        </div>`);
    }
    o.push(`    </section>`);
  }

  o.push(`
    <section class="schema">
        <h2 class="tag">Schema</h2>
        <p class="note">All tables live in one database and are created on demand — the <code class="inline">CREATE TABLE IF NOT EXISTS</code> block at the top of <code class="inline">api.php</code> runs on every request. New tables appear automatically; column changes to an existing table need a manual <code class="inline">ALTER</code> against the live database.</p>`);
  for (const group of tableGroupsUsed) {
    o.push(`        <p class="group">${h(group)}</p>`);
    for (const t of tables.filter(x => x.group === group)) {
      o.push(`        <h3>${h(t.name)}</h3>`);
      o.push(`        <pre><code>${t.columns.map(h).join('\n')}</code></pre>`);
    }
  }
  o.push(`    </section>`);

  const publicUndocumented = undocumented.filter(e => !isOperator(e));
  if (publicUndocumented.length) {
    o.push(`
    <section class="todo">
        <h2 class="tag">Not yet described</h2>
        <p class="note">These endpoints have no written description yet.</p>
        <ul>`);
    for (const e of publicUndocumented) {
      o.push(`            <li>${h(e.method)} ?action=${h(e.action)}</li>`);
    }
    o.push(`        </ul>
    </section>`);
  }

  if (operatorCount) {
    o.push(`
    <section class="todo">
        <p class="note">${operatorCount} operator endpoints are not listed here.</p>
    </section>`);
  }

  o.push(`
    <footer>
        <a href="/">davenn.com</a> &nbsp;·&nbsp; <a href="/docs/">Docs</a> &nbsp;·&nbsp; <a href="/privacy.html">Privacy</a> &nbsp;·&nbsp; <a href="/terms.html">Terms</a>
    </footer>
</main>

<script>
(function () {
    'use strict';

    var toggle = document.getElementById('theme-toggle');
    var icon = document.getElementById('toggle-icon');
    icon.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '🌙' : '☀️';
    toggle.addEventListener('click', function () {
        var t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', t);
        icon.textContent = t === 'dark' ? '🌙' : '☀️';
        try { localStorage.setItem('docs_theme', t); } catch (e) {}
    });

    var ops = Array.prototype.slice.call(document.querySelectorAll('.op'));

    ops.forEach(function (op) {
        var head = op.querySelector('.op-head');
        var body = op.querySelector('.op-body');
        head.addEventListener('click', function () {
            var open = op.classList.toggle('open');
            body.hidden = !open;
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    // Deep link: /docs/api.html#get-bg_latest opens that endpoint.
    function openHash() {
        var id = location.hash.slice(1);
        if (!id) return;
        var op = document.getElementById(id);
        if (!op || !op.classList.contains('op')) return;
        op.classList.add('open');
        op.querySelector('.op-body').hidden = false;
        op.querySelector('.op-head').setAttribute('aria-expanded', 'true');
        op.scrollIntoView({ block: 'center' });
    }
    openHash();
    window.addEventListener('hashchange', openHash);

    var filter = document.getElementById('filter');
    var noMatch = document.getElementById('no-match');
    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        var shown = 0;
        ops.forEach(function (op) {
            var hit = !q || op.getAttribute('data-search').indexOf(q) !== -1;
            op.hidden = !hit;
            if (hit) shown++;
        });
        // Hide a group heading when everything under it is filtered out.
        Array.prototype.forEach.call(document.querySelectorAll('.grp'), function (g) {
            var any = Array.prototype.some.call(g.querySelectorAll('.op'), function (o) { return !o.hidden; });
            g.hidden = !any;
        });
        noMatch.hidden = shown !== 0;
    });
})();
</script>

</body>
</html>`);

  return o.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
}

// ── Write or check ─────────────────────────────────────────────────────────

const outputs = [[OUT_MD, renderMarkdown()], [OUT_HTML, renderHtml()]];

for (const w of warnings) console.error(`warning: ${w}`);

if (process.argv.includes('--check')) {
  const stale = outputs.filter(([path, want]) => {
    let have = '';
    try { have = readFileSync(path, 'utf8'); } catch {}
    return have !== want;
  });
  if (stale.length) {
    for (const [path] of stale) {
      console.error(`out of date: ${path.slice(ROOT.length + 1).replace(/\\/g, '/')}`);
    }
    console.error('Run: node tools/gen-api-docs.mjs');
    process.exit(1);
  }
  console.log(`Docs are up to date (${endpoints.length} endpoints, ${tables.length} tables).`);
} else {
  mkdirSync(dirname(OUT_MD), { recursive: true });
  for (const [path, content] of outputs) writeFileSync(path, content);
  const described = endpoints.length - undocumented.length;
  console.log(
    `Wrote docs/api.md and docs/api.html — ${endpoints.length} endpoints, ` +
    `${tables.length} tables, ${described}/${endpoints.length} described.`
  );
}
