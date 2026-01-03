# 后端接口总览（API_OVERVIEW）

> **说明**：本文件基于 `pos_registry.php`、`pos_registry_ext_pass.php` 和 `kds_registry.php` 生成。
> 
> **网关模式**：
> 1.  **POS 接口**：所有请求均发往 `html/pos/api/pos_api_gateway.php`。
> 2.  **KDS 接口**：所有请求均发往 `html/pos/kds/api/kds_api_gateway.php`。
> 3.  **路由**：通过 `res` (resource) 和 `act` (action) 两个参数（通常在 POST 的 JSON 体中）来路由到对应的 `handle_` 函数。

## 模块：POS 收银 (pos_registry.php)

### 资源 (Resource): `pass` (次卡)
-   **接口**：售卡
-   **参数**：`res=pass`, `act=purchase`
-   **后端处理**：`handle_pass_purchase`
-   **说明**：(实现在 `pos_registry_member_pass.php` 中, (待确认))

-   **接口**：核销 (占位)
-   **参数**：`res=pass`, `act=redeem`
-   **后端处理**：`handle_pass_redeem`
-   **说明**：此定义被 `pos_registry_ext_pass.php` 覆盖。

### 资源 (Resource): `order` (订单)
-   **接口**：提交订单
-   **参数**：`res=order`, `act=submit`
-   **后端处理**：`handle_order_submit`
-   **说明**：(实现在 `pos_registry_sales.php` 中, (待确认))

### 资源 (Resource): `cart` (购物车)
-   **接口**：计算购物车
-   **参数**：`res=cart`, `act=calculate`
-   **后端处理**：`handle_cart_calculate`
-   **说明**：(实现在 `pos_registry_sales.php` 中, (待确认))

### 资源 (Resource): `shift` (班次)
-   **接口**：获取班次状态
-   **参数**：`res=shift`, `act=status`
-   **后端处理**：`handle_shift_status`

-   **接口**：开始班次
-   **参数**：`res=shift`, `act=start`
-   **后端处理**：`handle_shift_start`

-   **接口**：结束班次
-   **参数**：`res=shift`, `act=end`
-   **后端处理**：`handle_shift_end`

-   **接口**：强制开始班次
-   **参数**：`res=shift`, `act=force_start`
-   **后端处理**：`handle_shift_force_start`
-   **说明**：(以上 `shift` 接口实现在 `pos_registry_ops_shift.php` 中, (待确认))

### 资源 (Resource): `data` (初始化数据)
-   **接口**：加载 POS 初始数据
-   **参数**：`res=data`, `act=load`
-   **后端处理**：`handle_data_load`
-   **说明**：(实现在 `pos_registry_ops.php` 中, (待确认))

### 资源 (Resource): `member` (会员)
-   **接口**：查找会员
-   **参数**：`res=member`, `act=find`
-   **后端处理**：`handle_member_find`

-   **接口**：创建会员
-   **参数**：`res=member`, `act=create`
-   **后端处理**：`handle_member_create`
-   **说明**：(以上 `member` 接口实现在 `pos_registry_member_pass.php` 中, (待确认))

### 资源 (Resource): `hold` (挂起单)
-   **接口**：列出挂起单
-   **参数**：`res=hold`, `act=list`
-   **后端处理**：`handle_hold_list`

-   **接口**：保存挂起单
-   **参数**：`res=hold`, `act=save`
-   **后端处理**：`handle_hold_save`

-   **接口**：恢复挂起单
-   **参数**：`res=hold`, `act=restore`
-   **后端处理**：`handle_hold_restore`
-   **说明**：(以上 `hold` 接口实现在 `pos_registry_ops.php` 中, (待确认))

### 资源 (Resource): `transaction` (交易)
-   **接口**：列出交易
-   **参数**：`res=transaction`, `act=list`
-   **后端处理**：`handle_txn_list`

-   **接口**：获取交易详情
-   **参数**：`res=transaction`, `act=get_details`
-   **后端处理**：`handle_txn_get_details`
-   **说明**：(以上 `transaction` 接口实现在 `pos_registry_ops.php` 中, (待确认))

