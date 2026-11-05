#!/usr/bin/env python3
"""Plan fixed cutover milestones; append truthful, evidence-hashed executions privately."""
import argparse
import datetime as dt
import hashlib
import json
import pathlib

UTC = dt.timezone.utc
REQUIRED = {'google', 'search', 'inquiries', 'technical'}

def instant(value):
    result = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
    if result.tzinfo is None:
        raise ValueError('Explicit timezone required')
    return result.astimezone(UTC)

def plan(release, now):
    start = instant(release['cutoverAt'])
    return [{'stage': stage, 'dueAt': (start + delta).isoformat(),
             'dueLocal': (start + delta).astimezone(dt.timezone(dt.timedelta(hours=8))).isoformat(),
             'status': 'due' if now >= start + delta else 'not_due'}
            for stage, delta in [('72h', dt.timedelta(hours=72)), ('7d', dt.timedelta(days=7))]]

def record(release, stage, evidence, now, directory):
    milestone = next(x for x in plan(release, now) if x['stage'] == stage)
    if milestone['status'] != 'due':
        raise ValueError('Milestone is not due; cannot accept early')
    if set(evidence) != REQUIRED:
        raise ValueError('Require google, search, inquiries and technical evidence files')
    hashes = {}
    for kind, path in evidence.items():
        raw = pathlib.Path(path).read_bytes()
        if not raw.strip():
            raise ValueError('Empty evidence')
        hashes[kind] = {'path': str(pathlib.Path(path).resolve()), 'sha256': hashlib.sha256(raw).hexdigest()}
    result = {**milestone, 'status': 'executed_pending_review', 'executedAt': now.isoformat(),
              'secondsAfterDue': (now - instant(milestone['dueAt'])).total_seconds(),
              'evidence': hashes, 'acceptance': 'Record execution only; assess results in signed-off report. Indexing or qualified leads are not inferred.'}
    directory = pathlib.Path(directory)
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    filename = directory / (stage + '-' + now.strftime('%Y%m%dT%H%M%S%fZ') + '.json')
    with filename.open('x') as f:
        f.write(json.dumps(result, indent=2) + '\n')
    filename.chmod(0o600)
    return result

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['plan', 'record'])
    parser.add_argument('--release', default=str(pathlib.Path(__file__).resolve().parents[1] / 'migration/release.json'))
    parser.add_argument('--at', help='Planning simulation only; never allowed for recording')
    parser.add_argument('--stage', choices=['72h', '7d'])
    parser.add_argument('--evidence', action='append', default=[], help='kind=/private/file')
    parser.add_argument('--out', help='Private append-only execution directory')
    args = parser.parse_args()
    release = json.loads(pathlib.Path(args.release).read_text())
    now = dt.datetime.now(UTC)
    if args.command == 'plan':
        print(json.dumps(plan(release, instant(args.at) if args.at else now), indent=2))
    else:
        if args.at or not args.stage or not args.out:
            parser.error('record requires --stage and --out; simulation --at is forbidden')
        print(json.dumps(record(release, args.stage, dict(x.split('=', 1) for x in args.evidence), now, args.out), indent=2))
