# ddy数据管理系统

## 1. 这份文档的用途

这不是传统的项目介绍，而是一份给开发者和 AI 用的
“**报表配置生成指南**”。

目标有两个：

1. 说明 `menuitem` 报表配置到底怎么写。
2. 把代码里已经支持、但旧文档没整理全的能力补出来，
   方便后续直接让 AI 生成可运行的报表配置。

配套参考样例来自 `menuitem.sql`，其中包含真实的
`menuitem` 表结构、SQL 报表、Markdown 报表、PHP 报表、
图表、插件、字段配置等用法。

## 2. menuitem 关键字段

一个报表菜单通常至少关心下面这些字段：

- `name`：菜单名称，支持层级，如 `业务报表/订单分析/近30天`
- `type`：常见值为 `folder`、`report`、`link`
- `parent_id`：父节点 ID
- `dsn`：默认数据源，未指定时一般走 `default`
- `content_type`：内容类型，常见为 `sql`、`markdown`、`php`
- `dev_content` / `content`：报表实际内容
- `settings`：菜单附加配置，常见是图标、页面行为
- `disabled` / `visiable`：是否禁用、是否在菜单展示

推荐给 AI 的最小输入结构：

```json
{
  "name": "业务报表/订单分析/近30天订单",
  "type": "report",
  "content_type": "sql",
  "dsn": "default",
  "settings": "{\"icon\":\"icon-bar-chart\"}",
  "content": "-- @id=近30天订单\nSELECT ...;"
}
```

## 3. 报表内容的三种主要形态

### 3.1 SQL 报表

最常见。一个 `content` 里可以写一段或多段 SQL，
每段通过注释控制标题、图表、插件和显示行为。

最简单示例：

```sql
-- @id=订单查询
SELECT *
FROM aicoding_user_order
WHERE product_id = 'claude_code_test';
```

### 3.2 Markdown 报表

适合做说明文档、使用手册、排障指南。
在 `menuitem.sql` 里已经有这类真实样例。

```markdown
# 订单报表说明

- 数据源：`default`
- 用途：查询指定商品订单
- 建议过滤器：日期、优惠券、支付状态
```

也支持显式原始模式：

```text
#!MARKDOWN
# 标题
```

以及：

```text
#!RAW
直接输出原始文本，不走 HTML 包装
```

### 3.3 PHP 报表

适合复杂聚合、跨数据源拼接、二次加工、动态表格和图表。
核心入口通常是：

- `ddy_db()`
- `ddy_model()`
- `ddy_set_page_data()`
- `ddy_set_table_options()`
- `ddy_set_chart_options()`
- `ddy_register_form_handler()`

示例：

```php
<?php
$rows = ddy_db('default')->select("
    SELECT channel, COUNT(*) AS total
    FROM user_order
    GROUP BY channel
");

ddy_set_page_data([
    '渠道统计' => [
        'rows' => $rows,
        'options' => [
            'chart' => '__auto__',
        ],
    ],
]);
```

### 3.4 JSON / 动态报表输出

除了 SQL 和直接 `echo HTML`，模板引擎还支持：

- `ddy_set_page_data()` 直接返回报表结构
- 纯 JSON / `data:default` 风格结果
- `ddy_dynamic_reports(&$result, $data)` 动态追加报表块
- `ddy_process_result(&$result, $data)` 最终结果后处理

适合：

- 运行时根据数据决定增加哪些报表块
- 统一加工多个报表的标题、提示、图表、attrs
- 跨 SQL / PHP 结果做最后一轮整合

## 4. settings 配置说明

`menuitem.settings` 是报表页面级配置入口。
运行时由 `ReportController` 和 `Data_Template` 共同消费。

一个较完整的例子：

```json
{
  "icon": "icon-bar-chart",
  "auto_refresh": 30,
  "prepend_content": "<div class=\"alert alert-info\">数据每 30 秒刷新一次</div>",
  "remark": "这里只展示最近 30 天数据",
  "dsn": "default",
  "table": {
    "sum": true,
    "avg": false,
    "limit": 500,
    "dt": {
      "paging": true,
      "order": [[0, "desc"]]
    },
    "fields": {
      "consume_amount": {
        "header": "消耗金额",
        "tooltip": "单位：元"
      }
    }
  },
  "tables": {
    "每天消耗趋势": {
      "sum": false,
      "chart": "__auto__"
    }
  },
  "chart": "__auto__",
  "charts": {
    "渠道分布": "pie:channel,total_amount"
  },
  "mail": {
    "receiver": "somebody@example.com,username",
    "subject": "{name}",
    "enable": true
  }
}
```

### 4.1 配置优先级

同一份报表最终配置，按下面顺序叠加：

1. 系统默认值
2. `settings.table` / `settings.chart`
3. `settings.tables[报表ID]` / `settings.charts[报表ID]`
4. SQL 注释里的 `-- @xxx=...`
5. PHP 运行时注入：
   `ddy_set_table_options()` / `ddy_set_chart_options()`

也就是说，**离报表执行越近，优先级越高**。

### 4.2 `table` 和 `tables`

`table` 是所有 SQL 报表块的默认表格配置。  
`tables` 是按报表 ID 单独覆盖。

代码里已经确认这些键有明确效果：

- `sum`：是否显示合计行，默认 `false`
- `avg`：是否显示平均行，默认 `false`
- `limit`：SQL 自动补 `LIMIT` 的默认行数，默认 `1000`
- `dt`：DataTables 配置；若为 `false` 则关闭 datatable
- `fields`：列级配置
- `merge_cell`：按列名做纵向合并单元格，多个列逗号分隔
- `edit`：开启表格编辑能力
- `chart`：当前表块的图表配置
- `plugin_sum` / `plugin_delay_sum`：汇总插件配置
- `plugin_series`
- `plugin_date_line`
- `plugin_data_fluctuations`
- `join`
- `union`
- `invisible`：隐藏表格主体，但仍可配合图表使用
- `notice`：在表格上方显示提示信息
- `title` / `subtitle`：覆盖每个报表块标题、副标题
- `avg_pk`：按去重主键计算平均值
- `no_delay_sum`：禁用延迟汇总，改用普通汇总

需要注意：

- 虽然 `sum` 默认是 `false`，但模板层会默认开启
  `plugin_delay_sum`，所以很多报表即使没显式写 `sum`
  也会保留汇总能力。
- `dt` 关闭或配置了 `merge_cell` 时，前端不会启用 datatable。
- `avg_pk` 适合一人多行明细、但平均值想按“用户数”而不是“行数”算。
- `no_delay_sum` 适合需要在字段插件前就生成 sum/avg 的场景。

### 4.3 `chart` 和 `charts`

`chart` 是页面默认图表配置。  
`charts` 是按报表 ID 覆盖。

支持几种常见写法：

```json
{
  "chart": "__auto__"
}
```

```json
{
  "chart": "line:consume_amount,refund_amount"
}
```

```json
{
  "chart": "pie:channel,total_amount"
}
```

也支持对象形式：

```json
{
  "chart": {
    "type": "serial",
    "fields": "consume_amount,refund_amount"
  }
}
```

### 4.4 `mail`

当前发邮件主链路实际使用的是 `mail` 对象，不是旧文档里的
顶层 `mail_title` / `mail_receiver`。

已确认有效字段：

- `receiver`：收件人，支持邮箱或系统用户名，逗号分隔
- `subject`：邮件标题，默认 `{name}`
- `enable`：是否允许发送，设为 `false` 会直接拒绝发送

示例：

```json
{
  "mail": {
    "receiver": "ops@example.com,admin",
    "subject": "{name}",
    "enable": true
  }
}
```

当前代码里 `subject` 会替换：

- `{name}`：当前页面名称
- `{date|2026-03-31}`：按给定日期格式化成 `Y-m-d`

### 4.5 `alarm` / notify 通知

除了 `mail` 这种“手动发送报表邮件”，系统还支持
**报警通知链路**，入口是 `ReportController::alarmAction()`。

它的工作方式是：

1. 先正常执行报表
2. 读取每个报表块里的 `message`
3. 读取页面配置里的 `settings.alarm`
4. 按 `alarm.type` 指定的通知渠道逐个发送

当前已确认的配置结构：

