# All Pass P3 展示流程 Runbook

## 目的

這份文件用於期末展示前最後排練。重點不是新增功能，而是固定一條穩定、清楚、可驗收的展示路線，讓老師可以看到前台購物、後台管理、安全防線與售後流程都已串起來。

測試資料目前先保留，包含 `codex_*` 測試會員、後台管理員、測試訂單、退貨、付款、優惠券、客服與供應資料。

## 展示帳號

### 前台會員

| 角色 | Email | 密碼 | 展示用途 |
|---|---|---|---|
| 一般會員 | `codex.normal@test.local` | `CodexTest01` | 一般下單、優惠券、評論、退貨 |
| VIP | `codex.vip@test.local` | `CodexTest01` | VIP 價格展示 |
| VVIP | `codex.vvip@test.local` | `CodexTest01` | VVIP 價格展示 |
| 停權會員 | `codex.suspended@test.local` | `CodexTest01` | 登入阻擋展示 |

### 後台管理員

| 角色 | 帳號 | 密碼 | 展示用途 |
|---|---|---|---|
| 超級管理員 | `codex_super` | `CodexSuper01` | 訂單、商品、會員、系統與退貨管理 |
| 客服 | `codex_cs` | `CodexCs01` | 客服權限與 ticket 回覆 |
| 供應商 | `codex_vendor` | `CodexVendor01` | 供應商供貨流程 |
| 停用管理員 | `codex_disabled_admin` | `CodexDisabled01` | 後台停用帳號阻擋 |

## P3 測試資料提示

- 商品 #5 `旅行箱的旅行箱` / variant #6 用於一般、VIP、VVIP、優惠券、評論與退貨展示。
- 商品 #4 `普通箱` / variant #5 已指派給 `codex_vendor` 對應的 supplier #2，用於供應商供貨展示。
- P3 驗收產生的主要資料：訂單 #15-#18、退貨 #5、評論 #4、供應請求 #3、供應紀錄 #5。

## 推薦展示順序

### 1. 前台商品與會員價格

1. 開啟 `http://localhost/Suitcase/homepage/index.php`。
2. 搜尋或進入新品/商品列表。
3. 打開商品詳情，展示圖片、規格、庫存、價格、收藏與評論區。
4. 分別用一般會員、VIP、VVIP 帳號查看同一商品價格。

預期：

- 一般會員看原價或特價。
- VIP/VVIP 看到會員價。
- 未登入或未購買時評論區會給清楚提示。

### 2. 購物車、優惠券與結帳

1. 使用一般會員登入。
2. 選擇有庫存規格，加入購物車。
3. 在購物車調整數量，確認前台不顯示內部 SKU。
4. 前往結帳，套用可用優惠券。
5. 送出訂單。

預期：

- 伺服器端會檢查庫存。
- 訂單會建立 `orders`、`order_items`、`payment_transactions`。
- 使用優惠券時會寫入 `orders.coupon_id`、`orders.discount_amount`，並扣減券數。

### 3. 後台訂單與退貨管理

1. 使用 `codex_super` 登入後台。
2. 進入 dashboard，確認「待審核退貨」卡片。
3. 進入訂單管理，展示退貨 badge、退貨篩選與訂單列表。
4. 點「查看詳情」，展示右側訂單抽屜。
5. 點抽屜外側或「關閉詳情」，確認遮罩正常關閉。
6. 打開有退貨申請的訂單，展示退貨審核區。

預期：

- 後台不用打開詳情也能看出哪筆訂單有退貨。
- 抽屜不會卡住畫面或遮罩。
- 取消訂單不硬刪，會改 `CANCELLED` 並回補庫存。

### 4. 評論與退貨售後流程

1. 前台會員進入已送達或已完成訂單詳情。
2. 送出退貨申請，確認前台中文狀態。
3. 後台訂單管理篩選待審核退貨。
4. 後台將退貨改為核准、拒絕或已退款。
5. 前台刷新訂單詳情確認狀態同步。

預期：

- 未達狀態的訂單不能申請退貨。
- 已有退貨申請時不能重複申請。
- `REFUNDED` 不應重複建立退款交易。

### 5. 客服與商品 QA

1. 前台會員從商品或客服入口送出問題。
2. 使用 `codex_cs` 登入後台。
3. 進入客服管理回覆 ticket。
4. 展示空 ticket、無訊息、FAQ 狀態都不會空白。

預期：

- 客服帳號只可操作客服相關頁與 action。
- 點開 ticket 不會自動變已回覆；實際回覆後才更新狀態。

### 6. 供應商與庫存管理

1. 使用 `codex_super` 在請求供貨頁建立/查看商品 #4、variant #5 的供應請求。
2. 使用 `codex_vendor` 進入供應商品頁。
3. 展示供應請求中文狀態與可供應數量。
4. 送出供貨後，由 `codex_super` 在供應表單頁完成入庫。
5. 到系統頁查看庫存異動紀錄。

預期：

- 供應商只能操作自己的供應商品。
- 入庫後庫存增加。
- 系統頁有庫存異動 log 與友善空狀態。

### 7. 安全邊界展示

1. 未登入直接進會員中心、後台、結帳等敏感頁。
2. 使用停權會員登入。
3. 使用停用管理員登入。
4. 對後台 action 省略 CSRF token。
5. 客服/供應商直接 POST 非授權 action。

預期：

- 未登入會被導回登入。
- 停用/停權帳號不能登入。
- CSRF 缺失會被擋。
- 非授權角色不能修改資料。
- 錯誤訊息不暴露資料庫細節。

## 展示前檢查命令

```powershell
$php='C:\xampp\php\php.exe'
$files=Get-ChildItem -Path . -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' }
foreach ($file in $files) { & $php -l $file.FullName }

C:\xampp\php\php.exe db_setup_and_sync.php

$node='C:\Users\ianfu\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
& $node --check js\product_detail.js
& $node --check js\products.js
```

## 備援說法

- 金流為模擬付款紀錄，因期末專案不串正式金流。
- 忘記密碼展示用 token 連結，未串正式寄信服務。
- 測試資料保留是為了展示完整流程，展示後再決定是否清理。
- 若某筆 demo 商品庫存不足，先切換到另一個有庫存規格，不直接改資料庫。
