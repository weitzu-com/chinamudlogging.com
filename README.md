# ChinaMudLogging.com

Static English B2B website rebuilt from the public content and media stored on the original SiteGround WordPress installation.

## Source boundary

- Public pages, product records, article records, taxonomies, and original media were extracted read-only.
- WordPress core, themes, plugins, backups, database credentials, users, and executable PHP were not migrated.
- Product details are legacy source records and must be reconfirmed during quotation.

## Local development

```bash
npm install
npm run build
npm run dev
```

## Routes preserved

- `/about-us/`, `/services/`, `/product/`, `/blog/`, `/contact-us/`
- `/product/<legacy-product-slug>/`
- `/<legacy-article-slug>/`

Legacy cart, checkout, account, shop, news, and duplicate content paths redirect to the appropriate RFQ or content route.
