# PR #8：仅产品 600 的字段审计摘录

来源提交：`67053764c498b76e6974cc5c6d6d828c2b0b7fd9`。

[冻结审计原文](https://github.com/weitzu-com/chinamudlogging.com/blob/67053764c498b76e6974cc5c6d6d828c2b0b7fd9/wordpress/docs/supplemental-content-audit-20261104.md)。以下为已确认字段错误段落原文摘录，未包含其他产品或待确认措辞建议。

ID600 原目录商品介绍明确：
- 名称：超声波传感器支架
- 规格型号：超声波体积
- 说明：传感器配件

当前本站省略 Model / SKU，并将“超声波体积 传感器配件”合并为 Category note。建议仅修正此产品正文规格表、摘要与对应结构化字段：
- Source specification label: 超声波体积
- Category note: 传感器配件
- 保留名称、URL、WP ID及原文；不要把“超声波体积”擅自规范成工程型号。

摘录中摘要修改属于当时提案范围；本 PR 的实际候选仅修改正文规格行和对应 meta，摘要及所有待确认措辞保持原值。
