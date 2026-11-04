# ChinaMudLogging.com

Static English B2B website rebuilt from the public content and media stored on the original SiteGround WordPress installation.

## Source boundary

- Public pages, product records, article records, taxonomies, and original media were extracted read-only.
- WordPress core, themes, plugins, backups, database credentials, users, and executable PHP were not migrated.
- Product details are legacy source records and must be reconfirmed during quotation.

## Local development

```bash
npm ci
npm run dev
```

Uses Node.js 24.x (`nvm use`), matching CI and Vercel. Before pushing, run the same build, generated-page, link, metadata, and production-dependency checks used by CI:

```bash
npm run verify
```

## Routes preserved

- `/about-us/`, `/services/`, `/product/`, `/blog/`, `/contact-us/`
- `/product/<legacy-product-slug>/`
- `/<legacy-article-slug>/`

Legacy cart, checkout, account, shop, news, and duplicate content paths redirect to the appropriate RFQ or content route.

## Release controls

- Develop on a branch and open a pull request into `main`.
- The `verify` check must pass against the current base before merging.
- Main requires pull requests, resolved conversations, and linear history; force pushes and deletion are blocked, including for administrators.
- Vercel production builds run `npm run verify`, so generated-page and dependency checks also gate the deployment itself.
- After release, confirm the production deployment commit matches `main`, then check the homepage, sitemap, product page, and contact page.
- The inquiry form prepares a `mailto:` draft only. It does not confirm submission or delivery. A test email is accepted only after its unique ID is found in the recipient mailbox; verify spam placement as well.
- A mailto click is an intent signal, not a delivered lead or a sale. Keep email receipt and qualified inquiry counts separate from website clicks.

## Inquiry measurement

See [询盘统计链路](docs/询盘统计链路.md) for definitions, verified receipt import, human qualification, private reporting, deployment and test isolation. Browser events record intent only; receipt and qualification use separate authenticated operations.
