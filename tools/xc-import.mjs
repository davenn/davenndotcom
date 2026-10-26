#!/usr/bin/env node
// Bulk MileSplit import for WildcatsXC: the "MileSplit link" button, run over
// a list of meet links. Repo tooling, not deployed.
//
// Each link goes through the site's own ?action=xc_milesplit, so it is read by
// the same parser, identifies as the same client and skips PRO-only files the
// same way as a link pasted in the app. This script only decides which links
// and which runners: it keeps runners from the schools in GROUPS (State by
// default), then saves through ?action=xc_save_meet, which validates as the
// app does.
//
//   XC_PIN=… node tools/xc-import.mjs links.txt              # dry run: report only
//   XC_PIN=… node tools/xc-import.mjs --commit links.txt     # save
//
// Options:
//   --commit            save; without it nothing is written
//   --groups a,b        whose runners to keep: state (default), section, conference, all
//   --force             save as its own meet even if one saved that day shares a school
//   --delay N           seconds between MileSplit reads (default 5, minimum 2)
//   --base URL          site to talk to (default https://davenn.com)
//
// Links come from the arguments and/or files of one link per line ('#' starts
// a comment). Reads happen one at a time with a pause between them. A refusal
// from MileSplit (blocked, rate-limited, unreachable) stops the run: it is not
// retried and nothing here tries to get around it.

import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

