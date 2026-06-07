# All Pass P3 測試結果紀錄

## 測試狀態說明

- `PASS`: 已由工具、HTTP 流程或 DB 證據驗證通過。
- `PENDING_MANUAL`: 需要展示前人工照流程再操作一次。
- `BLOCKED`: 目前無法驗證，需補資料或處理環境問題。
- `UNCERTAIN`: 證據不足，不可當作通過。

## 本輪自動/半自動檢查

| 項目 | 狀態 | 證據 |
|---|---|---|
| 全專案 PHP lint | PASS | `C:\xampp\php\php.exe -l` 掃描 68 個 PHP 檔案通過 |
| JS syntax check | PASS | `node --check js/product_detail.js`、`node --check js/products.js` 通過 |
| `db_setup_and_sync.php` 可重複執行 | PASS | 執行成功，輸出 `執行版本：v4.1` 與資料庫結構已同步完成 |
| POST form CSRF 靜態掃描 | PASS | 掃描 `backend`、`homepage` 共 64 個 PHP 檔案，未發現缺 CSRF 的 POST form |
| 後台主要頁 HTTP smoke | PASS | `dashboard/products/categories/orders/coupon/members/customer_service/marketing/system/supplier_supplies` 全部 200、非空白、無 fatal/parse error |
| 前台主要頁 HTTP smoke | PASS | `index/search/new_in/promotions/login/register/forgot_password/cart/profile` 全部 200、非空白、無 fatal/parse error，且前台購物車未顯示 SKU |

## 手測主線

| 流程 | 狀態 | 測試帳號/資料 | 結果紀錄 |
|---|---|---|---|
| 一般會員完整下單 | PASS | `codex.normal@test.local` | 訂單 #15 建立成功，付款 `SUCCESS`，`order_items.locked_price=6600.00`，庫存先扣 1 |
| VIP 價格與結帳 | PASS | `codex.vip@test.local` | 訂單 #16 建立成功，付款 `SUCCESS`，`locked_price=6000.00`，總額 `6000.00` |
| VVIP 價格與結帳 | PASS | `codex.vvip@test.local` | 訂單 #17 建立成功，付款 `SUCCESS`，`locked_price=6000.00`，總額 `6000.00` |
| 使用優惠券結帳 | PASS | 一般會員 + coupon #4 | 訂單 #18 建立成功，`coupon_id=4`，折扣 `660.00`，總額 `5940.00`，會員券數 `2 -> 1` |
| 超庫存防線 | PASS | 商品 #5 / variant #6 | 超庫存加入購物車、更新購物車、送出結帳皆被伺服器端阻擋；訂單數與庫存未變 |
| 後台更新訂單狀態 | PASS | `codex_super` | 訂單 #15 由 `PENDING` 更新為 `PROCESSING` |
| 後台取消訂單回補庫存 | PASS | `codex_super` | 訂單 #15 改為 `CANCELLED`，`inventory_deducted=0`，variant #6 庫存 `164 -> 165` |
| 忘記密碼 token 過期 | PASS | 測試會員 user #9 | 手動建立過期 token，重設被擋並顯示「重設連結無效或已過期」 |
| 忘記密碼 token 重複使用 | PASS | 測試會員 user #9 | 有效 token 第一次可把密碼重設為同一組 `CodexTest01`，第二次重用被擋，`used_at` 已寫入 |
| 停權會員登入阻擋 | PASS | `codex.suspended@test.local` | 登入後無法進入會員中心，仍被導回登入/停用提示 |
| 停用管理員登入阻擋 | PASS | `codex_disabled_admin` | 登入後無法進入後台，仍被導回 `admin_login.php`/停用提示 |
| 商品評論資格 | PASS | 未登入 / VIP 未完成訂單 / 一般會員已送達訂單 | 未登入顯示登入提示；VIP 訂單未送達不可評論；一般會員訂單 #18 可新增 review #4 |
| 退貨申請與後台審核 | PASS | 一般會員 + `codex_super` | 訂單 #18 走到 `DELIVERED` 後送退貨，return #5 由 `PENDING` 後台核准為 `APPROVED` |
| 客服回覆與 FAQ | PASS | `codex_cs` | ticket message 數 `3 -> 4`，ticket #3 維持 `ANSWERED`；FAQ 數 `1 -> 2` |
| 供應商供貨與入庫 | PASS | `codex_vendor` + `codex_super` | request #3 建立，vendor 建 supply #5，後台完成入庫，variant #5 庫存 `7 -> 8`，inventory log `5 -> 6` |

## 安全與權限補充驗證

| 項目 | 狀態 | 證據 |
|---|---|---|
| 後台缺 CSRF 不可修改 | PASS | 省略 CSRF 直接 POST 更新訂單 #4，狀態未變 |
| 客服越權改訂單被擋 | PASS | `codex_cs` 直接 POST `update_order_status`，訂單 #16 狀態未變 |
| 結帳錯誤不暴露內部細節 | PASS | 超庫存結帳只顯示友善錯誤，未顯示 SQL/DB exception |

## 展示資料盤點

| 資料 | 狀態 | 數量 / 說明 |
|---|---|---|
| `codex.*@test.local` 前台會員 | PASS | 4 筆：一般、VIP、停權、VVIP |
| `codex_*` 後台管理員 | PASS | 6 筆，含超級管理員、客服、供應商、停用管理員 |
| 訂單資料 | PASS | 17 筆 |
| 付款紀錄 | PASS | 16 筆 |
| 退貨申請 | PASS | 5 筆 |
| 商品評論 | PASS | 3 筆 |
| 客服 ticket | PASS | 3 筆，ticket message 4 筆 |
| FAQ / 商品 QA | PASS | 2 筆 |
| 供應請求 | PASS | 3 筆 |
| 供應紀錄 | PASS | 5 筆 |
| 庫存異動紀錄 | PASS | 6 筆 |

## 已知保留項目

- 測試資料先保留，最後由使用者決定是否清理。
- P3 為了讓 `codex_vendor` 可展示供應商流程，已將商品 #4 `普通箱` 指派給 supplier #2。
- 不串正式金流、不寄正式 email、不正式部署。
- 忘記密碼展示仍是本機 demo token，不是正式寄信服務。
- `docs/CURRENT_HANDOFF.md` 是本機接力文件，不納入 commit。

## 最終展示判定

目前狀態：`PASS`

P3 核心展示路線、基線檢查、高風險安全邊界、前台下單、優惠券、庫存、後台取消、退貨、評論、客服與供應商流程均已完成一次驗收。
