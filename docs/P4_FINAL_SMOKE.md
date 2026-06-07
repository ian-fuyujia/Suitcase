# All Pass P4 最終展示前 Smoke 紀錄

## 範圍

本輪使用 in-app Browser 驗證 localhost 頁面在桌面與手機尺寸的實際呈現。重點是展示前封版檢查：主要內容不可空白、不可出現 fatal/parse error、不可有整頁水平 overflow、不可有 console error，且訂單抽屜不能卡住遮罩。

## 檢查尺寸

| 尺寸 | Viewport |
|---|---|
| Desktop | 1365 x 768 |
| Mobile | 390 x 844 |

## 前台 Browser Smoke

| 頁面 | Desktop | Mobile | 備註 |
|---|---|---|---|
| 首頁 `homepage/index.php` | PASS | PASS | 無 fatal、無 console error、無整頁 overflow |
| 新品列表 `homepage/new_in.php` | PASS | PASS | 無 fatal、無 console error、無整頁 overflow |
| 搜尋 `homepage/search.php?q=箱` | PASS | PASS | 無 fatal、無 console error、無整頁 overflow |
| 商品詳情 `homepage/product_detail.php?id=5` | PASS | PASS | 商品標題、規格、評論區正常 |
| 購物車 `homepage/cart.php` | PASS | PASS | 手機表格為內部橫向捲動，不是整頁破版 |
| 結帳 `homepage/checkout.php` | PASS | PASS | 由購物車勾選商品進入；手機表格為內部橫向捲動 |
| 會員中心 `homepage/profile.php` | PASS | PASS | 訂單清單正常；手機表格為內部橫向捲動 |
| 訂單明細 `homepage/order_detail.php` | PASS | PASS | 退貨狀態與商品明細正常；手機表格為內部橫向捲動 |

## 後台 Browser Smoke

| 頁面 | Desktop | Mobile | 備註 |
|---|---|---|---|
| Dashboard | PASS | PASS | 無 fatal、無 console error、無整頁 overflow |
| 商品管理 | PASS | PASS | 手機表格為內部橫向捲動 |
| 分類管理 | PASS | PASS | 手機表格/分類卡可讀，未造成整頁 overflow |
| 訂單管理 | PASS | PASS | 退貨 badge、篩選與列表正常 |
| 優惠卷管理 | PASS | PASS | 手機表格為內部橫向捲動 |
| 會員管理 | PASS | PASS | 手機表格為內部橫向捲動 |
| 客服管理 | PASS | PASS | ticket 與 FAQ 區塊非空白 |
| 行銷內容管理 | PASS | PASS | 手機表格為內部橫向捲動 |
| 系統與權限管理 | PASS | PASS | 狀態卡、權限表、log 區塊正常 |
| 供應表單 | PASS | PASS | 未完成/已完成區塊正常 |
| 請求供貨 | PASS | PASS | 建立請求表單與最近紀錄正常 |

## 訂單抽屜回歸

| 情境 | 結果 | 證據 |
|---|---|---|
| Desktop 點抽屜外側 | PASS | `body.om-drawer-open` 移除，backdrop hidden，URL 移除 `order_id` |
| Mobile 點抽屜外側 | N/A | 手機抽屜寬度為 100vw，沒有外側可點 |
| Mobile 點「關閉詳情」 | PASS | `body.om-drawer-open` 移除，backdrop hidden，URL 移除 `order_id` |

## 可接受的展示狀態

- 多數資料表在手機尺寸使用內部橫向捲動，這是後台資料密集頁常見處理方式；本輪未發現整個頁面被表格撐爆。
- 首頁跑馬燈在手機有可視範圍外的動畫元素，但 body 沒有水平 overflow，屬正常跑馬燈效果。
- 本輪未新增 schema，也未修改 PHP/JS 功能程式碼。

## 最終 P4 判定

狀態：`PASS`

目前可進入展示前封版階段。建議最後只再決定是否清理測試資料，或保留測試資料直接展示完整流程。
