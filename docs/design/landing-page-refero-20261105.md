# ChinaMudLogging landing page — Refero visual exploration

Date: 2026-11-05. Status: user selected A and explicitly authorized launch. Implement A in the WordPress theme; preserve the Astro rollback source.

## Brief

Design an English web landing page for oilfield engineers and procurement teams sourcing mud logging units, sensors, formation evaluation and drilling monitoring equipment. Primary goal: find an equipment family and submit an RFQ with measurement, operating environment and interface requirements. Product scope comes from the repository homepage and README. Legacy specifications require confirmation during quotation. Do not invent certifications, customers, performance statistics or delivery promises.

## Research

Three Refero style searches: industrial engineering photography; precise white/blue hardware; dark industrial technology. Full style references reviewed:

- Andercore: https://www.andercore.com — style 15fd028d-c493-47a9-8e69-0a59c6fdb14b. Near-black industrial photography, compact Archivo, crimson primary actions, flat sharp surfaces.
- DJI: https://dji.com — style c6fe2881-d664-4756-849e-5c254f712338. White/grayscale product presentation, Open Sans, blue interactions and rounded buttons.
- Timescale: https://www.timescale.com — style 520e6739-69c0-4c16-a3ce-1c891c8c77c6. White engineering composition, Geist/mono, black structure, orange heading emphasis and yellow actions.
- Square hardware screen: https://refero.design/pages/a627ef93-4347-4c43-a1a4-bc80b9edaa3c — full metadata and thumbnail inspected. Borrow category navigation and product comparison hierarchy, replacing purchase actions with product details and RFQ.

## Direction locks and decision ledger

| Direction | Primary / preserve | Bounded borrowing | Tokens and media | Reject |
|---|---|---|---|---|
| A — Field / Industrial | Andercore: near-black canvas, white compact type, desaturated contextual photography, flat sharp sections | Square: equipment-family navigation; DJI: clear isolated product images only | #0b0405 canvas; #fff text; #e32735 primary CTA only; 0px panels / 4px buttons; wide oilfield photograph with dark overlay | Extra saturated accents, glass cards, decorative gradients, invented proof |
| B — Instrument / Precision | DJI: white/gray canvas, lightweight sans, generous space, hardware-first composition, blue interactions | Square: category rail and uniform equipment cards; Andercore: short technical labels without dark palette | #fff canvas; #f7f9fa section; #ededed product surface; #0070d5 interactions only; pill actions, 4px cards; isolated instrument photography | Generic software dashboard, blue decoration, fake specifications |
| C — Engineering / Blueprint | Timescale: #fafafa canvas, black rules, assertive Geist headings, mono labels, diagram-led split composition | Square: family selection; Andercore: concise measurement-first copy only | #ff5b29 headline emphasis; #f5ff80 filled CTA; black schematic strokes; 4px controls; restrained hard offsets; clearly conceptual signal-chain drawing | Soft gradients, decorative 3D, fabricated telemetry, certification marks |

Generated images are visual concept mockups, including illustrative equipment imagery. They are not product photographs or engineering evidence. Production implementation should use existing verified catalog assets and confirmed specifications.

## Shared page content and hierarchy

Navigation → concise mud logging equipment value proposition → product-family selector → featured equipment with details links → measurement/environment/interface RFQ checklist → request-quote action and footer. A emphasizes field context, B emphasizes selecting hardware, C emphasizes the path from measurement to equipment.

Headline: “Mud logging equipment. Selected for your wellsite.”
Support: “Explore units, sensors and formation evaluation tools. Share your measurement and interface requirements for a configuration review.”
Actions: “Explore equipment” and “Request a quote”.
Families: Mud logging units; Mud logging sensors; Wireless sensors; Formation evaluation; Drilling monitoring.

## Next gate

## Concept artifacts and review

- `concepts/a-field-industrial.png`: dark field-context direction.
- `concepts/b-instrument-precision.png`: white hardware-selection direction, recommended for this audience.
- `concepts/c-engineering-blueprint.png`: diagram-led engineering direction.

All three generated images were visually inspected against their direction locks. Main canvas, accent roles, product-family hierarchy and RFQ actions remain distinct. These are static concept images, not browser implementations; responsive behavior and keyboard interactions are not yet validated. Generated typography and equipment geometry must not be treated as production assets. Product detail links should be explicit in implementation even where the concept images show only labeled tiles. For B, use lighter display typography in implementation to align more closely with the DJI reference; for C, use the exact #f5ff80 action token rather than the brighter generated approximation.

The user chose A with “A 上线。” A is the build and QA target. Concept images remain in the original project design directory. The production candidate uses actual product attachments and a separately generated atmospheric rig background; see the WordPress release acceptance record.
