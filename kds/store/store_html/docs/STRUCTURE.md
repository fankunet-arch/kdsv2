# 系统结构概览（STRUCTURE）

## 根目录
- 项目根目录：`store_html`（基于 `pos_backend/core/config.php` 中 `POS_ROOT_PATH` 推断）
- 主要子目录说明：
  - `pos_backend`：POS 系统的后端 PHP 核心逻辑
  - `html/pos`：POS 系统的面向前端的入口（PHP 页面和 API 网关）
  - `html/pos/kds`：KDS（后厨显示系统）的前端入口和 API 网关
  - `kds_backend`：（推测）KDS 系统的后端 PHP 核心逻辑（基于 KDS 网关的 `require` 路径推断）

## 业务模块一览

### 1. 模块：POS 收银（点餐台）
- 目录路径：
  - 前端（入口）：`store_html/html/pos/index.php`
  - 后端（API 网关）：`store_html/html/pos/api/pos_api_gateway.php`
  - 后端（核心配置）：`store_html/pos_backend/core/config.php`
- 功能概述：
  - 提供点餐、收银、购物车、会员管理、交接班、日结、交易查询、挂起单等功能（基于 `index.php` 的 HTML 结构推断）
- 典型入口：
  - 前端页面：`index.php`
  - 后端 API 网关：`pos_api_gateway.php`
  - 前端 JS（主入口）：`assets/js/main.js`（在 `index.php` 底部引用）
- 涉及主要表：
  - (待确认)

### 2. 模块：KDS（后厨显示系统）
- 目录路径：
  - 前端（入口）：`store_html/html/pos/kds/index.php`
  - 后端（API 网关）：`store_html/html/pos/kds/api/kds_api_gateway.php`
  - 后端（核心配置）：（推测）`store_html/kds/core/config.php`
- 功能概述：
  - 用于后厨显示订单和SOP（制茶助手）
- 典型入口：
  - 前端页面：`index.php?page=sop`
  - 后端 API 网关：`kds_api_gateway.php`
  - 前端 JS（SOP页）：（推测）`kds_sop.js`（在 `kds/index.php` 中引用）
- 涉及主要表：
  - (待确认)