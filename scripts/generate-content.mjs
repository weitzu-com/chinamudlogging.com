// @ts-nocheck
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const sourceDir = path.join(root, ".source-private");
const outputDir = path.join(root, "src", "data");

function readJsonl(name) {
  const file = path.join(sourceDir, name);
  return fs
    .readFileSync(file, "utf8")
    .split(/\r?\n/)
    .filter(Boolean)
    .map((line) => JSON.parse(line));
}

function decodeEntities(value) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(Number.parseInt(hex, 16)))
    .replace(/&#([0-9]+);/g, (_, decimal) => String.fromCodePoint(Number.parseInt(decimal, 10)))
    .replace(/&nbsp;/gi, " ")
    .replace(/&amp;/gi, "&")
    .replace(/&quot;/gi, '"')
    .replace(/&#039;|&apos;/gi, "'")
    .replace(/&lt;/gi, "<")
    .replace(/&gt;/gi, ">");
}

function htmlToText(value = "") {
  return decodeEntities(
    value
      .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, "")
      .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, "")
      .replace(/<!--([\s\S]*?)-->/g, "")
      .replace(/<br\s*\/?>/gi, "\n")
      .replace(/<\/?(strong|b)\b[^>]*>/gi, "\n")
      .replace(/<\/(p|div|h[1-6]|li|ul|ol|table|tr|blockquote)>/gi, "\n\n")
      .replace(/<li\b[^>]*>/gi, "• ")
      .replace(/<[^>]+>/g, "")
      .replace(/\[(\/?)[a-z0-9_-]+(?:\s[^\]]*)?\]/gi, "\n")
  )
    .replace(/\r/g, "")
    .replace(/[ \t]+\n/g, "\n")
    .replace(/\n[ \t]+/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function toParagraphs(value) {
  return htmlToText(value)
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.replace(/\s+/g, " ").trim())
    .filter(Boolean);
}

const sectionLabels = new Map([
  ["PRODUCT DESCRIPTION", "Product description"],
  ["SPECIFICATIONS", "Specifications"],
  ["PRODUCT FEATURE", "Product features"],
  ["PRODUCT FEATURES", "Product features"],
  ["DELIVERY SHIPPING AND SERVICES", "Delivery and services"],
  ["DELIVERY, SHIPPING AND SERVICES", "Delivery and services"],
  ["FAQ", "FAQ"],
]);

function productSections(value) {
  const paragraphs = toParagraphs(value);
  const sections = [];
  let current = { heading: "Overview", paragraphs: [] };

  for (const paragraph of paragraphs) {
    const normalized = paragraph.replace(/[:.]+$/, "").trim().toUpperCase();
    const heading = sectionLabels.get(normalized);
    if (heading) {
      if (current.paragraphs.length) sections.push(current);
      if (heading === "FAQ") return sections;
      current = { heading, paragraphs: [] };
      continue;
    }
    current.paragraphs.push(paragraph);
  }
  if (current.paragraphs.length) sections.push(current);
  return sections;
}

function truncate(value, length = 210) {
  const clean = value.replace(/\s+/g, " ").trim();
  if (clean.length <= length) return clean;
  return `${clean.slice(0, length).replace(/\s+\S*$/, "")}…`;
}

function cleanLegacyWeight(value) {
  const weight = String(value || "").trim();
  // 33333 is a known placeholder in the legacy WooCommerce export, not a
  // defensible shipping weight. Omit it until the sales team confirms it.
  return weight === "33333" ? "" : weight;
}

function curatedCategory(title, sourceCategories) {
  const haystack = `${title} ${sourceCategories.join(" ")}`.toLowerCase();
  if (/wireless/.test(haystack)) return { slug: "wireless-sensors", name: "Wireless Sensors" };
  if (/mud logging unit|surface logging system|logging systems|sls\b|pls\b/.test(haystack)) {
    return { slug: "mud-logging-units", name: "Mud Logging Units" };
  }
  if (/fluoroscope|uv lamp|shale density|chromatograph|co2|degasser|formation/.test(haystack)) {
    return { slug: "formation-evaluation", name: "Formation Evaluation" };
  }
  if (/sensor|encoder|transmission box|master station|battery|charger|pressure|torque|hook.?load|density|temperature|flow|pit|stroke|conductivity/.test(haystack)) {
    return { slug: "mud-logging-sensors", name: "Mud Logging Sensors" };
  }
  return { slug: "drilling-monitoring", name: "Drilling Monitoring & Accessories" };
}

