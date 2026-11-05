# 产品 600：精确修正审阅包

状态：2026-11-05 已取得当前 WordPress 原始正文和已注册 REST 元数据，生成本地精确差异；未保存、更新、发布或部署产品修正。

## 证据与范围

既有 `cml-siteground` SSH 在 banner 握手阶段超时，未执行远端命令。改用已有授权的 Chrome Tzu 登录会话，读取产品 600 后台页面 DOM 内嵌的 `/wp/v2/cml_product/600?context=edit` 预载 JSON，并用后台规格输入框和英语选项交叉核对。没有点击 Update/Save，没有填写或提交任何字段；随后关闭该页。WordPress 编辑页自身可能维护临时编辑锁，本次并未取得全库快照，不声称全库零变化。

`evidence/before-review.json` 保留原始正文、摘要和全部已注册 REST meta，以及身份、时间、状态、媒体和分类。它是选定字段的原值转录，不是完整 HTTP 响应或全量数据库导出。未导出 nonce、凭据、未注册私有 meta、修订正文或审批记录。私有目录 `.source-private/corrections/product-600/` 另存原值副本。详细采集范围见 `evidence/acquisition-20261105.json`。

原值确认：

- ID 600，英语，slug `ultrasonic-sensor-bracket-mud-logging`，publish；`modified_gmt=2026-11-04T08:29:40`。
- 正文合并行是 `Category note` → ` 超声波体积 传感器配件`；meta 对应原值为 `超声波体积 传感器配件.`，末尾句点保留在 before 中。
- `_cml_sku` 确认为空；`cml_family=[69]` 对应 Mud Logging Sensors，保持不变。
- Name 和 Source URL 的 meta 原值亦含句点，本次原样保留，未做顺手清理。

EPEC 来源：https://emall.epec.com/productd/141652/15487160 。字段含义依据 PR #8 审计：名称「超声波传感器支架」、规格型号「超声波体积」、说明「传感器配件」。产品 600 审计摘录冻结于 `evidence/pr8-product-600-excerpt.md`，PR head 为 `67053764c498b76e6974cc5c6d6d828c2b0b7fd9`。先前请求未取得 EPEC 商品字段；本次于 2026-11-05 08:40（北京时间）通过浏览器直接读取该商品的商品介绍，三个字段均与冻结审计一致。新增来源 DOM 快照及核对报告见下方。目录记录不构成厂家数据表或认证证明。

## 本地精确修正

| 字段 | 修正值 |
|---|---|
| Source specification label | 超声波体积 |
| Category note | 传感器配件 |

仅拆分正文的一条规格行和 `_cml_specifications` 中对应的一条记录，数字表达式及单位均为空。保留名称、来源 URL、其他规格行及其顺序。Category note 是规格说明标签，不是 WordPress 分类法调整；不把「超声波体积」当作工程 SKU。

- `exact.diff`：选定原值与 after 的完整统一差异。
- `table-row.diff`：便于审阅的正文表格行差异。
- `after-review.json`：离线修正后记录。
- `proposed-fields.json`：仅含 content 与规格 meta 的候选字段对象；没有目标端点、没有写入程序，未发送。
- `build-review.py`：离线重复生成器，无网络或 WordPress 调用。

摘要仍包含原始合并来源说明，按既定最小范围原样保留；Overview、Applications、安全用途、认证措辞及其他产品、语言不变。原始记录保留全部原值，不以 after 替换 before。

## 验证结果

`evidence/offline-validation.json` 记录身份、唯一原始行匹配（含句点差异）、仅两处字段变化、正文反向还原、摘要与其他 meta 不变等检查均 PASS。`evidence/php-lint.txt` 记录 PHP 8.2.35 `TOKEN_PARSE` 通过；使用本机既有缓存的 `@php-wasm` 运行时，导出脚本只解析、未执行，不访问生产。未证明导出脚本在当前 WordPress 上的运行结果。

复现离线差异：

```sh
python3 wordpress/corrections/product-600/build-review.py
```

`SHA256SUMS` 覆盖证据、原值、after、候选字段和差异。任何后续变更均应重新生成清单。

## 只读核对与后续门槛

1. 后续操作前重新通过授权只读方式取得产品 600 的 `content.raw`、`excerpt.raw`、全部注册 meta、语言、slug、分类及 `modified_gmt`；同时比较正文/规格的 SHA256 与原值，不能只凭时间或只比摘要。发现漂移、已修正、重复行或字段不符则停止覆盖。
2. 若 SSH 恢复，可使用 `export-readonly.php` 导出全量 meta 和修订供进一步审阅。输出可能含作者与私有信息，须存私有目录。当前脚本仅完成语法检查，未在生产执行。
3. 保留私有原始导出、before/after、diff、时间、来源、审核人和 SHA256；meta 不一定保存在 WordPress 修订中，不能依赖修订代替备份。作者不等于审批人。
4. 在隔离副本验证表格两行、结构化规格一致及原 URL/canonical/询盘参数，未提交询盘。本包没有生产写入步骤；任何后续发布另行授权并再次检查并发变更。

## EPEC 直接核验补充 — 2026-11-05

来源商品编号 `40246960-2101310115-157722016`，URL 中供应商目录 ID `141652`、产品 ID `15487160`。页面标题与商品介绍共同支持产品身份。无需登录，未进行购买、询价、留言或 WordPress 操作。

| EPEC 原始字段 | 直接读到的原值 | 产品 600 修正对照 |
|---|---|---|
| 名称 | 超声波传感器支架 | 原名称保留 |
| 规格型号 | 超声波体积 | Source specification label，逐字一致 |
| 说明 | 传感器配件 | Category note，逐字一致 |

- `evidence/epec-15487160-dom-20261105.json`：商品介绍区域的 DOM HTML、原始文本、页面标题、商品编号、URL。属于选定区域 DOM 快照，不是完整 HTTP 响应或整页截图；图片仅保留原始 URL，未下载。
- `evidence/epec-15487160-verification-20261105.json`：采集 UTC 时间、快照 SHA256、字段对照结果和原有修正文件哈希。
- `evidence/SHA256SUMS-before-epec-20261105.txt`：本次来源核验前的哈希清单，保留历史证据。

核对通过：DOM 文本与所存 HTML 一致，三个来源字段与 PR #8 记录一致，候选正文和 meta 拆分与原始字段一致。原始 `before-review.json`、`after-review.json`、两份 diff、候选字段文件和 PHP 脚本共六个文件的字节与核验前哈希完全一致。此次只补证据和状态说明，不重新生成或调整产品修正。

此前 `acquisition-20261105.json` 中“未重新验证 EPEC”的说明记录的是较早采集时的证据状态，予以保留；本次新增报告补足该缺口。未把目录规格字符串认定为工程 SKU，未调整 WordPress 分类法或扩展任何用途、兼容性及认证结论。

## 独立草稿 PR 范围

本目录以 `codex/wordpress-migration` 的 `e12e33ddf658d64aca3a08a36d5b280dde97171c` 为基线独立提交。仅纳入 `wordpress/corrections/product-600/` 文件。完整 PR #8 审计包含其他产品，故仅收录产品 600 字段摘录；来源链接、完整原文哈希及历史清单说明见 `evidence/pr8-excerpt-provenance.json`。所有原值、精确差异和 EPEC DOM 快照保持原始字节不变。没有生产导入、写入或部署入口，草稿 PR 不代表上线或批准修改。