### 资源 (Resource): `print` (打印)
-   **接口**：获取打印模板
-   **参数**：`res=print`, `act=get_templates`
-   **后端处理**：`handle_print_get_templates`

-   **接口**：获取 EOD 打印数据
-   **参数**：`res=print`, `act=get_eod_data`
-   **后端处理**：`handle_print_get_eod_data`
-   **说明**：(以上 `print` 接口实现在 `pos_registry_ops.php` 中, (待确认))

### 资源 (Resource): `availability` (估清)
-   **接口**：获取所有估清
-   **参数**：`res=availability`, `act=get_all`
-   **后端处理**：`handle_avail_get_all`

-   **接口**：切换估清状态
-   **参数**：`res=availability`, `act=toggle`
-   **后端处理**：`handle_avail_toggle`

-   **接口**：重置所有估清
-   **参数**：`res=availability`, `act=reset_all`
-   **后端处理**：`handle_avail_reset_all`
-   **说明**：(以上 `availability` 接口实现在 `pos_registry_ops.php` 中, (待确认))

### 资源 (Resource): `eod` (日结)
-   **接口**：获取日结预览
-   **参数**：`res=eod`, `act=get_preview`
-   **后端处理**：`handle_eod_get_preview`

-   **接口**：提交日结报告
-   **参数**：`res=eod`, `act=submit_report`
-   **后端处理**：`handle_eod_submit_report`

-   **接口**：列出历史日结
-   **参数**：`res=eod`, `act=list`
-   **后端处理**：`handle_eod_list`

-   **接口**：获取单个日结报告
-   **参数**：`res=eod`, `act=get`
-   **后端处理**：`handle_eod_get`

-   **接口**：检查日结状态
-   **参数**：`res=eod`, `act=check_status`
-   **后端处理**：`handle_check_eod_status`
-   **说明**：(以上 `eod` 接口实现在 `pos_registry_ops_eod.php` 中, (待确认))

---

## 模块：POS 扩展 - 次卡 (pos_registry_ext_pass.php)

> **说明**：此文件中的定义会**覆盖** `pos_registry.php` 中的同名 `res` 和 `act`。

### 资源 (Resource): `pass` (次卡)
-   **接口**：核销次卡 (P3 核心事务)
-   **参数**：`res=pass`, `act=redeem`
-   **后端处理**：`handle_pass_redeem`
-   **说明**：
    -   此实现**覆盖**了主注册表中的定义。
    -   这是 P3 核心核销事务，包含复杂的业务逻辑（计价、锁、事务）。
    -   依赖 `pos_pass_helper.php` 和 `pos_repo_ext_pass.php`。

---

## 模块：KDS（后厨） (kds_registry.php)

> **说明**：KDS 接口使用独立的网关 `html/pos/kds/api/kds_api_gateway.php`。

### 资源 (Resource): `print` (打印)
-   **接口**：获取 KDS 用的打印模板
-   **参数**：`res=print`, `act=get_templates`
-   **后端处理**：`handle_print_get_templates`
-   **说明**：从 POS 复制而来，供 KDS 独立使用。

### 资源 (Resource): `sop` (制茶助手)
-   **接口**：获取 SOP
-   **参数**：`res=sop`, `act=get`
-   **后端处理**：`handle_kds_sop_get`
-   **说明**：解析 P-A-M-T 编码并返回动态或基础配方。

### 资源 (Resource): `expiry` (效期管理)
-   **接口**：记录效期（开封）
-   **参数**：`res=expiry`, `act=record`
-   **后端处理**：`handle_kds_expiry_record`

-   **接口**：获取当前效期内项目
-   **参数**：`res=expiry`, `act=get_items`
-   **后端处理**：`handle_kds_get_expiry_items`

-   **接口**：更新效期项目状态（核销/丢弃）
-   **参数**：`res=expiry`, `act=update_status`
-   **后端处理**：`handle_kds_update_expiry_status`

### 资源 (Resource): `prep` (备料)
-   **接口**：获取可备料物料列表
-   **参数**：`res=prep`, `act=get_materials`
-   **后端处理**：`handle_kds_get_preppable`