# 业务流程：POS 收银 (BIZ_POS_CASHIER)

## 1. 概述
- **模块用途**：POS 点餐收银前台，处理日常的点餐、支付、会员、班次管理等（基于 `html/pos/index.php` 界面推断）。
- **涉及主要表**：
  - `(待确认 - 缺少 Schema)`
- **涉及主要接口**：
  - `res=data, act=load` (加载初始数据)
  - `res=order, act=submit` (提交订单)
  - `res=cart, act=calculate` (计算购物车)
  - `res=shift, act=start` (开始班次)
  - `res=shift, act=end` (结束班次)
  - `res=shift, act=force_start` (强制开始班次)
  - `res=member, act=find` (查找会员)
  - `res=eod, act=get_preview` (日结预览)

## 2. 核心流程：标准订单（点餐到支付）

1.  **加载界面**
    -   前端入口 `html/pos/index.php`。
    -   调用 `res=data, act=load` (由 `handle_data_load` 处理) 获取商品、分类等初始数据。
2.  **点餐 & 购物车**
    -   用户在界面 (`product_grid`) 选择商品。
    -   商品进入 `cartOffcanvas` 购物车侧边栏。
    -   (可选) 购物车修改时，可能会调用 `res=cart, act=calculate` 重新计价。
3.  **(可选) 关联会员**
    -   在 `member_section` 中输入手机号 (`member_search_phone`)。
    -   点击 "查找" (`btn_find_member`)。
    -   调用 `res=member, act=find` (由 `handle_member_find` 处理) 获取会员信息和可用积分。
4.  **(可选) 应用折扣**
    -   输入优惠码 (`coupon_code_input`)。
    -   使用积分 (`points_to_redeem_input`)。
5.  **结账**
    -   点击 "去结账" (`btn_cart_checkout`)，打开 `paymentModal` 结账模态框。
    -   选择支付方式 (现金 `Cash`, 刷卡 `Card` 等) 并输入金额。
    -   点击 "确认收款" (`btn_confirm_payment`)。
    -   (前端验证后) 调用 `res=order, act=submit` (由 `handle_order_submit` 处理) 提交最终订单。
6.  **完成**
    -   后端处理成功后，前端显示 `orderSuccessModal`，展示票号 (`success_invoice_number`) 和二维码 (`success_qr_content`)。

## 3. 核心流程：班次管理 (Shift)

**班次 (Shift)** 是 POS 操作的核心前置条件，许多操作（如次卡核销）会检查班次状态。

1.  **检查班次状态**
    -   (推测) 页面加载时调用 `res=shift, act=status` (由 `handle_shift_status` 处理)。
2.  **(场景 A) 正常开始班次**
    -   如果 `status` 结果为无班次，弹出 `startShiftModal`。
    -   用户输入初始备用金 (`starting_float`)。
    -   调用 `res=shift, act=start` (由 `handle_shift_start` 处理)。
3.  **(场景 B) 发现“幽灵”班次**
    -   如果 `status` 结果为有未结束的班次 (Ghost Shift)，弹出 `forceStartShiftModal` (基于 `index.php` 的 HTML 和注释推断)。
    -   用户输入 **自己** 的初始备用金 (`force_starting_float`)。
    -   调用 `res=shift, act=force_start` (由 `handle_shift_force_start` 处理)，强制结束上一班次并开始新班次。
4.  **结束班次**
    -   用户在功能面板 (`opsOffcanvas`) 点击 "交接班" (`btn_open_shift_end`)。
    -   弹出 `endShiftModal`，显示系统统计 (`end_shift_summary_body`)。
    -   用户输入清点的现金 (`counted_cash`)。
    -   点击 "确认交班" (`form="end_shift_form"`)。
    -   调用 `res=shift, act=end` (由 `handle_shift_end` 处理)。