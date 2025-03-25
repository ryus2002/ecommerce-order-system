## 系統簡介 (System Introduction)
這是專為高流量電商平台設計的分散式訂單處理系統，採用先進的架構設計和技術方案，能夠有效處理大量並發訂單請求。系統通過 CQRS 模式、分散式鎖、資料庫分片等技術實現了高可用性、高併發處理能力和資料一致性保證。

## 核心特性 (Key Features)

### 1. CQRS 模式 (Command Query Responsibility Segregation)
- 讀寫操作分離，提高系統擴展性
- 命令處理器處理寫操作，查詢處理器處理讀操作
- 優化讀寫工作負載的獨立擴展

### 2. 分散式鎖機制 (Distributed Locking)
- 使用 Redis 實現分散式鎖
- 保證在分散式環境中處理訂單的一致性
- 避免庫存超賣問題

### 3. 資料庫分片 (Database Sharding)
- 根據訂單 ID 進行水平分片
- 實現資料庫負載均衡
- 支援系統容量的水平擴展

### 4. 高併發處理 (High Concurrency Processing)
- 整合 Swoole 提供高性能非阻塞 I/O
- Redis 連接池優化資源使用
- 異步任務處理提高系統吞吐量

### 5. 樂觀鎖 (Optimistic Locking)
- 應用於庫存更新環節
- 使用版本控制機制處理併發衝突
- 減少鎖競爭提升系統效能

### 6. 事件驅動架構 (Event-Driven Architecture)
- 系統組件通過事件解耦
- 支援事件監聽和反應式處理
- 便於系統擴展和維護

### 7. 全面的測試覆蓋 (Comprehensive Testing)
- 完整的單元測試確保代碼品質
- 模擬並發測試驗證系統穩定性
- 測試覆蓋核心業務邏輯和邊緣情況

## 系統架構 (System Architecture)

系統採用分層架構，主要包含以下幾個部分：

1. **API 層**：處理外部請求，進行身份驗證和基本驗證
2. **應用層**：包含命令和查詢處理器，實現業務邏輯
3. **領域層**：核心業務模型和規則
4. **基礎設施層**：提供分散式鎖、資料庫操作等基礎服務

系統處理訂單的流程：
1. 訂單請求通過 API 層進入系統
2. 創建訂單命令由命令處理器處理
3. 使用分散式鎖確保資源獨佔
4. 檢查庫存並使用樂觀鎖更新庫存
5. 創建訂單記錄並持久化
6. 觸發訂單創建事件
7. 相關子系統對事件做出反應

## 技術棧 (Technologies)

- **框架**: Laravel 12+
- **通訊**: RESTful API
- **資料庫**: MySQL (水平分片)
- **快取**: Redis (分散式鎖, 連接池)
- **併發處理**: Swoole
- **異步任務**: Laravel Queue
- **事件處理**: Laravel Event & Listeners
- **測試**: PHPUnit
  
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
