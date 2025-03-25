# 電商訂單系統
這是一個基於 Laravel 的電商訂單系統，支持產品管理、庫存管理、訂單創建和處理等功能。系統使用 UUID 作為訂單主鍵，並支持資料庫分片以提高性能。

## 功能特點
- 產品管理：添加、編輯、刪除產品
- 庫存管理：追蹤產品庫存，支持樂觀鎖防止並發問題
- 訂單管理：創建訂單，查看訂單歷史
- 用戶認證：用戶註冊、登入和權限管理
- 資料庫分片：通過 shard_id 支持訂單資料分片存儲

## 系統要求
- PHP >= 8.1
- PHP Laravel 12
- Composer
- MySQL >= 8.0
- Docker 和 Docker Compose (推薦使用 Laradock)

## 安裝方法

### 方法一：使用 Docker 和 Laradock（推薦）
1. 克隆專案代碼
```bash
git clone https://your-repository-url/ecommerce-order-system.git
cd ecommerce-order-system
```
2. 克隆 Laradock 到專案目錄
```bash
git clone https://github.com/laradock/laradock.git
cd laradock
```
3. 複製環境配置文件
```bash
cp env-example .env
```
4. 根據需要修改 Laradock 的 .env 文件，特別是以下配置：
```
APP_CODE_PATH_HOST=../
COMPOSE_PROJECT_NAME=ecommerce-order-system
```
5. 啟動 Docker 容器
```bash
docker-compose up -d nginx mysql phpmyadmin redis workspace
```
6. 進入 workspace 容器
```bash
docker-compose exec workspace bash
```
7. 在容器內安裝專案依賴
```bash
composer install
```
8. 複製專案環境配置文件
```bash
cp .env.example .env
```
9. 生成應用密鑰
```bash
php artisan key:generate
```
10. 運行資料庫遷移和填充數據
```bash
php artisan migrate
php artisan db:seed
```

### 方法二：傳統安裝
1. 克隆專案代碼
```bash
git clone https://your-repository-url/ecommerce-order-system.git
cd ecommerce-order-system
```
2. 安裝依賴
```bash
composer install
```
3. 複製環境配置文件
```bash
cp .env.example .env
```
4. 配置資料庫連接（在 .env 文件中）
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce-order-system
DB_USERNAME=root
DB_PASSWORD=your_password
```
5. 生成應用密鑰
```bash
php artisan key:generate
```
6. 運行資料庫遷移和填充數據
```bash
php artisan migrate
php artisan db:seed
```
7. 啟動開發服務器
```bash
php artisan serve
```

## 使用方法

### API 接口

系統提供以下 API 接口：

#### 認證相關
- `POST /api/auth/register` - 用戶註冊
- `POST /api/auth/login` - 用戶登入
- `POST /api/auth/logout` - 用戶登出
- `GET /api/auth/user` - 獲取當前用戶信息

#### 產品相關
- `GET /api/products` - 獲取所有產品
- `GET /api/products/{id}` - 獲取單個產品詳情
- `POST /api/products` - 創建新產品（需要管理員權限）
- `PUT /api/products/{id}` - 更新產品信息（需要管理員權限）
- `DELETE /api/products/{id}` - 刪除產品（需要管理員權限）

#### 訂單相關
- `GET /api/orders` - 獲取當前用戶的所有訂單
- `GET /api/orders/{id}` - 獲取單個訂單詳情
- `POST /api/orders` - 創建新訂單

### 創建訂單示例

要創建新訂單，發送 POST 請求到 `/api/orders` 端點，請求體格式如下：
```json
{
"items": [
{
"product_id": 1,
"quantity": 2,
"unit_price": 7999.00
},
{
"product_id": 3,
"quantity": 1,
"unit_price": 14999.00
}
],
"total_amount": 30997.00,
"address": "台北市信義區信義路五段7號",
"payment_method": "credit_card"
}
```

### 添加測試數據

如果需要添加測試數據，可以使用以下命令：
```bash
# 在 Docker 環境中
docker exec -it laradock-workspace-1 php artisan db:seed
# 或者指定特定的 seeder
docker exec -it laradock-workspace-1 php artisan db:seed --class=ProductSeeder
docker exec -it laradock-workspace-1 php artisan db:seed --class=InventorySeeder
```
也可以通過 Tinker 手動添加數據：
```bash
# 添加產品
docker exec -it laradock-workspace-1 php artisan tinker --execute="App\Models\Product::create(['name' => '測試產品', 'description' => '這是一個測試產品', 'price' => 99.99]);"
# 添加庫存
docker exec -it laradock-workspace-1 php artisan tinker --execute="App\Models\Inventory::create(['product_id' => 1, 'quantity' => 100, 'version' => 1]);"
```

## 常見問題

### 訂單創建失敗："The selected items.0.product_id is invalid."

這個錯誤表示您嘗試使用的產品 ID 在資料庫中不存在。請確保：
1. 已經添加了產品數據
2. 使用正確的產品 ID
可以通過以下命令查看可用的產品：
```bash
docker exec -it laradock-workspace-1 php artisan tinker --execute="foreach(App\Models\Product::all() as \$p) { echo \$p->id . ': ' . \$p->name . ' - NT$' . \$p->price . PHP_EOL; }"
```

### 訂單創建失敗："Insufficient inventory for products: X"

這個錯誤表示產品庫存不足。請確保：
1. 已經為產品添加了庫存記錄
2. 庫存數量足夠滿足訂單需求
可以通過以下命令查看產品庫存：
```bash
docker exec -it laradock-workspace-1 php artisan tinker --execute="foreach(App\Models\Inventory::with('product')->get() as \$inv) { echo \$inv->product->name . ' - 庫存: ' . \$inv->quantity . PHP_EOL; }"
```

## 貢獻指南

1. Fork 本倉庫
2. 創建您的特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交您的更改 (`git commit -m '添加一些很棒的功能'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 打開一個 Pull Request

## 許可證
[MIT](LICENSE)