```json
{
  "alarm": {
    "type": "lark"
  }
}
```

如果要多个渠道，可以写成逗号分隔：

```json
{
  "alarm": {
    "type": "lark,mail"
  }
}
```

说明：

- `alarm.type` 对应系统配置 `notify.xxx`
- 发送时会读取 `GG\Config::get("notify.{$type}")`
- 当前代码里通知接收人默认是 `@all`
- 报表块必须产出 `message`，否则不会发送

最常见的触发方式，是让某个表插件或 PHP 逻辑写入
报表 `message`。

例如 `plugin_data_fluctuations` 就会在波动超过阈值时，
自动往报表里写提示消息。

示例：

```json
{
  "alarm": {
    "type": "lark"
  }
}
```

```sql
-- @id=每日波动监控
-- @plugin_data_fluctuations={"field":["consume_amount","order_count"],"threshold_percent":50}
SELECT stat_date, consume_amount, order_count
FROM daily_finance
ORDER BY stat_date DESC;
```

然后通过报警任务入口执行：

- 报表页面渲染：`/report/index?id=xxx`
- 报警执行：`/report/alarm?id=xxx`

这两条链路是分开的。

### 4.6 其他页面级配置

这些键也已经在代码里确认使用：

- `icon`：菜单图标
- `auto_refresh`：前端自动刷新秒数
- `prepend_content`：页面顶部插入 HTML
- `remark`：页面顶部绿色说明块
- `disable_cache`：禁用 SQL 结果缓存
- `sql_cache`：设置 SQL 缓存秒数

### 4.7 字段配置 `fields`

`fields` 是最常用的细粒度配置项，通常放在 `table.fields`
或某个报表块的 `options.fields` 下。

已确认常用字段：

- `header`：表头重命名
- `tooltip`：表头或单元格提示
- `count`：参与前端汇总/平均计算
- `def`：汇总行表达式
- `nan`：控制空值展示
- `href`
- `class`
- `style`
- `raw`

示例：

```json
{
  "table": {
    "fields": {
      "consume_amount": {
        "header": "消耗金额",
        "tooltip": "单位：元",
        "count": true
      },
      "roi": {
        "header": "ROI",
        "def": "{收入}/{消耗金额}"
      }
    }
  }
}
```

## 5. 报表筛选控件

筛选控件是报表模板最重要的入口之一。  
它既负责渲染前端查询表单，也负责把用户输入转换成
SQL 可直接使用的宏变量。

代码入口主要在：

- `application/library/MY/FilterFactory.php`
- `application/library/MY/Filter/*`
- `application/library/MY/Data/Template.php`

### 5.1 基本语法

控件定义格式：

```sql
${name|label|default|type}
```

四段含义：

- `name`：控件名，只能是字母、数字、下划线
- `label`：前端显示名称
- `default`：默认值
- `type`：控件类型和参数

最常见示例：

```sql
${stat_date|统计日期|2026-01-01|date}
${username|用户名||string}
${coupon|优惠券||string.macro.raw}
```

配合 SQL 的典型写法：

```sql
${coupon|coupon||string.macro.raw};

-- {?coupon}
-- @id=优惠券订单
SELECT *
FROM aicoding_user_order
WHERE coupon = '{coupon}';
```

### 5.2 控件值如何进入 SQL

筛选控件有两种典型模式：

1. **替换模式**
   直接把控件值替换回模板中的 `${...}` 位置
2. **宏模式**
   不直接输出内容，而是生成后续可引用的宏变量

例如：

```sql
${name|name||string}
```

会直接输出一个字符串值。

而：

```sql
${name|name||string.macro.raw}
```

不会在当前位置输出内容，但会生成宏 `{name}`，
后续可以在 SQL 里引用。

### 5.3 系统内置基础控件

当前代码里已注册的基础控件类型有：

- `date`
- `time`
- `string`
- `number`
- `macro`
- `enum`
- `enum.multiple`
- `date_range`
- `time_range`
- `combine`
- `bool`
- `br`

下面按实际用途展开。

### 5.4 `date` 日期控件

用于单个日期输入，默认格式是 `Y-m-d`。

```sql
${mdate|日期|today|date}
```

常见变体：

```sql
${mdate|月份|first day of this month|date.month}
${mdate|日期|today|date.macro.raw}
${mdate|日期|today|date(limit:31)}
```

常见参数和 flag：

- `month`：按月份选择，格式变成 `Y-m`
- `macro`：生成宏，不直接输出
- `raw`：宏值不自动加引号
- `limit:n`：限制只能查最近 `n` 天
- `end:xxx`：限制最大日期

说明：

- `date.month` 是现网已在用的写法
- 默认值支持 `strtotime` 可解析的相对时间表达式

### 5.5 `time` 时间控件

用于单个时间输入，默认格式是 `Y-m-d H:i`。

```sql
${start_time|开始时间|today 00:00|time}
```

常见变体：

```sql
${hour_time|小时|today 00:00|time.hour}
${start_time|开始时间|today 00:00|time(step:5)}
${start_time|开始时间|today 00:00|time.macro.raw}
```

常见参数：

- `hour`：按整点选择，格式类似 `Y-m-d H:00`
- `step:n`：分钟步长
- `format:xxx`：输入格式
- `oformat:xxx`：输出格式
- `limit:n`：限制最近 `n` 天
- `macro`
- `raw`

### 5.6 `string` 文本控件

最常用的单行文本输入框。

```sql
${uid|UID||string}
${invite_code|邀请码||string.macro.raw}
```

适合：

- 用户 ID
- token
- 邀请码
- 订单号
- 关键字

推荐：

- 需要拼到 SQL 条件中时，优先用 `string.macro.raw`
- 需要安全转义时，用 `string.macro`

### 5.7 `number` 数字控件

用于整数、小数或数字列表输入。

```sql
${rate|扣量系数|15|number.macro.raw(min:10,max:90)}
${uid|UID||number}
${ids|用户ID列表||number.multiple.raw}
```

常见参数：

- `min`
- `max`
- `decimal`
- `multiple`
- `step`
- `prefix`
- `suffix`

说明：

- `multiple` 时输入格式是逗号分隔
- `decimal` 允许小数
- `number` 默认就是 `raw` 输出，适合数字条件

### 5.8 `enum` / `macro` 下拉控件

这类控件最终都会走枚举选择器。

#### 静态选项

```sql
${status|状态|1|enum(1:成功,0:失败)}
```

#### 多选

```sql
${channels|渠道||enum.multiple(1:直营,2:代理,3:联运)}
```

#### 宏模式

```sql
${business|业务类型|0|enum.macro.raw(0:所有,taobao:淘宝,tencent:腾讯)}
```

说明：

- `enum`：单选
- `enum.multiple`：多选
- `macro`：本质上也是枚举控件，只是更强调“生成宏”

常用附加参数：

- `minwidth:150`：下拉框最小宽度
- `order`：前端允许排序
- `tags`：前端标签模式

例如：

```sql
${business|业务线|0|enum.macro.raw(minwidth:220,0:全部,1:aicoding,2:gemini)}
```

### 5.9 `date_range` 日期范围控件

这是最常见的报表控件之一，会自动生成两个宏：

- `{from_xxx}`
- `{to_xxx}`

示例：

```sql
${date|日期|-7 days,today|date_range.macro.raw(range:31)}
```

后续 SQL 可以直接写：

```sql
WHERE created_at BETWEEN '{from_date} 00:00:00' AND '{to_date} 23:59:59'
```

说明：

- 默认要求必须同时选择开始和结束日期
- 未显式指定时，普通日期范围默认最大 31 天
- `month` 模式也可用于月范围选择

按月范围选择的推荐写法：

```sql
${month|月份范围|-2 months,this month|date_range.month.macro.raw}
```

这会生成：

- `{from_month}`
- `{to_month}`

值格式都是 `Y-m`，例如 `2026-02`、`2026-04`。

按月查询时，通常需要在 SQL 里补月初 / 下月月初：

```sql
WHERE created_at >= DATE_FORMAT('{from_month}-01', '%Y-%m-%d')
  AND created_at < DATE_ADD(DATE_FORMAT('{to_month}-01', '%Y-%m-%d'), INTERVAL 1 MONTH)
```

