# 新增公开产品内容审计与待执行修正 — 2026-11-04

## 状态与边界

基线为 PR #6 的提交 `7195d45e09572ae86841d53be3a7a4fbd3eab291` 及 live-supplemental-content.json。核查日 2026-11-04（北京时间）。PR #6 在本次读取时 open / draft / merged=false。PR 文档记录生产已切换；本次直接读取了全部20个生产产品页面及其20个目录来源。此次变更仅为审计与具体修正提案，未写入生产 WordPress，未执行内容审核通过、暂停展示或账号权限变更。

原63条英文、六语60条、20条草稿均不修改。本次未以生产数据库独立复点草稿数量。GitHub 合并不会自动证明数据库中的产品正文同步更新。

## 来源核对

20/20页面正文均有易派客供应商目录URL，逐页读取对应商品标题及“商品介绍”名称、规格型号、说明。SCW-537来源显示品牌“胜油测录”、供应商“胜利油田胜油测录工程技术有限公司”。这支持供应商目录归属，不构成制造商身份、授权经销关系、原始数据表或产品认证的独立认证。

| WP ID | 当前生产产品 | 已读取的来源 | 结论 |
|---|---|---|---|
| 622 | [wireless-torque-sensor-3-3v-0-1000a](https://www.chinamudlogging.com/product/wireless-torque-sensor-3-3v-0-1000a/) | [目录原文](https://emall.epec.com/productd/141652/15487643) | 名称/型号对应；用途扩写未获数据表支持 |
| 588 | [scw-535-wireless-pressure-sensor](https://www.chinamudlogging.com/product/scw-535-wireless-pressure-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487653) | 名称/型号对应；用途扩写未获数据表支持 |
| 590 | [scw-537-wireless-h2s-sensor](https://www.chinamudlogging.com/product/scw-537-wireless-h2s-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487644) | 名称/型号对应；用途扩写未获数据表支持 |
| 627 | [scw-539-wireless-temperature-sensor](https://www.chinamudlogging.com/product/scw-539-wireless-temperature-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487650) | 名称/型号对应；用途扩写未获数据表支持 |
| 593 | [csy-by-wireless-hookload-sensor](https://www.chinamudlogging.com/product/csy-by-wireless-hookload-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487527) | 名称/型号对应；用途扩写未获数据表支持 |
| 595 | [scw-53b-wireless-flow-sensor](https://www.chinamudlogging.com/product/scw-53b-wireless-flow-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487645) | 名称/型号对应；用途扩写未获数据表支持 |
| 629 | [scw-53b-wireless-density-sensor](https://www.chinamudlogging.com/product/scw-53b-wireless-density-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487624) | 名称/型号对应；用途扩写未获数据表支持 |
| 598 | [scw-532-wireless-pump-stroke-sensor](https://www.chinamudlogging.com/product/scw-532-wireless-pump-stroke-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487628) | 名称/型号对应；用途扩写未获数据表支持 |
| 600 | [ultrasonic-sensor-bracket-mud-logging](https://www.chinamudlogging.com/product/ultrasonic-sensor-bracket-mud-logging/) | [目录原文](https://emall.epec.com/productd/141652/15487160) | 字段归类错误 |
| 601 | [csy-aty-wireless-standpipe-pressure-sensor](https://www.chinamudlogging.com/product/csy-aty-wireless-standpipe-pressure-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487526) | 名称/型号对应；用途扩写未获数据表支持 |
| 603 | [scw-536-wireless-ultrasonic-sensor](https://www.chinamudlogging.com/product/scw-536-wireless-ultrasonic-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487625) | 名称/型号对应；用途扩写未获数据表支持 |
| 605 | [hu-48y-wireless-flow-sensor-probe](https://www.chinamudlogging.com/product/hu-48y-wireless-flow-sensor-probe/) | [目录原文](https://emall.epec.com/productd/141652/15487525) | 名称/型号对应；用途扩写未获数据表支持 |
| 607 | [an-02-volume-sensor-circuit-board](https://www.chinamudlogging.com/product/an-02-volume-sensor-circuit-board/) | [目录原文](https://emall.epec.com/productd/141652/15487433) | 名称/型号对应；用途扩写未获数据表支持 |
| 609 | [wdas-battery-wireless-sensor-battery-pack](https://www.chinamudlogging.com/product/wdas-battery-wireless-sensor-battery-pack/) | [目录原文](https://emall.epec.com/productd/141652/15487139) | 名称/型号对应；用途扩写未获数据表支持 |
| 611 | [scw-53d-wireless-electric-torque-sensor](https://www.chinamudlogging.com/product/scw-53d-wireless-electric-torque-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487623) | 名称/型号对应；用途扩写未获数据表支持 |
| 613 | [csy-apy-wireless-casing-pressure-sensor-probe](https://www.chinamudlogging.com/product/csy-apy-wireless-casing-pressure-sensor-probe/) | [目录原文](https://emall.epec.com/productd/141652/15487428) | 名称/型号对应；用途扩写未获数据表支持 |
| 615 | [scw-53c-wireless-top-screw-torque-sensor](https://www.chinamudlogging.com/product/scw-53c-wireless-top-screw-torque-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487655) | 名称/型号对应；用途扩写未获数据表支持 |
| 616 | [scw-53a-wireless-conductivity-sensor](https://www.chinamudlogging.com/product/scw-53a-wireless-conductivity-sensor/) | [目录原文](https://emall.epec.com/productd/141652/15487654) | 名称/型号对应；用途扩写未获数据表支持 |
| 618 | [bk4-488-009-mud-sensor-mounting-frame](https://www.chinamudlogging.com/product/bk4-488-009-mud-sensor-mounting-frame/) | [目录原文](https://emall.epec.com/productd/141652/15487672) | 名称/型号对应；用途扩写未获数据表支持 |
| 620 | [h2s-02-h2s-sensor-circuit-board](https://www.chinamudlogging.com/product/h2s-02-h2s-sensor-circuit-board/) | [目录原文](https://emall.epec.com/productd/141652/15487432) | 名称/型号对应；用途扩写未获数据表支持 |

SCW-53B 在流量和密度两个目录原文中确实同名型号，不能凭重复型号认定本站复制错误，也不能据此认定互换兼容。3.3V 0-1000A 是原目录的规格型号字符串；本站已注明其不属于独立核实性能值，本次不推断它是扭矩单位或改为 N·m。

除ID600字段归类外，名称及型号与目录记录对应。英文“wireless”等修饰有时来自目录配件说明而非产品名称，目录说明不保证整个器件具有独立无线能力。20页均有通用用途或兼容性扩写，不能把这些扩写当成厂家确认结论。

## 确认的字段错误及最小修正

ID600 原目录商品介绍明确：
- 名称：超声波传感器支架
- 规格型号：超声波体积
- 说明：传感器配件

当前本站省略 Model / SKU，并将“超声波体积 传感器配件”合并为 Category note。建议仅修正此产品正文规格表、摘要与对应结构化字段：
- Source specification label: 超声波体积
- Category note: 传感器配件
- 保留名称、URL、WP ID及原文；不要把“超声波体积”擅自规范成工程型号。
- Overview 建议：`The supplier catalogue lists an ultrasonic sensor bracket with the source specification label “超声波体积” and the note “传感器配件”. Dimensions, materials and installation compatibility require supplier documentation.`

ID618安装架与ID600支架使用通用“wireless sensor suite”应用段落，来源并未说明其无线属性。两页 Applications 建议替换为：
`Confirm dimensions, mounting arrangement, materials and compatibility with the intended instrument before ordering. The catalogue listing does not establish these details.`

## 危险气体及用途证据风险（不等于已证实性能错误）

ID590 SCW-537 当前 Overview 第二句把它关联到气体安全与地层流体监测。已核对目录只有名称、型号与配件说明，不能支持这一具体产品的安全适用性。建议替换该段为：
`The supplier catalogue lists a hydrogen sulfide sensor, model SCW-537, under wireless sensor accessories. Detection range, accuracy, response time, calibration requirements, alarm functions and suitability for any safety-related use have not been verified from manufacturer documentation. Obtain the applicable datasheet and certification evidence before selection.`

ID620 H2S-02 是电路板配件；其目录不证明它能独立检测H2S或组成合格报警系统。建议 Overview：
`The supplier catalogue lists an H2S sensor circuit board, model H2S-02, under wireless sensor accessories. This listing does not establish a complete detector or an approved safety system. Electrical interfaces, compatible sensor assemblies and any safety-related suitability require manufacturer documentation.`

其余产品 Overview 的具体用途（井控、立管/套管通道、顶驱/旋转设备、罐容积等）建议逐条删去未经该型号资料支持的断言，保留“supplier catalogue lists + 原始名称/型号/说明”。不能以行业通用用途替代型号适用证据。

在20页main正文中未看到ATEX、IECEx、SIL或防爆等级等具体通过认证声明。通用“Final specifications, certification ... are confirmed for the ordered configuration”不是证书，但易让人误读可提供认证。建议20页统一为：
`Specifications, availability and delivery require quotation confirmation. No certification is established by this catalogue listing; request configuration-specific evidence where applicable.`

没有取得制造商数据表、校准/响应资料、证书编号/签发机构/有效期及配置匹配证据，故上述项目均待验证，不能授予“内容审核通过”。本次未发现足以证明必须立即暂停全部20页的事实；优先处理590/620安全表述。若负责人决定暂停，须先私有导出正文、元数据、修订及附件并记录哈希，再仅将相应ID转为可恢复草稿；不得删除。

## 询盘验证

20/20产品的 Request a quote 链接均指向本站 contact-us，product 参数与各产品标题对应。实际点击ID620入口，Contact页 Product / application 正确预填，存在姓名、邮箱、需求和隐私同意字段。本轮未提交新询盘，未独立复测保存/通知/实际收件；PR #6 的既有投递证据不等于本轮新增产品的内容审核或订单配置确认。

## 发布记录、作者与账号权限：受阻待核查

生产 wp-admin 实际返回 WordPress登录页，当前浏览器无已登录会话；未发现可用WordPress/SiteGround专用连接能力。因此未取得 post_author、post_date_gmt、post_modified_gmt、修订作者/时间、发布事件或审批记录。不能从WP ID顺序、页面状态、GitHub作者或注册时间推断实际编辑人。

新用户注册通知已独立读取，时间为2026-11-04 07:31:48 UTC（北京时间15:31:48），确认用户名simon；邮件不含角色或权限。未核实其当前角色，不能认定其为管理员、发布者或上述产品作者。出于隐私，注册邮箱和原始邮件头不写入仓库。

具备授权后台或只读WP-CLI后，需要：
1. 私有导出且保留20个ID的当前正文、摘要、meta、语言、作者、创建/修改GMT时间、发布状态及修订，记录导出时间和SHA256；审计日志含个人信息，禁止提交公开仓库。
2. 核对每个revision的作者、时间与差异；post_author不是发布审批人，修订可能不保存发布事件或所有meta，缺记录不证明未审核。
3. 独立核对simon角色及实际能力，以及默认注册角色、允许注册设置；重点检查cml_product所映射的edit/publish/edit_others权限，含自定义角色和插件授予能力。只读调查，不自动降权。
4. 对照真正审批记录/工单确认来源核验人、版本、授权和审核时间。作者身份与审核授权分别举证。

## 执行与回归要求

上述为可审阅的精确文案/字段修正，尚未部署。取得访问后，每个ID更新前先比对导出版本或post_modified_gmt，有并发编辑则停止覆盖；仅修改已审阅段落及其对应meta，不重跑迁移覆盖人工编辑。

执行后逐页核对差异、来源字段与正文一致、H1/既有URL/canonical、语言、询盘参数；确认原123条已发布迁移产品、20草稿和其他人工内容均未受影响。危险气体表述须人工审核；只有实际修改和回归成功后才标注修复完成。页面可读、HTTP200、代码合并和内容批准必须分别记录。
