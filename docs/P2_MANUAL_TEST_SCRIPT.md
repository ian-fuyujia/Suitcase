# All Pass P2 手測驗收腳本

## 測試帳號與保留資料

測試資料目前先保留，最後再決定是否清理。

前台會員：

| 角色 | Email | 密碼 | 會員等級 | 預期 |
|---|---|---|---|---|
| 一般會員 | `codex.normal@test.local` | `CodexTest01` | 一般 | 可登入、下單、領券、評論符合資格商品 |
| VIP | `codex.vip@test.local` | `CodexTest01` | VIP | 顯示 VIP 價格並可結帳 |
| VVIP | `codex.vvip@test.local` | `CodexTest01` | VVIP | 顯示 VVIP/會員價並可結帳 |
| 停權會員 | `codex.suspended@test.local` | `CodexTest01` | 一般 | 不可登入 |

後台管理員：

| 角色 | 帳號 | 密碼 | 預期 |
|---|---|---|---|
| 超級管理員 | `codex_super` | `CodexSuper01` | 可操作所有後台管理頁 |
| 客服 | `codex_cs` | `CodexCs01` | 只能進客服相關頁與 action |
| 供應商 | `codex_vendor` | `CodexVendor01` | 只能進供應商可用頁與供貨 action |
| 停用管理員 | `codex_disabled_admin` | `CodexDisabled01` | 不可登入後台 |

## 前台流程

### 1. 一般會員完整下單

1. 登入 `codex.normal@test.local`。
2. 進入商品詳情，選擇有庫存的規格。
3. 加入購物車，進入購物車更新數量。
4. 前往結帳，確認收件資料、付款資訊與金額。
5. 送出訂單。

預期：

- 訂單建立成功並進入成功頁。
- 庫存扣減。
- `orders` 有新訂單，`order_items` 有明細。
- `payment_transactions` 有對應付款紀錄。

DB 檢查建議：

```sql
SELECT order_id, order_number, user_id, status, total_amount, coupon_id, discount_amount
FROM orders
WHERE user_id = 9
ORDER BY order_id DESC
LIMIT 3;

SELECT * FROM payment_transactions
ORDER BY transaction_id DESC
LIMIT 3;
```

### 2. VIP / VVIP 價格與結帳

1. 分別登入 `codex.vip@test.local`、`codex.vvip@test.local`。
2. 打開同一商品詳情。
3. 確認頁面顯示會員價。
4. 直接下單或加入購物車後結帳。

預期：

- VIP/VVIP 看到的價格與結帳鎖定價格一致。
- `order_items.locked_price` 與結帳金額一致。

DB 檢查建議：

```sql
SELECT o.order_id, o.user_id, o.total_amount, oi.product_name, oi.locked_price, oi.quantity
FROM orders o
JOIN order_items oi ON oi.order_id = o.order_id
WHERE o.user_id IN (10, 12)
ORDER BY o.order_id DESC
LIMIT 10;
```

### 3. 優惠券結帳

1. 使用一般會員登入。
2. 到會員中心領取或輸入可用優惠券。
3. 結帳頁選用優惠券。
4. 送出訂單。

預期：

- 結帳頁顯示折扣。
- 訂單寫入 `orders.coupon_id` 與 `orders.discount_amount`。
- `coupon_distributions.quantity` 正確扣減。

DB 檢查建議：

```sql
SELECT order_id, coupon_id, discount_amount, total_amount
FROM orders
WHERE user_id = 9
ORDER BY order_id DESC
LIMIT 5;

SELECT coupon_id, user_id, quantity
FROM coupon_distributions
WHERE user_id = 9
ORDER BY distribution_id DESC
LIMIT 10;
```

### 4. 超庫存防線

1. 商品詳情輸入超過庫存的數量並加入購物車。
2. 在購物車把數量改成超過庫存。
3. 嘗試送出結帳。

預期：

- 商品詳情、購物車更新、結帳送出都會被伺服器端阻擋或修正。
- 不會建立超賣訂單。
- 庫存不會變成負數。

### 5. 忘記密碼 token

1. 對測試會員發起忘記密碼。
2. 使用過期 token 開啟重設頁。
3. 使用已使用 token 再次送出。

預期：

- 過期 token 不顯示有效重設表單。
- 已使用 token 不能再次改密碼。
- 錯誤訊息不暴露資料庫細節。

### 6. 商品評論資格

