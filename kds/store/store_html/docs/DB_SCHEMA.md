# 数据库结构概览（DB_SCHEMA）

> **[!!] 警告：** 本文件内容**极不完整**。
>
> 1.  仅基于 `pos_backend/core/config.php` 提供了连接信息。
> 2.  **未**提供任何 SQL 结构 (Schema) 文件，因此所有表结构、字段、状态值均**未知**。
> 3.  下方的“核心表一览”是基于 `kds_registry.php` 等文件中的 SQL 查询语句反向推测，**并非**完整列表。

## 0. 数据库连接信息
-   **来源文件**：`pos_backend/core/config.php`
-   **类型**：MySQL (PDO)
-   **主机**：`mhdlmskvtmwsnt5z.mysql.db`
-   **库名**：`mhdlmskvtmwsnt5z`
-   **用户**：`mhdlmskvtmwsnt5z`
-   **字符集**：`utf8mb4`
-   **连接时区**：UTC (`+00:00`)

## 1. 核心业务表一览 (推测)

| 表名 (基于代码推测) | 简要用途 (推测) | 来源文件（瞥见） |
| :--- | :--- | :--- |
| `pos_settings` | 存储 POS 全局设置 | `pos_registry_ext_pass.php` |
| `pos_print_templates` | 存储打印模板 | `kds_registry.php` |
| `kds_materials` | KDS 物料 | `kds_registry.php` |
| `kds_material_translations` | KDS 物料多语言翻译 | `kds_registry.php` |
| `kds_material_expiries` | KDS 物料效期记录 | `kds_registry.php` |
| `kds_cups` | KDS 杯型定义 | `kds_registry.php` |
| `kds_ice_options` | KDS 冰量定义 | `kds_registry.php` |
| `kds_sweetness_options` | KDS 甜度定义 | `kds_registry.php` |
| `(其它所有业务表)` | (待确认) | (待确认) |

## 2. 表详情

### 表：(全部待确认)
-   **用途**：(待确认)
-   **关键字段**：(待确认 - 缺少 Schema 文件)
    -   `id`：(待确认)
    -   `status`：(待确认)
-   **关联关系**：(待确认)
-   **状态字段说明**：(待确认 - 缺少 Schema 或 Model 文件)
    -   `status`：
        -   `0`：(待确认)
        -   `10`：(待确认)