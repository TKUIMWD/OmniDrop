# OmniDrop AWS S3 整合 - 變更摘要

## 📊 代碼變更統計

| 文件 | 變更類型 | 說明 |
|------|---------|------|
| `src/db.php` | 修改 | 初始化 S3FileManager，從環境變數讀取配置 |
| `src/files.php` | 修改 (3處) | 上傳、刪除、下載邏輯改為 S3 操作 |
| `src/download.php` | 修改 | 分享下載改用 S3 Presigned URL |

---

## 🔄 核心流程變化

### 原架構 (本地存儲)
```
用戶上傳 → PHP 保存到 /storage/ → 本地磁碟
用戶下載 → PHP 讀取 /storage/ 文件 → readfile() 輸出
```

### 新架構 (AWS S3)
```
用戶上傳 → S3FileManager::uploadFile() → AWS S3 Bucket
用戶下載 → S3FileManager::getPresignedUrl() → 重定向到 S3 URL
用戶預覽 → Presigned URL iFrame → 直接從 S3 取得
刪除文件 → 同時刪除 S3 + 資料庫
```

---

## 🔐 安全性改進

✅ **Presigned URL**
- 時間限制（1小時過期）
- 無須暴露 AWS 認證金鑰
- 用戶只能存取授權的文件

✅ **S3 私有訪問**
- Bucket 設為私有
- IAM Role 限制 EC2 存取
- 沒有公開 URL

✅ **保留所有驗證邏輯**
- 密碼保護
- 下載次數限制
- 分享連結過期時間
- IDOR 防護

---

## 📋 環境變數需求

```bash
# 必需配置
DB_HOST=your-rds-endpoint.rds.amazonaws.com
MYSQL_DATABASE=omnidrop
MYSQL_USER=admin
MYSQL_PASSWORD=your_password
AWS_DEFAULT_REGION=ap-southeast-1
AWS_S3_BUCKET=omnidrop-files

# EC2 使用 IAM Role 時無需設定：
# AWS_ACCESS_KEY_ID=xxx
# AWS_SECRET_ACCESS_KEY=xxx
```

---

## 📦 Docker Compose 更新

更新 `docker-compose.yml` PHP 服務的 environment：

```yaml
services:
  app:
    build: ./php
    environment:
      - DB_HOST=db
      - MYSQL_DATABASE=omnidrop
      - MYSQL_USER=omnidrop_user
      - MYSQL_PASSWORD=your_password
      - AWS_DEFAULT_REGION=ap-southeast-1
      - AWS_S3_BUCKET=omnidrop-files
```

---

## ✨ 新增的 S3FileManager 功能

```php
// 上傳
$s3Manager->uploadFile($tmpFile, $s3Key);

// 下載
$s3Manager->downloadFile($s3Key, $localPath);

// 刪除
$s3Manager->deleteFile($s3Key);

// 列表
$files = $s3Manager->listFiles('prefix/');

// 生成分享 URL (有時間限制)
$url = $s3Manager->getPresignedUrl($s3Key, 3600); // 1小時
```

---

## 🚀 部署步驟

### 1. 本地開發 (Docker)
```bash
# 建立 Composer vendor
docker-compose up app composer install

# 啟動服務
docker-compose up -d
```

### 2. AWS 生產環境
```bash
# 1. 建立 S3 Bucket
aws s3 mb s3://omnidrop-files --region ap-southeast-1

# 2. 建立 IAM Role (給 EC2)
# 參考 AWS_INTEGRATION_GUIDE.md

# 3. 建立 RDS MySQL
# 遷移本地資料庫到 RDS

# 4. 啟動 EC2 實例
# 安裝 PHP + Apache
# 配置環境變數
# 部署代碼

# 5. 設置 ALB + VPC
# 配置 Security Groups
# 綁定 HTTPS 憑證
```

---

## 🧪 測試清單

- [ ] 在本地 Docker 中測試文件上傳
- [ ] 驗證 vendor/autoload.php 已生成
- [ ] 確認 S3FileManager 類可被引入
- [ ] 測試 Presigned URL 生成
- [ ] 測試文件刪除同時刪除 S3
- [ ] 測試分享連結下載
- [ ] 測試密碼保護的分享
- [ ] 驗證過期時間限制
- [ ] 驗證下載次數計數

---

## ⚠️ 已知限制 & 注意事項

1. **Presigned URL 預覽**
   - 某些文件類型可能無法在 iFrame 中預覽
   - 建議為不支援的格式提供下載選項

2. **容量限制**
   - PHP upload_max_filesize 預設 128MB
   - 可在 php.ini 中增加

3. **地區考量**
   - 選擇 ap-southeast-1（新加坡）以降低延遲
   - 大檔案上傳建議使用 S3 Multipart Upload

4. **成本管理**
   - S3 請求費用：每百萬請求 $0.4
   - 設置 S3 Lifecycle Policy 自動刪除過期檔案

---

## 📞 常見問題

**Q: 如何遷移現有本地檔案到 S3?**
```bash
# 遍歷 /storage/ 目錄並上傳所有文件到 S3
for file in /var/www/html/storage/*; do
  aws s3 cp "$file" s3://omnidrop-files/files/$(basename $file)
done
```

**Q: Presigned URL 過期了怎麼辦?**
> 用戶會收到錯誤，需要重新下載。建議設定 1-2 小時的過期時間。

**Q: 能否備份 S3 中的檔案?**
> 是的，使用 S3 Replication 或 Backup 服務進行跨區備份。

---

## 📚 相關文檔

- [AWS_INTEGRATION_GUIDE.md](AWS_INTEGRATION_GUIDE.md) - 詳細 AWS 設置指南
- [AWS_SDK_SETUP.md](AWS_SDK_SETUP.md) - AWS SDK 安裝說明
- [README.md](README.md) - 項目概述

---

**最後更新**: 2026-05-22  
**版本**: 1.0
