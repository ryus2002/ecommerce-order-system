# Distributed E-commerce Order Processing System

A high-performance, scalable distributed order processing system built for e-commerce platforms that require handling high-volume concurrent transactions while maintaining data consistency.

## 系統簡介 (System Introduction)

本系統是一個專為高流量電商平台設計的分散式訂單處理系統，採用先進的架構設計和技術方案，能夠有效處理大量並發訂單請求。系統通過 CQRS 模式、分散式鎖、資料庫分片等技術實現了高可用性、高併發處理能力和資料一致性保證。

This system is a distributed order processing solution designed for high-traffic e-commerce platforms. It implements advanced architectural patterns and technologies to effectively handle large volumes of concurrent order requests while maintaining data consistency and system reliability.

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

- **框架**: Laravel 9+
- **通訊**: RESTful API
- **資料庫**: MySQL (水平分片)
- **快取**: Redis (分散式鎖, 連接池)
- **併發處理**: Swoole
- **異步任務**: Laravel Queue
- **事件處理**: Laravel Event & Listeners
- **測試**: PHPUnit

## 安裝與設置 (Installation & Setup)

1. 克隆儲存庫
```bash
git clone [repository-url]
cd [repository-directory]
```

2. 安裝依賴
```bash
composer install
```

3. 環境設置
```bash
cp .env.example .env
php artisan key:generate
```

4. 配置資料庫和 Redis
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

5. 運行遷移和種子
```bash
php artisan migrate
php artisan db:seed
```

6. 啟動服務器
```bash
php artisan serve
# 或使用 Swoole
php artisan octane:start
```

## API 使用說明 (API Usage)

### 創建訂單
```
POST /api/orders
```
請求體:
```json
{
  "items": [
    {
      "product_id": "product-uuid",
      "quantity": 2,
      "unit_price": 99.50
    }
  ],
  "address": "配送地址",
  "payment_method": "信用卡"
}
```

### 查詢訂單
```
GET /api/orders/{order_id}
```

## 對開發者的建議 (Recommendations for Developers)

對於新手來說，可以按照以下步驟逐步實現類似系統：

1. 先實現基本的訂單處理功能
2. 添加 CQRS 模式分離讀寫操作
3. 實現分散式鎖機制保證一致性
4. 添加樂觀鎖處理併發更新
5. 引入事件驅動架構實現組件解耦
6. 實現資料庫分片進行水平擴展
7. 整合 Swoole 提升並發處理能力
8. 完善單元測試確保系統穩定性

## 授權 (License)

[MIT License](LICENSE)