# 原始资料复核与受控修订

2026-11-05：范围固定为 4 篇既有英文文章与 32 个英文产品。保留 URL、发布日期、图片、分类与语言关联；不重新发布未审核草稿。

32 个 EPEC 详情已通过 Ego Lite 逐页读取名称、规格型号、说明，与原有字段比对后没有实质来源变化。`review-inputs.json` 仅含最小产品事实、来源、读取时间与摘要。完整 CMS 快照、原文、备份及业务证据在 0700 私有目录。`reviewed-manifest.json` 包含逐字段前置 SHA256、英文修订和来源记录。

统一 drawworks、proximity switch、pump-stroke、conductivity 等术语及单位间距，不改变数值、精度或比较符。100p、100LEL、400r、J、防爆字符串保留未确认状态，不补 ±、%LEL、rpm、认证或兼容性。>70 °C 保留大于号。6 条保留来源型号文本；另外 26 条把复合描述从 SKU 移至明确标注的 catalogue designation，原值完整备份。4 篇文章依据原站档案、确认的业务定位与文内 SLB 来源重写，撤去缺证的就业比较、连续钻井保证和公司能力主张。

## 验证

```sh
python3 wordpress/corrections/source-content-20261105/build-review.py
python3 wordpress/corrections/source-content-20261105/check-review.py
python3 wordpress/tests/run-source-content-review.py
```

596 项内容检查；隔离 WordPress 覆盖范围拒绝、只读检查、36 条发布、读回、幂等、并行编辑保护、真实 tar.gz 备份及回滚。保留首次失败：WordPress 去除 bdi、标题/摘要编码大于号、测试夹具与回滚脚本变量重名。修正后完整重跑。

## 发布与回滚

仅通过 WP-CLI 执行 apply-review.php。显式提供 CML_REVIEW_MODE=check|apply|verify|rollback、CML_REVIEW_MANIFEST 和文档根目录外的 CML_REVIEW_BACKUP。先 check、全库 SQL/tar.gz 备份及验证，再 apply。程序另建逐项 JSON/tar.gz 备份，修改时间、逐字段哈希和编辑锁保护并行编辑。发布后 verify，再次 apply 应 changed=0；运行 wp sg purge，检查实际 HTTP、canonical、sitemap、正文和手机排版。

回滚要求本批次与当前字段哈希一致；后续人工编辑会阻止回滚。只恢复这 36 项变更字段。整库备份仅供灾难恢复，正常内容回滚不导入整库影响新询盘。备份哈希与隔离恢复演练证据独立保留。

生产 Yoast 派生索引可能仍有旧摘要。内容 verify 后可运行 refresh-yoast.php，同一环境变量下备份并刷新这 36 条已有 indexable，不重建全站或更新 post dates。随后再次清除 SiteGround 缓存。

cml-reviewed-article.php 作为独立 MU 文件部署，仅修复本批录井单元任务文章被既有 Content Depth 折叠和旧摘要覆盖的问题。回滚兼容修复时先核对文件 SHA256 仍为本次版本，再移至站点根目录外并清缓存，不动其他 MU。内容 rollback 后如要恢复 SEO 展示，按已恢复字段重新生成对应 indexable，避免直接导入整库派生表。

## 工作流与结果边界

未找到所述 /article 原始 11 阶段命令，用户指示继续全部内容。本次逐项记录需求、来源、事实/未知、术语、结构、草稿、审阅、数字检查、隔离/浏览器、发布/缓存、收录/询盘 11 阶段，不声称执行缺失的命令。

技术验收不等于 Google 重新抓取或有效商机。保存 URL Inspection 快照并比较 lastCrawl/lastmod。72 小时及第 7 天沿用固定 T0，不提前完成。测试询盘与待人工审核提交分开，不向客户发信。