注意：

- `date_range.month` 不会自动套默认 `range:31`
- 更适合做月报、经营分析、月度对比

### 5.10 `time_range` 时间范围控件

和 `date_range` 类似，但精确到时分。

```sql
${ctime|创建时间|today 00:00,today 23:59|time_range.macro.raw(range:7)}
```

会生成：

- `{from_ctime}`
- `{to_ctime}`

说明：

- 默认最大范围是 30 天
- 适合查日志、任务执行、接口调用等精确时间段数据

### 5.11 `bool` 布尔控件

渲染成复选框，值会被转换成布尔语义。

```sql
${show_disabled|显示禁用数据|0|bool}
```

以下值会被视为 `false`：

- 空
- `no`
- `off`
- `0`
- `false`

适合：

- 是否显示隐藏数据
- 是否开启某个额外条件

### 5.12 `combine` 组合控件

不渲染新控件，而是把多个已有控件值拼成一个宏。

```sql
${date|日期|today|date.macro.raw}
${hour|小时|00|string.macro.raw}
${datetime|完整时间||combine(date,hour).raw}
```

说明：

- `combine(a,b,c)` 会按顺序拼接多个已有参数
- 常用于把日期、小时、前缀、后缀等拼成一个完整查询值
- 自身不会渲染输入框

### 5.13 `br` 换行控件

这是布局控件，不产生查询值，只用于换行。

```sql
${br}
```

等价于插入两个 `<br/>`。

### 5.14 常用 flag 说明

类型后面可以继续跟 `.xxx` 形式的 flag。

最常见的是：

- `macro`：生成宏，不直接输出
- `raw`：不自动加引号
- `bare`：不做 SQL 转义包装
- `multiple`：多选 / 多值
- `month`：月选择模式
- `hour`：小时模式

典型写法：

```sql
${mdate|日期|m|date.month.macro.raw}
${coupon|coupon||string.macro.raw}
${uids|用户ID||number.multiple.raw}
```

### 5.15 默认值支持相对时间

日期和时间类控件的默认值支持相对时间表达式，例如：

- `today`
- `yesterday`
- `-7 days`
- `first day of this month`
- `today 00:00`

范围控件默认值用逗号分隔：

```sql
${date|日期|-6 days,yesterday|date_range.macro(range:30)}
```

### 5.16 基于 PHP 实时生成的自定义枚举控件

这是你提到的重点场景。

在 `FilterFactory` 里，如果类型参数写成 `ddy_xxx` 函数，
系统会直接调用这个 PHP 函数，把返回值当成选项列表。

示例：

```sql
${business|业务类型|0|enum.macro.raw(ddy_page_business_type)}
```

对应 PHP：

```php
<?php
function ddy_page_business_type() {
    return [
        '0' => '所有',
        'taobao' => '淘宝',
        'tencent' => '腾讯',
        'baidu' => '百度'
    ];
}
?>
```

适合：

- 选项取决于当前环境
- 选项需要实时查库、查配置、查接口
- 不想把枚举硬编码在 SQL 里

返回值建议格式：

```php
[
  'value1' => '标签1',
  'value2' => '标签2'
]
```

### 5.17 基于 PHP 注册的自定义控件类

如果内置控件不够，可以自己注册新的 filter 类型。

代码入口：

- `FilterFactory::register($type, $class)`
- 自定义类继承 `\MY\Filter_Abstract`
  或 `\MY\Filter_SelectBase`

你可以实现：

- `filterInit()`：初始化参数
- `validate()`：校验输入
- `view()`：输出控件 HTML
- `getMacroData()`：输出宏

适合：

- 远程搜索下拉框
- 级联控件
- 特殊格式输入
- 动态查库控件

### 5.18 远程数据型下拉控件

如果自定义控件继承 `Filter_SelectBase`，
还可以走插件管理器注册数据接口，支持运行时搜索。

这类控件适合：

- 用户列表
- 商品列表
- 对象列表
- 数据量大、不适合一次性全量下发的下拉框

如果你自己实现基于 `Filter_SelectBase` 的控件，还可以进一步定制：

- `use_ajax_data`：是否走远程搜索
- `text_with_id`：选项文本里是否带 ID
- `value_type`：值类型是否为整数
- `prefix_match`：是否前缀匹配
- `value_column` / `text_column`：值列、展示列
- `filter_conditions`：固定筛选条件

### 5.19 推荐写法

实际写报表时，优先用下面几类组合：

```sql
${date|日期|-7 days,today|date_range.macro.raw(range:31)}
${uid|UID||string.macro.raw}
${status|状态|1|enum(1:成功,0:失败)}
${show_all|显示全部|0|bool}
```

这是最稳妥的一组基础组合。

### 5.20 宏与条件删除

模板里常见几种写法：

- `{name}`：直接替换变量
- `{?name}`：变量存在时保留该段
- `{?!name}`：变量不存在时保留该段
- `{4?name}`：按模板引擎既有规则做条件拼接

建议 AI 生成 SQL 时优先用 `-- {?xxx}` 这种显式控制，
不要手写一堆字符串拼接。

## 6. 动态 SQL 构造与模板宏

这一块是很多旧报表最依赖、但最容易被忽略的能力。

按当前代码实现，SQL 模板的大致处理顺序是：

1. 解析 `${...}` 控件定义，生成筛选器和宏
2. 执行模板中的内嵌 PHP
3. 按分号拆分多段 SQL
4. 对每段 SQL 执行 `_macro()`，替换 `{...}` 宏
5. 解析列尾 `-- @...` 字段配置
6. 清理注释，执行 SQL
7. 再根据字段 `def`、表插件、字段插件处理结果

对应代码主要在：

- `application/library/MY/Data/Template.php:_runSql()`
- `application/library/MY/Data/Template.php:_macro()`
- `application/library/MY/Data/Template.php:processRowTpl()`

### 6.1 宏替换基础语法

模板引擎当前支持这些核心写法：

- `{name}`：直接替换宏值
- `{?name}`：宏为空时，删除当前行
- `{?!name}`：宏不为空时，删除当前行
- `{4?name}`：宏为空时，连续删除 4 行
- `{a,b}`：组合多个宏，只要有值就拼接
- `{?a,b}`：组合条件判断
- `{name[raw]}`：带 pipeline 的宏写法
- `{date[+1 day|ymd|raw]}`：带参数和管道的宏写法

其中最常用的，不是花哨表达式，而是下面三种：

```sql
{name}
{?name}
{?!name}
```

### 6.2 `-- {?xxx}` 为什么能控制 SQL 结构

这是这套系统里最重要的“动态 SQL 构造”能力。

如果条件写在一行尾部：

```sql
SUM(income) AS '收入', -- {?show_income}
```

当 `show_income` 为空时，这一整行会被删掉；有值时，这一列保留。

如果条件单独占一行，且位于 SQL 片段前面：

```sql
-- {?cond}
-- @id=示例二
SELECT ...
```

当 `cond` 为空时，当前行开始后的整段 SQL 都会被跳过。

所以要区分两种用途：

- 行尾 `-- {?xxx}`：控制一列、一个条件、一个 `JOIN` 是否保留
- 行首单独 `-- {?xxx}`：控制整个报表块是否执行

### 6.3 `combine(...)` 的真实作用

`combine` 不会渲染控件，只会生成一个新的宏。

```sql
${show_income|显示收入|on|bool.macro};
${show_cost|显示成本|0|bool.macro};
${cond|||combine(show_income,show_cost)};
```

这里的 `cond` 本质是把 `show_income`、`show_cost` 两个宏拼起来。
只要其中任意一个有值，`{cond}` / `{?cond}` 就会判定为真。

这类写法适合：

- 某几个筛选条件只要命中一个，就显示某段 SQL
- 多个开关共同控制一组列
- 给整段子查询、整段 UNION 加显式开关

### 6.4 真实样例：按条件删列、删整段 SQL、替换表名

下面这段来自 `menuitem.sql` 的“sql语句的报表”样例，基本覆盖了最常用的动态 SQL 写法：

