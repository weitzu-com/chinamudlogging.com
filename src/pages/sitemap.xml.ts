import type { APIRoute } from "astro";
import products from "../data/products.json";
import categories from "../data/categories.json";
import articles from "../data/articles.json";

const staticPaths = ["/", "/about-us/", "/services/", "/product/", "/blog/", "/contact-us/"];

export const GET: APIRoute = ({ site }) => {
  const origin = site ?? new URL("https://www.chinamudlogging.com");
  const paths = [
    ...staticPaths,
    ...products.map((product) => `/product/${product.slug}/`),
    ...categories.map((category) => `/product-category/${category.slug}/`),
    ...articles.map((article) => `/${article.slug}/`),
  ];
  const body = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${paths
    .map((pathname) => `  <url><loc>${new URL(pathname, origin).toString()}</loc></url>`)
    .join("\n")}\n</urlset>\n`;
  return new Response(body, { headers: { "Content-Type": "application/xml; charset=utf-8" } });
};
