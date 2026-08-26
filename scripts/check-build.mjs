// @ts-nocheck
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const dist = path.join(root, "dist");
const htmlFiles = [];
const errors = [];

function walk(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const target = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(target);
    if (entry.isFile() && entry.name.endsWith(".html")) htmlFiles.push(target);
  }
}

function localTargetExists(url) {
  const pathname = decodeURIComponent(url.split(/[?#]/)[0]);
  if (!pathname.startsWith("/")) return true;
  const relative = pathname.replace(/^\/+/, "");
  const candidates = pathname.endsWith("/")
    ? [path.join(dist, relative, "index.html")]
    : [path.join(dist, relative), path.join(dist, relative, "index.html")];
  return candidates.some((candidate) => fs.existsSync(candidate));
}

walk(dist);

for (const file of htmlFiles) {
  const html = fs.readFileSync(file, "utf8");
  const route = path.relative(dist, file);
  const h1Count = (html.match(/<h1\b/gi) ?? []).length;
  if (h1Count !== 1) errors.push(`${route}: expected 1 h1, found ${h1Count}`);
  if (!/<title>[^<]+<\/title>/i.test(html)) errors.push(`${route}: missing title`);
  if (!/<meta name="description" content="[^"]+"/i.test(html)) errors.push(`${route}: missing meta description`);
  if (!/<link rel="canonical" href="https:\/\/www\.chinamudlogging\.com\//i.test(html)) errors.push(`${route}: missing production canonical`);

  const references = [...html.matchAll(/(?:href|src)="([^"]+)"/gi)].map((match) => match[1]);
  for (const reference of references) {
    if (/^(?:https?:|mailto:|tel:|data:|#)/i.test(reference)) continue;
    if (reference.startsWith("/") && !localTargetExists(reference)) errors.push(`${route}: missing local target ${reference}`);
  }
}

if (!fs.existsSync(path.join(dist, "sitemap.xml"))) errors.push("missing sitemap.xml");
if (!fs.existsSync(path.join(dist, "robots.txt"))) errors.push("missing robots.txt");

if (errors.length) {
  console.error(errors.join("\n"));
  process.exit(1);
}

console.log(`Checked ${htmlFiles.length} HTML files: titles, descriptions, canonicals, H1s, and local targets passed.`);