```sql
${date|日期|-6 days,yesterday|date_range.macro(range:30)};
${obj_id|对象id||testObj.macro.raw};
${show_income|显示收入|on|bool.macro};
${show_cost|显示成本|0|bool.macro};
${cond|||combine(show_income,show_cost)};

-- @id=示例一
SELECT
    me.date AS '日期',
    CONCAT(obj.name , '【', obj.id,'】') AS '对象',
    SUM(request) AS '请求',
    SUM(click) AS '点击',
    SUM(impression) AS '展现',
    SUM(income) AS '收入', -- {?show_income}
    SUM(cost) AS '成本', -- {?show_cost}
    SUM(click)/SUM(request)*100 AS 'CTR' -- @{点击}/{请求}*100
FROM test_income_report AS me
LEFT JOIN test_obj AS obj
ON me.obj_id = obj.id
WHERE me.date >= {?from_date}
AND me.date <= {?to_date}
AND me.obj_id IN ({?obj_id})
GROUP BY me.date, me.obj_id;

-- {?cond}
-- @id=示例二
SELECT
    me.date AS '日期',
    CONCAT(obj.name , '【', obj.id,'】') AS '对象',
    SUM(income) AS '收入', -- {?show_income}
    SUM(cost) AS '成本', -- {?show_cost}
    SUM(impression) AS '展现'
FROM test_income_report AS me
LEFT JOIN test_obj AS obj
ON me.obj_id = obj.id
WHERE me.date >= {?from_date}
AND me.date <= {?to_date}
AND me.obj_id IN ({?obj_id})
GROUP BY me.date, me.obj_id;

-- @id=示例三
SELECT
    me.date AS '日期',
    CONCAT(obj.name , '【', obj.id,'】') AS '对象'
FROM {income} AS me
LEFT JOIN {obj} AS obj
ON me.obj_id = obj.id
WHERE date >= {?from_date}
AND date <= {?to_date}
AND me.obj_id IN ({?obj_id})
GROUP BY me.date, me.obj_id;
```

这一段可以拆成四个能力点理解：

1. `date_range.macro` 生成 `{from_date}` / `{to_date}`
2. `testObj.macro.raw` 生成可直接用于 `IN (...)` 的对象列表宏
3. `-- {?show_income}` / `-- {?show_cost}` 动态控制列是否出现
4. `{income}` / `{obj}` 直接从系统宏里替换真实表名

### 6.5 行插件式写法，适合控制哪些 SQL 片段

虽然代码注释里写的是“解析行插件”，但这里更准确地说，
它是在做“按宏条件删行 / 保留行”的模板处理。

最适合控制的片段有：

- `SELECT` 列
- `WHERE` 条件
- `JOIN` 语句
- `UNION ALL` 中的某一支
- 整段报表块

典型示例：

```sql
WHERE 1 = 1
AND username = '{username}' -- {?username}
AND status = {status} -- {?status}
AND parent_id = {parent_id} -- {?parent_id}
```

这样写比在 PHP 里手拼 SQL 更稳，因为：

- 可读性更高
- 条件是否生效一眼可见
- 不容易漏掉空值分支
- AI 生成时也更稳定

### 6.6 字段注释 `-- @{...}` 与结果字段 `def`

列尾除了写 JSON，也可以直接写表达式：

```sql
SUM(click)/SUM(request)*100 AS 'CTR' -- @{点击}/{请求}*100
```

当前实现会把它解析成字段配置：

```json
{ "def": "{点击}/{请求}*100" }
```

随后在结果处理阶段，通过 `processRowTpl(..., $excute = true)`
把 `{点击}`、`{请求}` 替换成当前行或汇总行的真实值，再执行表达式。

这类写法适合：

- 比例列
- 汇总 / 平均行的二次计算
- 依赖其他列实时推导的展示字段

如果要写结构化字段配置，用 JSON：

```sql
request AS '请求', -- @{"json_display":true}
```

如果要写公式型字段配置，直接写表达式：

```sql
profit_rate AS '利润率' -- @round({利润}/{销售额}*100, 2)
```

### 6.7 行模板变量还能引用列位置和相邻行

`processRowTpl()` 还支持更细的行内替换：

- `{字段名}`：当前行字段
- `{0}`、`{1}`：按当前行列顺序取第 0 / 1 列
- `{字段名@-1}`：上一行同字段
- `{字段名@1}`：下一行同字段

这类能力主要给字段 `def`、字段插件、提示链接使用。

例如：

```json
{
  "def": "{利润}-{利润@-1}"
}
```

适合做：

- 环比差值
- 同行对比
- 用隐藏列拼接跳转 URL

### 6.8 推荐给 AI 的动态 SQL 写法

当需要让 AI 生成“条件可选”的报表时，优先让它按下面模式输出：

```sql
${date|日期|-7 days,today|date_range.macro.raw(range:31)};
${uid|UID||string.macro.raw};
${show_extra|显示扩展列|0|bool.macro};

-- @id=订单统计
SELECT
    stat_date,
    order_count,
    consume_amount,
    extra_amount -- {?show_extra}
FROM daily_order_stats
WHERE stat_date >= '{from_date}'
  AND stat_date <= '{to_date}'
  AND uid = '{uid}' -- {?uid}
ORDER BY stat_date DESC;
```

不要优先生成下面这种难维护的写法：

- PHP 里手动字符串拼 SQL
- `WHERE 1=1` 后拼很多 `if`
- 在前端拼接 SQL 片段

## 7. SQL 注释指令

每段 SQL 前常用这些注释：

- `-- @id=报表标题`
- `-- @dsn=数据源`
- `-- @chart=图表配置`
- `-- @plugin_xxx=...`
- `-- @invisible=true`

示例：

```sql
-- @id=每天分事件数据
-- @dsn=default
-- @plugin_delay_series={"xAxis":"time_date","series":["event_type"],"series_value":["event_count"]}
-- @chart=__auto__
-- @invisible=true
SELECT DATE_FORMAT(transaction_time, '%Y-%m-%d') AS time_date,
       event_type,
       COUNT(*) AS event_count
FROM card_transaction
WHERE transaction_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY time_date, event_type
ORDER BY time_date DESC;
```

## 8. 表插件、字段插件与自定义 PHP 插件

这套报表引擎里，“插件”主要分三层：

1. **表插件**
   处理整个结果集，例如排序、汇总、翻转、补线。
2. **字段插件**
   处理某一列的每个单元格，例如百分比、日期格式化、枚举映射。
3. **模板级 PHP 插件**
   通过 PHP 类注册到 `Data_Template`，
   扩展新的 `plugin_xxx`、`field_xxx`、
   `plugin_hook_before_sql`、`plugin_hook_result_response` 能力。

### 7.1 插件执行顺序

按当前实现，大致顺序是：

1. 先解析 `settings.table`、`settings.tables[报表ID]`
   和 SQL 注释 `-- @xxx=...`
2. 执行普通表插件 `plugin_xxx`
3. 执行字段插件 `field_xxx`
4. 执行延迟表插件 `plugin_delay_xxx`
5. 最后移除私有列，例如以下划线开头的列

所以：

- 需要基于原始结果集做加工时，优先用表插件
- 需要逐格渲染时，优先用字段插件
- 需要汇总后再处理的，优先用 `plugin_delay_xxx`

### 7.2 表插件总览

以下插件在 `application/library/MY/Data/Template.php`
里已确认实现：

- `plugin_filter`
- `plugin_sort`
- `plugin_flip`
- `plugin_sum`
- `plugin_series`
- `plugin_date_line`
- `plugin_data_fluctuations`
- `plugin_delay_sum`
- `plugin_delay_series`

#### 多报表拼装：`join` / `union`

这不是 `plugin_xxx` 形式的方法，但属于同一层级的
结果集拼装能力，执行时机也在表插件链路附近。

用途：

- 把多段 SQL 合并成一个报表块
- 用一段 SQL 做主表，另一段 SQL 做附表 join
- 多段结构相同的数据做 union

示意：

```sql
-- @id=main
-- @join=group1
SELECT uid, order_no, amount
FROM user_order;

-- @id=extra
-- @join={"group":"group1","on":["uid"],"full":false}
SELECT uid, email
FROM user;
```

或者：

```sql
-- @id=all_orders
-- @join={"group":"group1","union":true}
SELECT uid, order_no FROM order_a

-- @id=all_orders_2
-- @join={"group":"group1","union":true}
SELECT uid, order_no FROM order_b
```

