# ChinaMudLogging.com

The production domain now serves the independent SiteGround WordPress installation. The English Astro source and Vercel hosting remain available for rollback. WordPress code, source reconciliation and acceptance evidence live in `wordpress/`.

## WordPress migration

2026-11-05：既定十个重点产品的六语全文已发布并完成六十页复核，详见 [六语全文翻译验收](wordpress/docs/六语全文翻译验收_20261105.md)。

- Native block theme: `wordpress/theme/cml`.
- Products, private inquiries and repeatable import: `wordpress/plugin/cml-core`.
- Seven language page sets, 60 published priority product selection pages and separate pending full translation drafts: `wordpress/migration`.
- Deployment, mailbox validation and rollback: `wordpress/docs/部署与回滚手册.md`.
- Current acceptance evidence and remaining content-review limits: `wordpress/docs/验收报告.md`.

Build a portable candidate with `python3 wordpress/scripts/prepare.py`, validate it with `python3 wordpress/scripts/check-bundle.py`, then package it with `python3 wordpress/scripts/package.py`. Output lives under `~/.cache/chinamudlogging-wordpress/` and includes media and source archives. Credentials, generated packages and backups must stay outside Git. Google web translation is used only for review drafts; it is not a production dependency and has no availability guarantee.

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
- The retained Astro form prepares a `mailto:` draft. The production WordPress form saves the inquiry server-side before notification; transport acceptance is distinct from mailbox delivery. Test delivery requires matching receiving-side evidence.
- A mailto click is an intent signal, not a delivered lead or a sale. Keep email receipt and qualified inquiry counts separate from website clicks.

## Inquiry measurement

See [询盘统计链路](docs/询盘统计链路.md) for definitions, verified receipt import, human qualification, private reporting, deployment and test isolation. Browser events record intent only; receipt and qualification use separate authenticated operations.
