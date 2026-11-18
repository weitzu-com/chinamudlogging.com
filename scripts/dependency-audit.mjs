#!/usr/bin/env node
// @ts-nocheck
// CI helper: this repo does not ship @types/node, and `astro check` type-checks
// .mjs files here, so the node: imports would otherwise fail the build gate.
/**
 * Normalise `npm audit` output into a stable fingerprint.
 *
 * The weekly audit job must only speak when the advisory picture actually
 * changed. A job that reports the same known findings every Monday trains
 * people to ignore it, which is the same failure mode as a merge gate that is
 * always red.
 *
 * A "key" identifies one finding as `package@range` plus the advisory ids that
 * produced it. Keys are sorted, so the fingerprint is order-independent and
 * changes when a finding is added *or* removed.
 *
 * Exits 0 always: the decision to raise an alarm is the workflow's job, not
 * this script's.
 */
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { writeFileSync } from "node:fs";

function runAudit() {
  try {
    const out = execFileSync("npm", ["audit", "--audit-level=high", "--json"], {
      encoding: "utf8",
      stdio: ["ignore", "pipe", "pipe"],
      maxBuffer: 64 * 1024 * 1024,
    });
    return { exitCode: 0, raw: out };
  } catch (err) {
    // npm exits non-zero when it finds advisories; that is expected here.
    return { exitCode: err.status ?? 1, raw: (err.stdout ?? "").toString() };
  }
}

function advisoryIds(via) {
  const ids = [];
  for (const entry of Array.isArray(via) ? via : []) {
    if (entry && typeof entry === "object" && typeof entry.url === "string") {
      const m = entry.url.match(/(GHSA-[a-z0-9-]+|CVE-\d{4}-\d+)/i);
      if (m) ids.push(m[1].toUpperCase());
    }
  }
  return [...new Set(ids)].sort();
}

const { exitCode, raw } = runAudit();

let parsed = null;
try {
  parsed = JSON.parse(raw);
} catch {
  // Not JSON: audit itself failed (network, registry without the audit API).
  // Treat as a distinct state so it cannot masquerade as "all clear".
  const keys = ["__audit_unavailable__"];
  writeFileSync(
    "audit-report.txt",
    `npm audit did not return JSON (exit ${exitCode}).\n\n${raw.slice(0, 4000)}\n`,
  );
  console.log(
    JSON.stringify({
      fingerprint: createHash("sha256").update(keys.join("\n")).digest("hex"),
      keys,
      count: 0,
      exitCode,
      auditAvailable: false,
    }),
  );
  process.exit(0);
}

const vulnerabilities = parsed.vulnerabilities ?? {};
const keys = [];

for (const [name, info] of Object.entries(vulnerabilities)) {
  const ids = advisoryIds(info?.via);
  keys.push(ids.length ? `${name}@${info.range}#${ids.join("+")}` : `${name}@${info?.range ?? "*"}`);
}
keys.sort();

const fingerprint = createHash("sha256").update(keys.join("\n")).digest("hex");
const counts = parsed.metadata?.vulnerabilities ?? {};

const report = [
  `npm audit --audit-level=high exited ${exitCode}`,
  `findings: high ${counts.high ?? 0}, critical ${counts.critical ?? 0}, total ${counts.total ?? 0}`,
  "",
  "stable keys:",
  ...keys.map((k) => `  - ${k}`),
  "",
].join("\n");

writeFileSync("audit-report.txt", report + "\n" + raw.slice(0, 20000));
console.log(JSON.stringify({ fingerprint, keys, count: keys.length, exitCode, auditAvailable: true }));