说明：

- `join` 会按 `on` 指定的键合并
- `full` 控制是否做全量 join
- `union` 为 `true` 时走纵向合并
- 多段 SQL 最终会合成一个报表块，再继续走后续插件链路

#### `plugin_filter`

用途：对 SQL 返回结果做二次过滤。  
适合：已经查出数据，但还要按宏或运行时条件再筛一次。

配置格式：

```sql
-- @plugin_filter=[["status","=","paid"],["stat_date","between","2026-03-01,2026-03-31"]]
```

支持操作符：

- `=`
- `is`
- `>`
- `>=`
- `<`
- `<=`
- `!=`
- `in`
- `not in`
- `between`

#### `plugin_sort`

用途：按指定列排序，支持分组排序。  
适合：SQL 本身不方便表达复杂排序时。

示例：

```sql
-- @plugin_sort=-consume_amount,+stat_date
SELECT stat_date, consume_amount
FROM daily_finance;
```

分组排序示意：

```sql
-- @plugin_sort=-consume_amount(channel>$)
```

说明：

- `+` 表示升序
- `-` 表示降序
- `(...)` 表示分组权重排序

#### `plugin_flip`

用途：把“多行指标”翻成“纵向指标表”。  
适合：余额卡片、账户状态、单对象指标总览。

示例：

```php
ddy_set_page_data([
    'Corpay 余额' => [
        'rows' => [$rows],
        'options' => [
            'plugin_flip' => true,
        ],
    ],
]);
```

可选配置：

```json
{
  "plugin_flip": {
    "key": "日期,渠道"
  }
}
```

说明：

- 行数超过 50 时会直接报错
- 执行后会自动关闭该表的 sum/avg 汇总展示

#### `plugin_sum` / `plugin_delay_sum`

用途：生成合计行、平均行，也支持分组合计。  
适合：金额、数量、比率类表格。

示例：

```sql
-- @plugin_sum={"fields":["consume_amount","refund_amount"],"sum":true,"avg":true}
SELECT stat_date, consume_amount, refund_amount
FROM xxx;
```

分组合计示例：

```sql
-- @plugin_sum={"group":"渠道,账户","sum":true,"avg":true}
SELECT 渠道, 账户, 日期, 金额
FROM xxx;
```

说明：

- 未显式配置时，系统会默认挂上 `plugin_delay_sum`
- `fields` 可指定参与汇总的字段
- `group` 可生成分组合计、分组平均
- `avg_fields` 可指定某些列的合计行展示平均值
- `ignore` 可排除部分字段
- `avg_pk` 可让平均值按某个去重主键来算
- `no_delay_sum=true` 时，可把默认延迟汇总切回普通汇总

#### `plugin_series` / `plugin_delay_series`

用途：把明细行转成多条折线/多列 series 数据。  
适合：按天、按事件类型展开趋势图。

示例：

```sql
-- @plugin_delay_series={"xAxis":"time_date","series":["event_type"],"series_value":["event_count"]}
-- @chart=__auto__
SELECT stat_date AS time_date, event_type, event_count
FROM xxx;
```

说明：

- `xAxis`：横轴字段
- `series`：用来展开序列名的字段
- `series_value`：用来填充值的字段
- 一般和 `@chart=__auto__` 搭配使用

#### `plugin_date_line`

用途：补齐时间轴缺失日期。  
适合：日报、周报、月报折线图。

示例：

```sql
-- @plugin_date_line={"field":"stat_date","start":"2026-01-01","end":"2026-01-31"}
SELECT stat_date, amount
FROM xxx;
```

#### `plugin_data_fluctuations`

用途：比较最近两行数据，自动生成“波动提醒”。  
适合：预警型报表、日报波动提示。

示例：

```sql
-- @plugin_data_fluctuations={"field":["consume_amount","order_count"],"threshold_percent":50}
SELECT stat_date, consume_amount, order_count
FROM xxx
ORDER BY stat_date DESC;
```

说明：

- 默认只比较前两行
- 超过阈值时，会把提示写到报表顶部 `message`

### 7.3 字段插件和行级渲染

字段插件本质上是 `options.fields[列名]` 里的某个键，
会映射到方法 `field_xxx(...)`。

系统内置已确认支持：

- `ratio`
- `date`
- `time2str`
- `enum`

示例：

```json
{
  "table": {
    "fields": {
      "点击率": {
        "ratio": 100
      },
      "创建时间": {
        "time2str": "Y-m-d H:i"
      },
      "状态": {
        "enum": "0:失败,1:成功"
      }
    }
  }
}
```

#### 字段插件参数与返回值

字段插件签名通常是：

```php
function field_xxx($config_value, $value, $field, $i, $row, $report)
```

参数含义：

- `$config_value`：插件配置值
- `$value`：当前单元格原始值
- `$field`：当前列名
- `$i`：当前行号或特殊行 key
- `$row`：当前行数据
- `$report`：整个报表结构

返回值规则：

- 返回普通值：视为替换当前单元格的值
- 返回数组：可同时设置 `value` 和 HTML 属性

例如：

```php
return [
    'value' => '成功',
    'class' => 'label label-success',
    'raw' => true
];
```

#### 未识别字段插件时的回退行为

如果某个键找不到对应 `field_xxx` 方法，
系统会把它当成单元格属性模板处理。

最常用的就是：

- `href`
- `class`
- `style`
- `raw`
- `tooltip`

示例：

```sql
SELECT
    uid AS "UID",                 -- @{"href":"/#/report/38?query=uid={uid}"}
    request AS "Request",         -- @{"json_display": true}
    response AS "Response",       -- @{"json_display": true}
    click / pv * 100 AS "点击率"   -- @{点击}/{曝光}*100
FROM api_log;
```

其中：

- `href` 会渲染成链接
- `json_display` 属于自定义字段插件
- `@{点击}/{曝光}*100` 是 `def` 简写，用于汇总行重新计算

### 7.4 自定义 PHP 字段插件

有两种常见方式：

#### 方式一：在报表 PHP 代码里定义全局函数

命名规则：

```php
function ddy_field_xxx($config_value, $value, $field, $i, $row, $report) {}
```

示例：

```php
<?php
function ddy_field_week_day($config_value, $value, $field, $i, $row, $report) {
    return date('w', strtotime($value));
}
?>
```

对应配置：

```sql
SELECT
    stat_date AS "日期" -- @{"week_day": true}
FROM xxx;
```

#### 方式二：写插件类并注册

真实样例见 `application/library/PL/DDY/Report.php`。

```php
<?php
namespace PL\DDY;

class Report extends \MY\Plugin_Abstract
{
    public function pluginInit($dispatcher, $manager)
    {
        \MY\Data_Template::registerPlugin($this);
    }

    public function field_percent($config_value, $value, $field, $i, $row, $report)
    {
        return "{$value}%";
    }
}
```

对应配置：

```json
{
  "table": {
    "fields": {
      "成功数": {
        "percent": {
          "base": "总数",
          "dot": 2,
          "succ": 70
        }
      }
    }
  }
}
```

### 7.5 自定义 PHP 表插件

如果你需要扩展新的表插件，约定方法名是：

```php
public function plugin_xxx(&$report, $config, $data)
```

参数含义：

- `&$report`：当前报表块，包含 `rows`、`options`、`attrs`
- `$config`：插件配置值
- `$data`：当前请求参数、过滤器值

示例：

```php
<?php
namespace PL\Demo;

class Report extends \MY\Plugin_Abstract
{
    public function pluginInit($dispatcher, $manager)
    {
        \MY\Data_Template::registerPlugin($this);
    }

    public function plugin_mark_top(&$report, $config, $data)
    {
        $top = $config['top'] ?? 3;
        foreach ($report['rows'] as $i => &$row) {
            if ($i < $top) {
                $report['attrs'][$i]['_']['class'] = 'info';
            }
        }
    }
}
```

对应配置：

```sql
-- @plugin_mark_top={"top":3}
SELECT name, score
FROM ranking;
```

### 7.6 模板级 Hook 插件

除了 `plugin_xxx` 和 `field_xxx`，
模板引擎还支持两个更底层的扩展点：

