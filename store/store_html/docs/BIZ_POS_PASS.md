# 业务流程：POS 次卡 (BIZ_POS_PASS)

## 1. 概述
- **模块用途**：处理 POS "Seasons Pass" (次卡) 的购买 (P0) 和核销 (P3) 两个关键业务。
- **涉及主要表** (基于 `pos_registry_ext_pass.php` 推测)：
  - `member_pass` (推测：通过 `get_member_pass_for_update` 锁定)
  - `(Invoice/Transaction 表)` (推测：通过 `invoice_uuid` 幂等键检查)
  - `pos_settings` (用于 `global_free_addon_limit`)
- **涉及主要接口**：
  - `res=pass, act=purchase` (P0 售卡)
  - `res=pass, act=redeem` (P3 核销，核心事务)

## 2. 核心流程：P3 次卡核销

此流程是次卡业务的核心，定义在 `pos_registry_ext_pass.php` 的 `handle_pass_redeem` 函数中，是一个高度事务性的操作。

1.  **前置检查 (Guards)**
    -   (1) **班次检查**: 必须在激活的班次 (Shift) 内 (`ensure_active_shift_or_fail`)。
    -   (2) **载荷检查**: 必须提供 `cart`, `member_id`, `pass_id` (要核销的卡), `idempotency_key` (幂等键)。
2.  **P2 服务端计价 (Allocation)**
    -   (1) **加载依赖**:
        -   加载 `store_config` (包含 `global_free_addon_limit`)。
        -   加载 `addon_defs` (通过 `get_addons_with_tags`)。
    -   (2) **计算分配**:
        -   调用 `calculate_redeem_allocation` (在 `pos_pass_helper.php` 中定义)。
        -   此函数计算购物车中哪些是 "免费" (次卡覆盖)，哪些是 "额外" (需支付)。
        -   返回 `alloc` 对象，其中包含 `extra_total` (额外应付金额)。
3.  **支付校验**
    -   (1) 解析 `payment` 载荷 (通过 `extract_payment_totals`)。
    -   (2) 校验：前端支付的总额 `sumPaid` 必须 `>` 或 `=` `alloc['extra_total']`。
4.  **P3/P4 核心事务 (`PDO->beginTransaction()`)**
    -   (1) **锁定卡片**:
        -   调用 `get_member_pass_for_update`。
        -   在数据库层面锁定 `member_pass` 表中的对应行，防止并发核销。
    -   (2) **业务校验**:
        -   调用 `validate_redeem_limits` (在 `pos_pass_helper.php` 中定义)。
        -   (推测) 检查卡片剩余次数、适用商品等。
    -   (3) **写入记录 (P3/P4)**:
        -   调用 `create_redeem_records` (在 `pos_pass_helper.php` 中定义)。
        -   (推测) 这是核心写入步骤，包含：
            -   扣减 `member_pass` 次数。
            -   创建 `invoice` (发票) 记录 (使用 `idempotency_key`)。
            -   创建 `transaction` (交易) 记录。
            -   (P4) 生成打印任务 (TP / VR 票号)。
    -   (4) **幂等冲突**: 如果 `create_redeem_records` 因 `invoice_uuid` 唯一键冲突而失败 (PDOException '23000')，事务回滚 (`rollBack()`) 并返回 409 冲突。
    -   (5) **其他失败**: 任何其他失败 (如 `validate_redeem_limits` 失败) 都会导致 `rollBack()` 和 500 错误。
5.  **提交 (Commit)**
    -   如果 P3/P4 写入成功，`PDO->commit()` 提交事务。
6.  **返回**
    -   `json_ok`，并附带 `create_redeem_records` 返回的 P3/P4 完整数据（票号、打印任务等）。