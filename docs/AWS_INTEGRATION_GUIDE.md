# AWS S3 整合設定指南

## ✅ 已完成的代碼修改

### 1. **db.php** - 已初始化 S3FileManager
- 自動引入並初始化 AWS S3 FileManager
- 從環境變數讀取 S3 Bucket 名稱

### 2. **files.php** - 已改為使用 S3
- **上傳邏輯**：檔案直接上傳到 S3 而非本地 `/storage/`
- **刪除邏輯**：刪除時同時從 S3 和資料庫移除
- **下載/預覽邏輯**：使用 S3 Presigned URL

### 3. **download.php** - 已改為從 S3 讀取
- 分享下載現在使用 S3 Presigned URL
- 保留了所有安全驗證邏輯（密碼、過期時間、下載次數限制）

### 4. **S3FileManager.php** - 已完備
- 支援上傳、下載、刪除、列表
- 支援生成 Presigned URL（用於下載和預覽）

---

## 📋 還需要的 AWS 資源和步驟

### 1️⃣ **S3 Bucket 建立**
```bash
# 使用 AWS CLI 建立
aws s3 mb s3://omnidrop-files --region ap-southeast-1

# 設定 Bucket Policy（私有存取）
aws s3api put-bucket-policy --bucket omnidrop-files \
  --policy file://bucket-policy.json
```

### 2️⃣ **IAM Role for EC2**
```bash
# 建立 Role 讓 EC2 存取 S3
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::omnidrop-files/*"
    },
    {
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::omnidrop-files"
    }
  ]
}
```

### 3️⃣ **RDS MySQL 設置**
- 建立 RDS MySQL 實例（推薦 ap-southeast-1 區域）
- 將現有 MySQL 資料庫遷移到 RDS
- 更新 `DB_HOST` 為 RDS endpoint

### 4️⃣ **EC2 實例設置**
- 建立 EC2 實例 (Amazon Linux 2 或 Ubuntu)
- 安裝 PHP 7.4+ 和 Apache
- 附加上面建立的 IAM Role
- 部署本應用程式代碼

### 5️⃣ **VPC 和 ALB 設置**
- 建立 VPC 與 Public/Private Subnets
- 建立 ALB 在 Public Subnet
- EC2 放在 Private Subnet
- 設定 Security Groups

### 6️⃣ **環境變數配置**
```bash
# 在 EC2 上設定
export DB_HOST=your-rds-endpoint.rds.amazonaws.com
export MYSQL_DATABASE=omnidrop
export MYSQL_USER=admin
export MYSQL_PASSWORD=your_password
export AWS_DEFAULT_REGION=ap-southeast-1
export AWS_S3_BUCKET=omnidrop-files

# 注意：EC2 IAM Role 會自動提供 AWS 認證，無需手動設定金鑰
```

---

## 🔐 安全性檢查清單

- [ ] S3 Bucket 設為私有（不允許公開存取）
- [ ] IAM Role 只授予必要的 S3 權限
- [ ] RDS 放在 Private Subnet，只允許 EC2 存取
- [ ] ALB 配置 HTTPS (ACM Certificate)
- [ ] EC2 Security Group 只允許來自 ALB 的流量
- [ ] 定期備份 RDS 和 S3 資料

---

## 📦 Docker Compose 中的環境變數

修改 `docker-compose.yml` 的 PHP 服務：

```yaml
environment:
  - DB_HOST=db
  - MYSQL_DATABASE=omnidrop
  - MYSQL_USER=omnidrop_user
  - MYSQL_PASSWORD=password123
  - AWS_DEFAULT_REGION=ap-southeast-1
  - AWS_S3_BUCKET=omnidrop-files
  # 若使用 IAM Role，以下可省略
  # - AWS_ACCESS_KEY_ID=xxx
  # - AWS_SECRET_ACCESS_KEY=xxx
```

---

## 🧪 測試 S3 連線

在 EC2 上執行：

```php
<?php
require_once '/path/to/vendor/autoload.php';
$s3 = new S3FileManager('omnidrop-files');

// 測試上傳
$s3->uploadFile('/tmp/test.txt', 'test/test.txt');

// 測試下載 URL
echo $s3->getPresignedUrl('test/test.txt', 3600);
?>
```

---

## 📝 資料庫遷移

現有本地 MySQL 表需要遷移到 RDS：

```bash
# 匯出本地資料庫
mysqldump -u omnidrop_user -p omnidrop > omnidrop_backup.sql

# 匯入到 RDS
mysql -h your-rds-endpoint.rds.amazonaws.com -u admin -p omnidrop < omnidrop_backup.sql
```

---

## 成本估算（月度）

| 服務 | 預估成本 |
|------|---------|
| S3 (存儲) | $0.023 per GB |
| RDS MySQL | $15-30 (t3.micro) |
| EC2 | $10-20 (t3.micro) |
| ALB | $16 + 資料處理 |
| **總計** | **$50-100/月** |

*實際成本依使用量而異*

---

## 下一步

1. 建立 S3 Bucket
2. 建立 IAM Role 給 EC2
3. 設置 RDS MySQL
4. 啟動 EC2 實例並部署應用
5. 設置 ALB 和 VPC
6. 測試上傳、下載、分享功能
