# 业务流程：KDS（制茶助手/效期） (BIZ_KDS)

## 1. 概述
- **模块用途**：KDS (Kitchen Display System)，在此项目中主要用于“制茶助手 (SOP)”和“物料效期管理 (Expiry)”。
- **涉及主要表** (基于 `kds_registry.php` 推测)：
  - `kds_materials` (物料定义)
  - `kds_material_translations` (物料翻译)
  - `kds_material_expiries` (物料效期实例)
  - `kds_cups` (杯型)
  - `kds_ice_options` (冰量)
  - `kds_sweetness_options` (甜度)
  - `(产品/配方相关表)` (待确认)
- **涉及主要接口** (KDS 网关)：
  - `res=sop, act=get` (获取 SOP 配方)
  - `res=expiry, act=record` (记录物料开封)
  - `res=expiry, act=get_items` (获取效期内物料)
  - `res=expiry, act=update_status` (更新效期物料状态)
  - `res=prep, act=get_materials` (获取可备料列表)

## 2. 核心流程：SOP (制茶助手)

此流程基于 `kds_registry.php` 中 `handle_kds_sop_get` 的逻辑反向推断。

1.  **入口**
    -   KDS SOP 页面 (`html/pos/kds/index.php?page=sop`)。
2.  **获取编码**
    -   (推测) 用户通过扫描或其他方式输入一个 "P-A-M-T" 编码 (产品-杯型-冰量-甜度)。
    -   前端将此编码 (`code`) 发送给 KDS API 网关。
3.  **API 调用**
    -   前端调用 `res=sop, act=get`。
4.  **后端处理 (`handle_kds_sop_get`)**
    -   (1) **解析**: `KdsSopParser` 解析 `code` 字符串，分离出 `P` (产品), `A` (杯型), `M` (冰量), `T` (甜度)。
    -   (2) **查询**: 根据 `P` 编码获取产品 ID (`pid`)。
    -   (3) **基础配方**: 获取 `pid` 的基础配方 (`get_base_recipe`)。
    -   (4) **查询选项**: 根据 `A`, `M`, `T` 编码获取对应的 ID。
    -   (5) **Gating**: 检查产品和选项的组合是否被 Gating 规则禁止 (`check_gating`)。
    -   (6) **动态计算**:
        -   应用全局规则 (`apply_global_rules`)。
        -   应用覆盖规则 (`apply_overrides`)。
    -   (7) **格式化**: 整合最终配方步骤和翻译 (`m_details`, `u_name`)。
5.  **返回**
    -   返回 `adjusted_recipe` (动态配方) 或 `base_info` (如果只提供了 P-Code)。

## 3. 核心流程：效期管理 (Expiry)

此流程基于 `kds_registry.php` 中 `handle_kds_expiry_record` 等函数的逻辑推断。

1.  **获取可备料物料**
    -   (推测) 备料页面加载时，调用 `res=prep, act=get_materials` (由 `handle_kds_get_preppable` 处理)，获取所有设置了效期规则的物料。
2.  **记录开封 (打印标签)**
    -   (推测) 用户选择一个物料（如 `material_id=5`）进行开封。
    -   调用 `res=expiry, act=record` (由 `handle_kds_expiry_record` 处理)。
    -   后端：
        -   (1) 查找 `kds_materials` 表获取该物料的 `expiry_rule_type` (HOURS, DAYS, END_OF_DAY)。
        -   (2) 根据规则，计算出 `expires_at_utc` (UTC 过期时间)。
        -   (3) 向 `kds_material_expiries` 表插入一条 `status = 'ACTIVE'` 的新纪录。
        -   (4) 返回用于打印标签的**本地时间**数据 (`print_data`)。
3.  **管理激活的效期**
    -   (推测) 效期管理页面加载时，调用 `res=expiry, act=get_items` (由 `handle_kds_get_expiry_items` 处理)，列出所有 `status = 'ACTIVE'` 的记录。
4.  **核销/丢弃**
    -   (推测) 用户将某个激活的效期项标记为 "用完" 或 "丢弃"。
    -   调用 `res=expiry, act=update_status` (由 `handle_kds_update_expiry_status` 处理)。
    -   后端：
        -   (1) 更新 `kds_material_expiries` 中对应 `id` 的 `status` 为 `USED` 或 `DISCARDED`。
        -   (2) 记录操作人 `handler_id` 和操作时间 `handled_at` (UTC)。