- `plugin_hook_before_sql($dsn, $sql, $options, &$error)`
  可在 SQL 执行前做校验、拦截、改写
- `plugin_hook_result_response(&$result)`
  可在最终结果输出前做统一加工

适合：

- SQL 安全校验
- 审计日志
- 统一给报表插入额外 message / attrs / chart 配置

### 7.7 与插件相关的辅助函数

如果插件或 PHP 报表要动态覆盖配置，常用这些函数：

- `ddy_set_table_options($reportId, $options)`
- `ddy_set_chart_options($reportId, $options)`
- `ddy_set_page_data($data)`
- `ddy_register_form_handler($reportId, $handler)`

这些函数适合做：

- 运行时按数据量开启/关闭某个插件
- 动态补字段配置
- 表单提交后自定义处理

### 7.8 动态报表 Hook

模板引擎还支持两个非常关键的运行时扩展点：

#### `ddy_dynamic_reports(&$result, $data)`

用途：在原始 SQL / PHP 报表结果之外，再动态生成新的报表块。

示例：

```php
<?php
function ddy_dynamic_reports(&$result, $data) {
    return [
        [
            'id' => 'summary',
            'rows' => [
                ['指标' => '报表数', '值' => count($result['data'])]
            ],
            'options' => [
                'plugin_flip' => true
            ]
        ]
    ];
}
?>
```

#### `ddy_process_result(&$result, $data)`

用途：在最终输出前，统一改写整个页面结果。

适合：

- 统一追加 `notice`
- 统一覆盖 chart 配置
- 给所有报表块补 attrs / 标题 / message

示例：

```php
<?php
function ddy_process_result(&$result, $data) {
    foreach ($result['data'] as &$report) {
        $report['options']['notice'][] = '数据仅供参考';
    }
}
?>
```

## 9. 图表配置

前端 `ReportController.js` 已支持几种常见简写。

### 8.1 自动图表

```sql
-- @chart=__auto__
SELECT stat_date, consume_amount, refund_amount
FROM daily_finance;
```

行为：

- 第 1 列作为横轴
- 其余列自动作为 series
- 如果第 1 列像日期，会自动走时间轴推断

### 8.2 折线图字段简写

```sql
-- @chart=line:consume_amount,refund_amount
SELECT stat_date, consume_amount, refund_amount
FROM daily_finance;
```

### 8.3 饼图简写

```sql
-- @chart=pie:channel,total_amount
SELECT channel, SUM(amount) AS total_amount
FROM user_order
GROUP BY channel;
```

### 8.4 PHP 动态图表

```php
ddy_set_chart_options('渠道统计', [
    'type' => 'pie',
    'graphs' => [
        ['valueField' => 'channel'],
        ['valueField' => 'total'],
    ],
]);
```

## 10. PHP 报表高级能力

`application/helpers/dataddy.php` 里还藏了不少实用函数：

- `ddy_db($dsn)`：直接取数据库连接
- `ddy_model($table, $dsn)`：取模型
- `ddy_macro($name, $value, $quote)`：注入宏
- `ddy_data($name)`：取过滤器数据
- `ddy_set_page_data($data)`：输出页面报表数据
- `ddy_set_table_options($tableId, $options)`：动态表格配置
- `ddy_set_chart_options($chartId, $options)`：动态图表配置
- `ddy_register_form_handler()`：处理表单提交
- `ddy_http_request()`：调用外部 HTTP 接口

适合让 AI 使用的场景：

- SQL 不方便表达复杂逻辑
- 一个页面要组合多段不同来源数据
- 需要把明细加工成摘要卡片、翻转表、图表
- 需要动态生成表单或表格行为

### 9.1 可编辑报表

这部分能力很强，但容易被忽略。完整链路包含：

- `options.edit`
- `ddy_register_form_handler()`
- `/report/save`
- `/report/form`
- `/report/saveform`
- `ddy_save_form_handler()`

#### 行内编辑

通过 `options.edit` 指定哪些列可编辑，再注册保存处理函数。

示例：

```php
<?php
ddy_set_table_options(0, [
    'edit' => [
        'pk' => 'officeCode',
        'columns' => [
            '国家' => [
                'type' => 'select',
                'name' => 'country',
                'options' => [
                    [ 'label' => '美国', 'value' => 'USA' ],
                    [ 'label' => '中国', 'value' => 'China' ]
                ]
            ],
            'phone' => []
        ]
    ]
]);

ddy_register_form_handler(function (&$error, $row_id, $data) {
    $m = ddy_model('offices', 'demo');
    $ok = $m->update(['officeCode' => $row_id], $data);
    if ($ok === FALSE) {
        $error = '保存失败';
        return FALSE;
    }
    return TRUE;
});
?>
```

说明：

- `pk` 指定主键列，可写列名或列索引
- `columns` 定义可编辑列
- 每列可配置 `type`、`name`、`rule`、`options`
- `/report/save` 会调用注册的 handler 落库

#### 独立表单模式

如果不是行内编辑，而是完整表单，也支持：

- `/report/form?id=xxx`
- `/report/saveform?id=xxx`
- `ddy_save_form_handler($id, $data)`

适合：

- 新建对象
- 复杂录入表单
- 多字段一次性提交

### 9.2 Widget 模式

报表还支持 `/report/widget?id=xxx` 输出小组件结构。

适合：

- Dashboard 卡片
- 把表格报表摘要成指标卡
- 单独提取 sum + chart 数据

如果报表有 `chart_options`，widget 输出会带：

- `sum`
- `chart.options`
- `chart.data`

如果没有图表，则直接输出表格数据。

### 9.3 调试与编辑器辅助

这几个能力也值得写进使用说明：

- `/report/syntaxCheck`：前端编辑器语法检查
- `_disable_cache=1`：临时禁用缓存调试
- `sql_cache`：SQL 结果缓存秒数
- `disable_cache`：页面级禁用缓存

## 11. 菜单 settings 与图标

后端菜单树会优先读取 `settings.icon`。
如果没配置，目录默认是 `icon-bar-chart`。

推荐写法：

```json
{"icon":"icon-bar-chart"}
```

已确认代码里常见可用图标类：

- `icon-home`
- `icon-bar-chart`
- `icon-ghost`
- `icon-users`
- `icon-list`
- `icon-key`
- `icon-calendar`
- `icon-paper-clip`
- `icon-settings`

前端菜单类型默认图标还用了 Font Awesome：

- `fa fa-folder icon-state-warning icon-lg`
- `fa fa-file icon-state-success icon-lg`
- `fa fa-link icon-state-default icon-lg`
- `fa fa-shield icon-state-success icon-lg`
- `fa fa-eye-slash icon-state-default icon-lg`

建议：

- `settings.icon` 优先使用后端已大量使用的 `icon-*`
- 如果是菜单管理树上的类型图标，可参考现有 `fa fa-*`

## 12. 可直接给 AI 的报表生成示例

### 示例 1：最小可用 SQL 报表

```sql
-- @id=订单查询
SELECT *
FROM aicoding_user_order
WHERE product_id = 'claude_code_test';
```

### 示例 2：带过滤器的查询

```sql
${start_date|开始日期|2026-01-01|date}
${end_date|结束日期|2026-01-31|date}

-- @id=订单明细
SELECT *
FROM aicoding_user_order
WHERE created_at >= '{start_date}'
  AND created_at < DATE_ADD('{end_date}', INTERVAL 1 DAY);
```

### 示例 3：单页多个报表块

```sql
-- @id=总用户数
SELECT '总用户数' AS topic, COUNT(1) AS value
FROM aicoding_project_user;

-- @id=每月销售额
-- @chart=__auto__
SELECT DATE_FORMAT(created_at, '%Y-%m') AS sales_month,
       SUM(amount / 100) AS total_sales_amount
FROM aicoding_user_order
GROUP BY sales_month
ORDER BY sales_month;
```

### 示例 4：延迟序列化后画图

```sql
-- @id=每天分事件数据
-- @plugin_delay_series={"xAxis":"time_date","series":["event_type"],"series_value":["event_count"]}
-- @chart=__auto__
SELECT DATE_FORMAT(transaction_time, '%Y-%m-%d') AS time_date,
       event_type,
       COUNT(*) AS event_count
FROM card_transaction
GROUP BY time_date, event_type
ORDER BY time_date DESC;
```