1. 未登入打開商品詳情，確認評論區提示需登入。
2. 登入沒有已送達/已完成訂單的會員，確認不能評論。
3. 登入有資格的會員，送出評論。
4. 再次送出評論。

預期：

- 未登入、未達資格、可評論狀態都有清楚提示。
- 符合資格時可建立評論。
- 重複送出會更新原評論，不會產生重複評論。

### 7. 退貨申請

1. 登入一般會員。
2. 進入已送達或已完成訂單詳情。
3. 填寫退貨原因並送出。

預期：

- 未達狀態的訂單不能申請退貨。
- 已送達/已完成可送出。
- 已有處理中退貨時不能重複申請。
- 前台顯示中文狀態：待客服審核、已核准退貨、退貨未通過、已完成退款。

## 後台流程

### 8. 訂單狀態與取消回補

1. 使用 `codex_super` 登入後台。
2. 進入訂單管理，選取測試訂單。
3. 更新物流資訊與訂單狀態。
4. 將訂單取消。

預期：

- 點擊「查看詳情」會開啟右側訂單詳情抽屜；關閉後回到原本列表與篩選條件。
- 訂單狀態更新成功。
- 取消訂單不硬刪，狀態改為 `CANCELLED`。
- 已扣庫存訂單取消後會回補庫存。
- 若訂單使用優惠券，取消時券數量正確回補。

### 9. 退貨後台審核

1. 使用超級管理員進入訂單管理。
2. 確認 dashboard 或訂單頁頂部會顯示「待審核退貨」提示。
3. 在訂單管理使用「有退貨」或「待審核」篩選。
4. 確認列表列出退貨 badge，且待審核退貨列有明顯標示。
5. 選擇有退貨申請的訂單。
6. 將退貨狀態改為 `APPROVED`、`REJECTED` 或 `REFUNDED`。
7. 對同一筆 `REFUNDED` 重複送出一次。

預期：

- 後台不用先打開訂單詳情，也能從列表看出哪筆訂單有退貨。
- 退貨篩選可正確縮小列表。
- 後台顯示中文退貨狀態與下一步提示。
- `REFUNDED` 第一次建立退款交易。
- 重複送出 `REFUNDED` 不建立第二筆退款交易。

DB 檢查建議：

```sql
SELECT order_id, payment_type, amount, status, created_at
FROM payment_transactions
WHERE payment_type = 'REFUND'
ORDER BY transaction_id DESC
LIMIT 10;
```

### 10. 客服與商品 QA

1. 前台會員在商品頁或客服入口送出問題。
2. 使用 `codex_cs` 登入後台。
3. 進入客服頁回覆 ticket。
4. 將使用者問題加入 FAQ。

預期：

- 客服只能看到客服頁。
- 點開 ticket 不會自動改成已回覆。
- 回覆後 ticket 才變為已回覆。
- 空 ticket、無訊息、無 FAQ 時頁面不空白。

### 11. 供應商供貨

1. 使用 `codex_vendor` 登入後台。
2. 進入供應商品頁。
3. 送出供貨數量。
4. 使用超級管理員到供應表單完成入庫。

預期：

- 供應商只能操作自己的供應商品。
- 供應請求狀態顯示中文，例如待供應、部分供應、已完成、已取消。
- 頁首可看到尚有幾筆可供應請求。
- 超級管理員完成入庫後庫存增加。
- 對應庫存異動 log 出現在系統頁。

### 12. 權限與 CSRF 邊界

1. 客服直接 POST 商品/會員/分類管理 action。
2. 供應商直接 POST 非供應商 action。
3. 任一後台 action 不帶 `csrf_token`。

預期：

- 非授權角色被導回並顯示權限不足。
- 不帶 CSRF 的 POST 被擋。
- 資料不被修改。

## 展示前自動檢查

```powershell
$php='C:\xampp\php\php.exe'
$files=Get-ChildItem -Path . -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\vendor\\' }
foreach ($file in $files) { & $php -l $file.FullName }

C:\xampp\php\php.exe db_setup_and_sync.php

$node='C:\Users\ianfu\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
& $node --check js\product_detail.js
& $node --check js\products.js
```

## 展示提醒

- 測試資料可保留到期末展示結束後再清。
- 若 demo 前需要重設測試帳號密碼，請先備份資料庫或只更新 `codex_*` 帳號。
- 展示時優先走「商品瀏覽 -> 加購 -> 結帳 -> 後台訂單 -> 退貨/評論/客服」主線，系統頁作為安全與管理完整度補充。
