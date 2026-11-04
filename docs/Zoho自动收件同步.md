# Zoho 组织级实际收件同步

## 规格与权限

复用现有 Worker/D1、CML 关联编号、收件去重、人工资格审核和超管 SMTP 投递日志。面向经营者及销售，每 5 分钟读取 `sales@chinamudlogging.com` 的入站投递记录。不依赖浏览器常开或销售个人登录。

使用现有 Self Client，OAuth 范围只有 `ZohoMail.partner.organization.READ`。此权限属于组织只读，代码仅向固定 Zoho US 官方域名请求令牌、组织信息及按目标收件地址过滤的 SMTP 日志，不读取邮件正文、不发信、不修改邮箱设置、不自动审核有效询盘。

官方接口：[SMTP logs](https://www.zoho.com/mail/help/api/get-smtp-logs.html)、[组织信息](https://www.zoho.com/mail/help/api/get-org-details.html)。启用必须同时满足：真实 OAuth 调用成功、`zoho-status` configured=true、有近期 last_success、真实测试邮件已自动入库。只部署代码不算启用。

## 收件判定

必须同时满足：`mailType=in`、目标收件地址匹配、`deliveryType=MAILBOX DELIVERY`、`deliveryStatus=delivered` 或 `Mail Delivered`（大小写不敏感）、有效 Message-ID、可信投递时间、主题只有一个 CML 编号且对应已存在的邮件意向。草稿、点击、发件、SMTP 接纳、排队/审核中、未知状态、歧义编号均不创建收件。

收件来源为 `zoho_smtp_api`，沿用 receipts 表的 `zoho_delivery_log` 证据类别；真实来源与证据细节位于独立加密证据表。一个意向只计一次实际收件；Message-ID 全局去重；同一意向后续真实邮件可留取额外证据但不放大转化数。测试标记继承原会话，不能由通知更改。

## 证据与可靠性

保留关联编号、原始 Message-ID、Zoho transaction ID、目标收件地址、状态、投递类型、时间、垃圾邮件标记、来源及抓取时间。Message-ID 和最小投递证据使用独立 AES-GCM 密钥加密；另存 SHA-256 用于核对。不保留发件人地址、完整主题、IP 或正文。证据与会话同样受 90 天清理约束。

每次查询回溯 24 小时，处理全部分页成功后才移动游标；失败保留游标，下一次重试。租约抑制并发。唯一约束防止手工登记与自动同步竞态造成重复。OAuth/日志错误仅记录安全错误码，不写令牌及供应商原始报错正文。

最后成功超过 15 分钟时 status.stale=true，便于检查失效授权或停滞；没有成功时也为 stale。不把失败写成零收件的经营结论。超过 10 页的一轮不会推进游标，需调查积压。

## 操作

凭据保存在本机 `~/.config/chinamudlogging-funnel/zoho.json`（0600），Worker 使用 secrets。首次配置通过一次性 loopback 页面交换授权码，凭据不进入命令参数、日志、Git 或网页回执。不要重置现有 Self Client 的 client secret，以免影响其他集成。

```sh
node scripts/zoho-oauth-setup.mjs
node scripts/funnel-admin.mjs zoho-status
node scripts/funnel-admin.mjs zoho-sync
node scripts/funnel-admin.mjs zoho-evidence --inquiry UUID
```

`zoho-evidence` 需要管理员密钥，只在本机操作时查看解密后的投递证据。报表不公开这些数据。自动接收后默认为待审核，销售仍使用原来的 qualify 命令人工判断。手工 receipt 导入作为取证补录入口保留，与自动同步共用唯一约束。

## 部署与验收

先应用 `workers/funnel/zoho-sync.sql` 的新增表，再配置 OAuth、组织 ID、证据加密密钥，部署 Worker。确认五分钟 Cron 存在；不能只用手动触发证明定时任务成功。

验收至少包含：准备邮件后 received=0；真实发送后 Zoho 日志 Delivered；Cron 自动生成 receipt 和加密证据；qualified 不增加；重复同步不增加 receipt；错误授权不推进游标；正式报表不混入测试数据。测试 fixtures 仅用于程序边界验证，真实收件证据必须来自实际 Zoho API。

## 2026-11-04 实测状态

只读授权已核验组织域名；生产 SMTP API 返回 200。Worker 版本 `972fe067-0e61-4fb1-9bc1-3718ce051cb5` 已部署每 5 分钟 Cron。初次真实同步读取 3 条目标地址日志、关联 0 条、插入 0 条，原因是 Zoho 当前不保留主题；不得将这些日志直接归属网站意向。

Zoho 报表 Configuration → General 的 **Save and display subject in email logs** 当前关闭。这是组织级设置，启用会保留组织邮件主题，且仅对启用后的邮件生效。需额外确认其范围；它不属于 OAuth 只读权限。参考[官方配置说明](https://www.zoho.com/mail/help/adminconsole/organization-email-reports.html)。关联字段启用并完成新邮件 Cron 验证前，状态为“已部署，端到端收件验收未完成”。