function parseArgs(argv) {
    const o = { commit: false, force: false, groups: ['state'], delay: 5, base: 'https://davenn.com', links: [] };
    for (let i = 0; i < argv.length; i++) {
        const a = argv[i];
        if (a === '--commit') o.commit = true;
        else if (a === '--force') o.force = true;
        else if (a === '--groups') o.groups = argv[++i].split(',').map(s => s.trim()).filter(Boolean);
        else if (a === '--delay') o.delay = Math.max(2, Number(argv[++i]) || 5);
        else if (a === '--base') o.base = argv[++i].replace(/\/+$/, '');
        else if (a.startsWith('--')) throw new Error('Unknown option ' + a);
        else if (/^https?:\/\//.test(a)) o.links.push(a);
        else if (existsSync(a)) readFileSync(a, 'utf8').split(/\r?\n/).map(l => l.replace(/#.*/, '').trim()).filter(Boolean).forEach(l => o.links.push(l));
        else throw new Error('Not a link or a file: ' + a);
    }
    return o;
}

// GROUPS and schoolKey() are read out of wildcatsxc.html itself, so the
// school lists and their aliases live in one place.
function loadGroups() {
    const html = readFileSync(join(ROOT, 'wildcatsxc.html'), 'utf8');
    const groups = html.match(/^const GROUPS = (\{[\s\S]*?\n\});$/m);
    const key = html.match(/^const schoolKey = (.+);$/m);
    if (!groups || !key) throw new Error('Could not find GROUPS or schoolKey in wildcatsxc.html');
    return { GROUPS: new Function('return ' + groups[1])(), schoolKey: new Function('return ' + key[1])() };
}

/** Every key a group's schools match under (list names and aliases), as wildcatsxc.html's groupKeys(). */
function targetKeys(GROUPS, schoolKey, names) {
    const keys = new Map();
    const pick = names.includes('all') ? Object.keys(GROUPS) : names;
    for (const n of pick) {
        const g = GROUPS[n];
        if (!g) throw new Error(`No group "${n}"; there is ${Object.keys(GROUPS).join(', ')}`);
        g.schools.forEach(s => {
            keys.set(schoolKey(s), s);
            ((g.aliases || {})[s] || []).forEach(a => keys.set(schoolKey(a), s));
        });
    }
    return keys;
}

const sleep = s => new Promise(r => setTimeout(r, s * 1000));
const normName = s => s.toLowerCase().replace(/[^a-z0-9]/g, '');

async function main() {
    const o = parseArgs(process.argv.slice(2));
    const pin = process.env.XC_PIN;
    if (!pin) throw new Error('Set XC_PIN to the team PIN (it is never stored in the repo).');
    if (!o.links.length) throw new Error('No links given.');
    const links = [...new Set(o.links)];
    const { GROUPS, schoolKey } = loadGroups();
    const keys = targetKeys(GROUPS, schoolKey, o.groups);

    const api = async (action, body) => {
        const res = await fetch(`${o.base}/api.php?action=${action}`, {
            method: body ? 'POST' : 'GET',
            headers: { 'X-XC-PIN': pin, ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = null;
        try { data = await res.json(); } catch (e) {}
        return { status: res.status, data: data || {} };
    };

    const saved = await api('xc_meets');
    if (saved.status === 401) throw new Error('Wrong PIN.');
    if (saved.status !== 200) throw new Error(`Could not list saved meets (HTTP ${saved.status}): ${saved.data.error || ''}`);
    const onDate = {};                            // date → [{ name, schools: Set of schoolKey }]
    (saved.data.meets || []).forEach(m => {
        (onDate[m.date] = onDate[m.date] || []).push({ name: m.name, schools: new Set(Object.keys(m.schools || {}).map(schoolKey)) });
    });

    console.log(`${o.commit ? 'IMPORTING' : 'DRY RUN (add --commit to save)'}: ${links.length} link(s), keeping ${o.groups.join(', ')} schools (${new Set(keys.values()).size}).\n`);
    const summary = { saved: 0, runners: 0, skipped: [], failed: [] };

    for (let i = 0; i < links.length; i++) {
        const url = links[i];
        if (i) await sleep(o.delay);
        const r = await api('xc_milesplit', { url });
        if (r.status !== 200) {
            const msg = r.data.error || `HTTP ${r.status}`;
            // 502 means the site could not get the meet from MileSplit: blocked,
            // rate-limited or down. Stop rather than keep asking.
            if (r.status === 502 || r.status === 429) {
                console.log(`✗ ${url}\n  ${msg}\n\nStopping: MileSplit refused or did not answer. Nothing is retried.`);
                summary.failed.push(url);
                summary.skipped.push(...links.slice(i + 1).map(u => u + ' (not reached)'));
                break;
            }
            console.log(`✗ ${url}\n  ${msg}\n`);
            (r.status === 403 ? summary.skipped : summary.failed).push(`${url} (${msg})`);
            continue;
        }
        const { meet, rows = [] } = r.data;
        const kept = [], seen = new Set();
        let noTime = 0, twice = 0;
        for (const row of rows) {
            if (!keys.has(schoolKey(row.school || ''))) continue;
            if (!row.time) { noTime++; continue; }
            // One runner listed in two races (a misfiled heat) would fail the whole save.
            const k = normName(row.name) + '|' + schoolKey(row.school);
            if (seen.has(k)) { twice++; continue; }
            seen.add(k);
            kept.push({ race: row.race, distance_m: row.distance_m, place: row.place, name: row.name, grade: row.grade, school: row.school, time: row.time });
        }
        const bySchool = {};
        kept.forEach(k => { const s = keys.get(schoolKey(k.school)); bySchool[s] = (bySchool[s] || 0) + 1; });
        console.log(`• ${meet.name} · ${meet.date}${meet.location ? ' · ' + meet.location : ''}\n  ${url}\n  ${rows.length} finishers, keeping ${kept.length}` +
            (Object.keys(bySchool).length ? ': ' + Object.entries(bySchool).sort().map(([s, n]) => `${s} ${n}`).join(', ') : '') +
            (noTime ? `; ${noTime} with an unusable time left out` : '') + (twice ? `; ${twice} listed twice left out` : ''));

        if (!kept.length) { console.log('  Nothing to save.\n'); summary.skipped.push(`${url} (no runners from these schools)`); continue; }
        // A team runs one meet a day, so a meet already saved on this date that
        // shares a school with this one is this meet, saved before (a scanned
        // sheet under its own name, or an earlier import). Add only the schools
        // it does not have yet, under its name, so no runner is doubled. One
        // that shares no school is a different meet. Shared schools with two
        // saved meets can't be told apart, so that waits for a person.
        let saveAs = meet.name, rowsToSave = kept;
        const mine = new Set(kept.map(k => schoolKey(k.school)));
        const overlap = (onDate[meet.date] || []).filter(m => [...m.schools].some(k => mine.has(k)));
        if (overlap.length > 1 && !o.force) {
            console.log(`  Shares schools with ${overlap.length} meets already saved on ${meet.date} (${overlap.map(m => m.name).join('; ')}). Skipped; check which it is.\n`);
            summary.skipped.push(`${url} (matches more than one saved meet on ${meet.date})`);
            continue;
        }
        if (overlap.length === 1 && !o.force) {
            saveAs = overlap[0].name;
            rowsToSave = kept.filter(k => !overlap[0].schools.has(schoolKey(k.school)));
            const have = [...new Set(kept.filter(k => overlap[0].schools.has(schoolKey(k.school))).map(k => keys.get(schoolKey(k.school))))];
            if (!rowsToSave.length) {
                console.log(`  Already saved as "${saveAs}" with ${have.join(', ')}. Nothing new.\n`);
                summary.skipped.push(`${url} (already saved as "${saveAs}")`);
                continue;
            }
            console.log(`  Same meet as "${saveAs}", which already has ${have.join(', ')}: adding the other ${rowsToSave.length}.`);
        }
        if (!o.commit) { console.log('  Would save.\n'); continue; }
        const s = await api('xc_save_meet', { name: saveAs, date: meet.date, location: meet.location || '', rows: rowsToSave });
        if (s.status !== 200) { console.log(`  Save failed: ${s.data.error || 'HTTP ' + s.status}\n`); summary.failed.push(`${url} (save: ${s.data.error || s.status})`); continue; }
        console.log(`  Saved ${s.data.saved} result(s)${s.data.converted ? `, ${s.data.converted} converted to 5K` : ''}.\n`);
        summary.saved++; summary.runners += s.data.saved;
        // Remembered for the rest of the run, so a later link for this same meet
        // (one race of it, say) adds to it rather than starting another.
        const day = (onDate[meet.date] = onDate[meet.date] || []);
        const entry = day.find(m => m.name === saveAs) || day[day.push({ name: saveAs, schools: new Set() }) - 1];
        rowsToSave.forEach(k => entry.schools.add(schoolKey(k.school)));
    }

    console.log(`\nDone. ${o.commit ? `Saved ${summary.saved} meet(s), ${summary.runners} result(s).` : 'Dry run: nothing saved.'}`);
    if (summary.skipped.length) console.log('Skipped:\n  ' + summary.skipped.join('\n  '));
    if (summary.failed.length) { console.log('Failed:\n  ' + summary.failed.join('\n  ')); process.exitCode = 1; }
}

main().catch(e => { console.error(e.message); process.exit(1); });
