# P6 後台表格可讀性修正紀錄

## Summary
- 修正後台表格中 badge、按鈕、短狀態文字被擠成直排或重疊的問題。
- 優先處理使用者截圖指出的 `system` 權限管理表與 `products` 商品管理表。
- 將共用 `products.css` 加上 `filemtime` 版本參數，避免展示時瀏覽器吃到舊 CSS。

## Key Changes
- `css/products.css`
  - `.pm-btn`、`.pm-badge` 改為 `inline-flex` 並維持 `white-space: nowrap`。
  - `.pm-card`、`.pm-table-wrap` 加入 `max-width: 100%`、`min-width: 0`、`box-sizing: border-box`，避免寬表撐出整頁。
  - 商品表新增固定欄寬與 `pm-nowrap`、`pm-text-wrap` 輔助樣式，讓價格、庫存、狀態、操作按鈕保持可讀。
- `backend/products/list.php`
  - 商品列表套用 `pm-product-table`。
  - 文字欄位與狀態欄位分別套用換行/不換行策略。
- `backend/system.php`
  - 權限表加入 `sys-table-wrap`，手機與窄版桌機由表格內部橫向捲動。
  - 管理員群組、安全狀態、儲存按鈕維持單行可讀。
- 後台引用 `products.css` 的頁面加入版本參數：
  - `backend/products.php`
  - `backend/orders.php`
  - `backend/members.php`
  - `backend/coupon.php`
  - `backend/marketing.php`
  - `backend/request_supply.php`
  - `backend/supplier_supplies.php`

## Browser Smoke
使用 in-app Browser 驗證桌機 `1470x900` 與手機 `390x844`。

| Page | Desktop | Mobile | Notes |
|---|---|---|---|
| products | PASS | PASS | 無直排擠字；桌機無頁面級水平 overflow；手機表格內部橫向捲動 |
| system | PASS | PASS | 權限表 badge、select、儲存按鈕可讀 |
| orders | PASS | PASS | 無直排擠字；抽屜相關樣式未受影響 |
| coupon | PASS | PASS | 無直排擠字 |
| dashboard | N/A | PASS | 手機無頁面級水平 overflow |
| categories | N/A | PASS | 手機無頁面級水平 overflow |
| members | N/A | PASS | 手機無頁面級水平 overflow |
| customer_service | N/A | PASS | 手機無頁面級水平 overflow |
| marketing | N/A | PASS | 手機無頁面級水平 overflow |

## Automated Checks
- 全專案 PHP lint：PASS，68 files。
- `git diff --check`：PASS，僅 Git 顯示 CRLF 提示。
- `js/product_detail.js` syntax check：PASS，使用 Codex bundled Node。
- `js/products.js` syntax check：PASS，使用 Codex bundled Node。

## Uncertain / Follow-up
- 本輪沒有修改 DB schema，也沒有重跑 `db_setup_and_sync.php`。
- Windows PATH 上的 `node.exe` 仍回報 Access denied；本輪已改用 Codex bundled Node 完成 JS syntax check。
- P6 只修展示與操作可讀性，不調整商品/訂單/退貨業務邏輯。

## Commit Message
修正後台表格狀態文字擠壓與 CSS 快取問題