const posts = readJsonl("posts.jsonl");
const attachments = readJsonl("attachments.jsonl");
const postmeta = readJsonl("postmeta.jsonl");
const terms = readJsonl("terms.jsonl");
const relationships = readJsonl("relationships.jsonl");

const attachmentById = new Map(attachments.map((attachment) => [Number(attachment.ID), attachment]));
const termById = new Map(terms.map((term) => [Number(term.term_id), term]));
const metaByPost = new Map();

for (const item of postmeta) {
  const postId = Number(item.post_id);
  const values = metaByPost.get(postId) ?? {};
  values[item.meta_key] = item.meta_value;
  metaByPost.set(postId, values);
}

function imageForPost(postId) {
  const meta = metaByPost.get(Number(postId)) ?? {};
  const attachment = attachmentById.get(Number(meta._thumbnail_id));
  return attachment?.attached_file ? `/images/source/${attachment.attached_file}` : "/images/source/woocommerce-placeholder.png";
}

function sourceCategoriesForPost(postId, taxonomy) {
  return relationships
    .filter((item) => Number(item.object_id) === Number(postId) && item.taxonomy === taxonomy)
    .map((item) => termById.get(Number(item.term_id))?.name)
    .filter(Boolean);
}

const products = posts
  .filter((post) => post.post_type === "product")
  .map((post) => {
    const sourceCategories = sourceCategoriesForPost(post.ID, "product_cat");
    const sections = productSections(post.post_content);
    const overview = sections.flatMap((section) => section.paragraphs)[0] ?? "";
    const meta = metaByPost.get(Number(post.ID)) ?? {};
    return {
      id: Number(post.ID),
      title: post.post_title.trim(),
      slug: post.post_name,
      summary: truncate(htmlToText(post.post_excerpt) || overview || post.post_title),
      image: imageForPost(post.ID),
      category: curatedCategory(post.post_title, sourceCategories),
      sourceCategories,
      sku: meta._sku || "",
      weight: cleanLegacyWeight(meta._weight),
      sections,
      legacyUrl: `/product/${post.post_name}/`,
    };
  })
  .sort((a, b) => a.title.localeCompare(b.title));

const articles = posts
  .filter((post) => post.post_type === "post")
  .map((post) => {
    const paragraphs = toParagraphs(post.post_content);
    return {
      id: Number(post.ID),
      title: post.post_title.trim(),
      slug: post.post_name,
      publishedAt: String(post.post_date),
      summary: truncate(htmlToText(post.post_excerpt) || paragraphs[0] || post.post_title),
      image: imageForPost(post.ID),
      paragraphs,
      legacyUrl: `/${post.post_name}/`,
    };
  })
  .sort((a, b) => b.publishedAt.localeCompare(a.publishedAt));

const categories = [...new Map(products.map((product) => [product.category.slug, product.category])).values()]
  .map((category) => ({
    ...category,
    count: products.filter((product) => product.category.slug === category.slug).length,
  }))
  .sort((a, b) => a.name.localeCompare(b.name));

fs.mkdirSync(outputDir, { recursive: true });
fs.writeFileSync(path.join(outputDir, "products.json"), `${JSON.stringify(products, null, 2)}\n`);
fs.writeFileSync(path.join(outputDir, "articles.json"), `${JSON.stringify(articles, null, 2)}\n`);
fs.writeFileSync(path.join(outputDir, "categories.json"), `${JSON.stringify(categories, null, 2)}\n`);

console.log(`Generated ${products.length} products, ${articles.length} articles, and ${categories.length} categories.`);