### 示例 5：翻转成指标看板

```php
<?php
$rows = [
    '余额' => 1200,
    '冻结金额' => 300,
    '可用余额' => 900,
];

ddy_set_page_data([
    'Corpay 余额' => [
        'rows' => [$rows],
        'options' => [
            'plugin_flip' => true,
        ],
    ],
]);
```

### 示例 6：JSON 字段高亮展示

```sql
-- @id=接口日志
SELECT
    id,
    request AS "Request",   -- @{"json_display": true}
    response AS "Response"  -- @{"json_display": true}
FROM api_log
ORDER BY id DESC
LIMIT 100;
```

### 示例 7：指定数据源

```sql
-- @id=订阅收入
-- @dsn=wildcard_sub
SELECT
    stat_date,
    SUM(consume_amount) AS total_consume_amount
FROM wildcard_sub.user_api_call_record
WHERE stat_date >= '2026-01-01'
GROUP BY stat_date
ORDER BY stat_date DESC;
```

## 13. 真实模板示例库

这一节不是“最小示例”，而是从 `menuitem.sql` 里抽出来的
高复用真实模板。后续让 AI 生成报表时，最好直接指定
“参考下面哪个模板”。

### 模板 A：日期范围 + 枚举映射统计

适合：

- 按日期范围统计
- 列值需要映射成中文
- 多维度分组

```sql
${date|日期|-7 days,today|date_range.macro.raw(range:31)};

-- @id=渠道消耗
SELECT
    channel AS Channel,      -- @{"enum":"1:aibub"}
    channel_id AS "渠道类型", -- @{"enum":"1:中转1,2:中转2,4:微软4,5:微软5"}
    model_value,
    SUM(consume_amount) AS consume_amount
FROM user_api_call_record
WHERE created_at BETWEEN '{from_date} 00:00:00' AND '{to_date} 00:00:00'
GROUP BY channel, channel_id, model_value;
```

### 模板 B：PHP 宏推导 + 多报表块联查

适合：

- 用户可能输入多个不同查询入口
- 需要先通过 PHP 查库推导真实查询键
- 一个页面同时展示主记录、明细、日志

```php
<?php
$card_id = ddy_data('card_id');
$card_last4 = ddy_data('card_last4');

if ($card_id) {
    ddy_macro('real_card_id', $card_id, false);
}

if ($card_last4) {
    $row = ddy_model('card', 'corpay')->select(['card_number_last4' => $card_last4]);
    if ($row && count($row)) {
        ddy_macro('real_card_id', $row[0]['card_id'], false);
    }
}
?>

-- @id=card
-- @plugin_flip=true
-- {?real_card_id}
SELECT *
FROM card
WHERE card_id = '{real_card_id}';

-- @id=transaction
-- {?real_card_id}
SELECT *
FROM card_transaction
WHERE card_id = '{real_card_id}'
ORDER BY id DESC;
```

### 模板 C：趋势图 + `plugin_delay_series`

适合：

- 原始数据是一行一个事件类型
- 需要转成多序列图
- 图表比明细表更重要

```sql
-- @id=每天分事件数据
-- @plugin_delay_series={"xAxis":"time_date","series":["event_type"],"series_value":["event_count"]}
-- @chart=__auto__
-- @invisible=true
SELECT
    DATE_FORMAT(transaction_time, '%Y-%m-%d') AS time_date,
    event_type,
    COUNT(*) AS event_count
FROM card_transaction
WHERE transaction_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY time_date, event_type
ORDER BY time_date DESC;
```

### 模板 D：PHP 直出报表 + 翻转看板

适合：

- 数据来自 HTTP API
- 最终只展示指标看板
- 不想写 SQL

```php
<?php
$balance = ddy_http_request("GET", "https://example.com/account_balance", [
    'headers' => [
        'Authorization' => 'Bearer xxx'
    ]
]);

$rows = json_decode($balance, true)['data']['account_balance'];

ddy_set_page_data([
    '余额看板' => [
        'rows' => [$rows],
        'options' => [
            'plugin_flip' => true
        ]
    ]
]);
?>
```

### 模板 E：自定义字段插件渲染 JSON

适合：

- 某些列是 JSON 文本
- 需要美化展示
- 需要渲染图片、视频或 HTML

```php
<?php
function ddy_field_json_display($config_value, $value, $field, $i, $row, $report) {
    $data = json_decode($value, true);
    if (isset($data['video']['url'])) {
        return [
            'value' => '<video width="320" height="240" controls><source src="' . $data['video']['url'] . '" type="video/mp4"></video>',
            'raw' => true
        ];
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>

SELECT
    request AS "Request",   -- @{"json_display": true}
    response AS "Response"  -- @{"json_display": true}
FROM project_wanx
ORDER BY id DESC
LIMIT 10;
```

### 模板 F：动态枚举筛选控件

适合：

- 下拉选项不能写死
- 需要由 PHP 运行时返回

```sql
${business|业务类型|0|enum.macro.raw(ddy_page_business_type)};
```

```php
<?php
function ddy_page_business_type() {
    return [
        '0' => '所有',
        'taobao' => '淘宝',
        'tencent' => '腾讯',
        'baidu' => '百度'
    ];
}
?>
```

### 模板 G：文档型报表

适合：

- 说明文档
- FAQ
- 排障手册

```markdown
#!MARKDOWN

# 订单报表说明

- 数据源：`default`
- 过滤器：日期、优惠券、订单号
- 适用场景：排查某个订单的生命周期
```

### 模板 H：可编辑报表

适合：

- 直接在报表里改值并保存
- 某几列需要 select 或 text 编辑

```php
<?php
ddy_set_table_options(0, [
    'edit' => [
        'pk' => 'officeCode',
        'columns' => [
            '国家' => [
                'type' => 'select',
                'name' => 'country',
                'options' => [
                    [ 'label' => '美国', 'value' => 'USA' ],
                    [ 'label' => '中国', 'value' => 'China' ]
                ]
            ],
            'phone' => []
        ]
    ]
]);

ddy_register_form_handler(function (&$error, $row_id, $data) {
    $m = ddy_model('offices', 'demo');
    $ok = $m->update(['officeCode' => $row_id], $data);
    if ($ok === FALSE) {
        $error = '保存失败';
        return FALSE;
    }
    return TRUE;
});
?>
```

### 模板 I：报警型报表

适合：

- 日报波动预警
- 定时任务触发通知
- 通知渠道由 `settings.alarm` 控制

```json
{
  "alarm": {
    "type": "lark"
  }
}
```

```sql
-- @id=每日波动监控
-- @plugin_data_fluctuations={"field":["consume_amount","order_count"],"threshold_percent":50}
SELECT stat_date, consume_amount, order_count
FROM daily_finance
ORDER BY stat_date DESC;
```

### 模板 J：月范围 + 枚举过滤 + 双数据源经营汇总

适合：

- 做月度经营分析
- 同时汇总两个数据源
- 需要“是否排除代付/其他”这种业务过滤器
- 一页放业务明细和老板视角总览

```sql
${month|月份范围|-2 months,this month|date_range.month.macro.raw}
${exclude_proxy|是否排除代付/其他|0|enum.macro.raw(0:全部,1:排除代付和其他)}

-- @id=月度业务汇总
-- @merge_cell=report_month,business_line
WITH merged_data AS (
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS report_month,
        SUBSTRING_INDEX(product_id, '_', 1) AS business_type,
        amount AS order_amount,
        amount AS profit_amount
    FROM wildcard.user_openai_share_apply_record
    WHERE pay_status = 1
      AND created_at >= DATE_FORMAT('{from_month}-01', '%Y-%m-%d')
      AND created_at < DATE_ADD(DATE_FORMAT('{to_month}-01', '%Y-%m-%d'), INTERVAL 1 MONTH)

    UNION ALL

    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS report_month,
        SUBSTRING_INDEX(o.goods_type, '_', 2) AS business_type,
        o.amount AS order_amount,
        CASE 
            WHEN b.cdk_code_id IS NOT NULL THEN o.amount
            ELSE (o.amount - o.origin_price)
        END AS profit_amount
    FROM wildai_prod.user_order o
    LEFT JOIN wildcard.user_bind_record b ON o.order_no = b.order_no
    WHERE o.pay_status = 1
      AND o.created_at >= DATE_FORMAT('{from_month}-01', '%Y-%m-%d')
      AND o.created_at < DATE_ADD(DATE_FORMAT('{to_month}-01', '%Y-%m-%d'), INTERVAL 1 MONTH)
),
normalized_data AS (
    SELECT
        report_month,
        business_type AS sku,
        CASE 
            WHEN business_type IN ('gpt_plus','gpt_pro') THEN 'GPT 代付'
            WHEN business_type = 'aicoding' THEN 'aicoding'
            WHEN business_type = 'gemini' THEN 'gemini 随心用'
            WHEN business_type = 'claude' THEN 'claude 随心用'
            WHEN business_type IN ('claude_pro','claude_max') THEN 'claude 代付'
            WHEN business_type = 'chatgpt' THEN 'gpt 随心用'
            WHEN business_type = 'sora' THEN 'sora 随心用'
            WHEN business_type IN ('kiro_power','kiro_pro') THEN 'kiro 代付'
            WHEN business_type IN ('x_premium','x_basic') THEN 'x 代付'
            ELSE '其他/补偿'
        END AS business_line,
        order_amount,
        profit_amount
    FROM merged_data
)
SELECT
    report_month,
    business_line,
    sku,
    COUNT(*) AS order_count,
    SUM(order_amount) AS total_sales,
    SUM(profit_amount) AS total_profit,
    CONCAT(
        ROUND(
            SUM(profit_amount) / SUM(SUM(profit_amount)) OVER(PARTITION BY report_month) * 100,
            2
        ),
        '%'
    ) AS profit_ratio
FROM normalized_data
WHERE
    '{exclude_proxy}' = '0'
    OR (
        '{exclude_proxy}' = '1'
        AND business_line NOT LIKE '%代付'
        AND business_line <> '其他/补偿'
    )
GROUP BY report_month, business_line, sku
ORDER BY report_month DESC, total_profit DESC;

-- @id=老板视角_经营总览
WITH raw_data AS (
    SELECT 
        DATE(created_at) AS stat_date,
        DATE_FORMAT(created_at, '%Y-%m') AS report_month,
        SUBSTRING_INDEX(product_id, '_', 1) AS business_type,
        amount AS order_amount,
        amount AS profit_amount
    FROM wildcard.user_openai_share_apply_record
    WHERE pay_status = 1
      AND created_at >= DATE_FORMAT('{from_month}-01', '%Y-%m-%d')
      AND created_at < DATE_ADD(DATE_FORMAT('{to_month}-01', '%Y-%m-%d'), INTERVAL 1 MONTH)

    UNION ALL

    SELECT 
        DATE(o.created_at) AS stat_date,
        DATE_FORMAT(o.created_at, '%Y-%m') AS report_month,
        SUBSTRING_INDEX(o.goods_type, '_', 2) AS business_type,
        o.amount AS order_amount,
        CASE 
            WHEN b.cdk_code_id IS NOT NULL THEN o.amount
            ELSE (o.amount - o.origin_price)
        END AS profit_amount
    FROM wildai_prod.user_order o
    LEFT JOIN wildcard.user_bind_record b ON o.order_no = b.order_no
    WHERE o.pay_status = 1
      AND o.created_at >= DATE_FORMAT('{from_month}-01', '%Y-%m-%d')
      AND o.created_at < DATE_ADD(DATE_FORMAT('{to_month}-01', '%Y-%m-%d'), INTERVAL 1 MONTH)
),
normalized_data AS (
    SELECT
        stat_date,
        report_month,
        CASE 
            WHEN business_type IN ('gpt_plus','gpt_pro') THEN 'GPT 代付'
            WHEN business_type = 'aicoding' THEN 'aicoding'
            WHEN business_type = 'gemini' THEN 'gemini 随心用'
            WHEN business_type = 'claude' THEN 'claude 随心用'
            WHEN business_type IN ('claude_pro','claude_max') THEN 'claude 代付'
            WHEN business_type = 'chatgpt' THEN 'gpt 随心用'
            WHEN business_type = 'sora' THEN 'sora 随心用'
            WHEN business_type IN ('kiro_power','kiro_pro') THEN 'kiro 代付'
            WHEN business_type IN ('x_premium','x_basic') THEN 'x 代付'
            ELSE '其他/补偿'
        END AS business_line,
        order_amount,
        profit_amount
    FROM raw_data
),
filtered_data AS (
    SELECT *
    FROM normalized_data
    WHERE
        '{exclude_proxy}' = '0'
        OR (
            '{exclude_proxy}' = '1'
            AND business_line NOT LIKE '%代付'
            AND business_line <> '其他/补偿'
        )
)
SELECT
    report_month AS 月份,
    CASE
        WHEN report_month = DATE_FORMAT(CURDATE(), '%Y-%m') THEN '未完整'
        ELSE '完整'
    END AS 月份状态,
    COUNT(*) AS 总订单数,
    ROUND(SUM(order_amount), 2) AS 总销售额,
    ROUND(SUM(profit_amount), 2) AS 总利润,
    ROUND(SUM(profit_amount) / NULLIF(COUNT(DISTINCT stat_date), 0), 2) AS 日均利润,
    ROUND(SUM(order_amount) / NULLIF(COUNT(*), 0), 2) AS 平均客单价
FROM filtered_data
GROUP BY report_month
ORDER BY report_month DESC;
```

这个模板的关键点：

- 用 `date_range.month.macro.raw` 做月范围
- 用 `enum.macro.raw` 做业务过滤器
- 用 `UNION ALL` 合并两个来源
- 用 `@merge_cell` 优化月度分组显示
- 一页同时输出业务明细和老板视角总览

### 模板选择建议

- 查明细：优先模板 A、B
- 趋势图：优先模板 C
- 接口聚合：优先模板 D
- JSON 富展示：优先模板 E
- 动态筛选：优先模板 F
- 文档页：优先模板 G
- 可编辑后台：优先模板 H
- 预警通知：优先模板 I
- 月度经营分析：优先模板 J

## 14. 给 AI 的提示词模板

如果你要让 AI 直接产出 `menuitem` 配置，建议至少给它这些信息：

1. 菜单路径：例如 `业务报表/支付/订阅收入`
2. 报表类型：`sql` / `markdown` / `php`
3. 数据源：例如 `default`、`wildcard_sub`
4. 统计维度：例如 `stat_date`、`channel`
5. 指标：例如 `consume_amount`、`order_count`
6. 过滤器：日期、用户、渠道、优惠券等
7. 展示方式：明细表 / 折线图 / 饼图 / 翻转表
8. 是否需要插件：`plugin_sum`、`plugin_flip`、`plugin_delay_series`
9. 是否需要图标：例如 `{"icon":"icon-bar-chart"}`

推荐提示词：

```text
请帮我生成一条 dataddy 的 menuitem 报表配置：

1. 菜单路径：业务报表/支付/订阅收入
2. 类型：report
3. content_type：sql
4. dsn：wildcard_sub
5. 需求：
   - 按天统计 consume_amount
   - 支持开始日期、结束日期、api_token 过滤
   - 输出明细表
   - 自动生成折线图
   - 菜单图标使用 icon-bar-chart
6. 请直接输出可插入 menuitem 的 content、settings，并尽量使用 dataddy 已支持的注释语法和插件语法。
```

## 15. 生成规则总结

让 AI 生成报表配置时，优先遵守这几条：

1. 优先生成 **简单 SQL + 注释配置**，不要上来就写 PHP。
2. 能用 `@chart`、`@plugin_xxx` 解决的，先不要自定义前端逻辑。
3. 过滤器优先用 `${...}`，不要手写拼接。
4. 多个结果块可以写在同一个 `content` 里，用多个 `-- @id=...` 分段。
5. 目录图标优先放到 `settings.icon`，推荐 `icon-bar-chart` 一类。
6. 涉及复杂加工时，再退到 PHP 报表，优先用 `ddy_set_page_data()`。

如果后续继续补文档，优先从 `menuitem.sql` 里新增真实案例，
不要只写抽象描述。
