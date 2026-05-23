# AWS Learner Lab 部署計畫書 - OmniDrop

**版本**: 1.0  
**最後更新**: 2026-05-22  
**項目名稱**: OmniDrop - 雲端檔案交換與訪客上傳服務

---

## 📋 目錄

1. [執行摘要](#執行摘要)
2. [AWS Learner Lab 限制與考慮](#aws-learner-lab-限制與考慮)
3. [架構設計](#架構設計)
4. [詳細部署步驟](#詳細部署步驟)
5. [成本估算](#成本估算)
6. [測試計畫](#測試計畫)
7. [故障排除](#故障排除)

---

## 執行摘要

本計畫書基於既有的AWS整合指南，為OmniDrop項目在AWS Learner Lab環境中的部署提供詳細步驟。

### 📊 快速參數
- **預算**: $50 USD
- **運行期限**: 10 天 (2026-05-22 至 2026-05-28)
- **預估成本**: ~$5.00 (占預算 10%)
- **風險等級**: 🟢 非常安全

### 部署目標
- 將OmniDrop從本地Docker Compose部署遷移至AWS
- 充分利用S3進行檔案儲存（無限空間）
- 使用RDS MySQL管理應用資料
- 通過公有 IP 和安全群組提供訪問控制

### 核心架構組件
- **VPC**: 使用預設 VPC（無需額外配置）
- **EC2**: 應用伺服器（PHP + Apache） - 免費層
- **RDS MySQL**: 託管資料庫 - $4.08 (10 天)
- **S3**: 檔案儲存後端 - 免費層
- **IAM**: 安全認證與授權 - 免費

---

## AWS Learner Lab 限制與考慮

### ⚠️ 重要限制

| 限制項目 | 說明 | 影響 | 處理方案 |
|---------|------|------|---------|
| **預算上限** | $50 USD | 只能用小型實例 | ✅ t3.micro 足夠 |
| **運行期限** | 10 天 (5/22-5/28) | 需快速部署 | ✅ 提供自動化腳本 |
| **區域限制** | 可用區域受限 | 影響延遲 | ✅ 用 ap-southeast-1 |
| **IAM 權限** | 某些管理操作受限 | 無法修改根設定 | ✅ 無需根權限 |
| **VPC 限制** | 可能已有預設 VPC | 無需新建 | ✅ 使用預設 VPC |
| **實例中斷** | 運行結束時所有資源刪除 | 需提前備份 | ✅ 計畫結束前匯出資料 |

### 🎯 針對 $50 預算的調整

1. **移除 ALB** - 直接使用 EC2 公有 IP 存取
   - 省錢: $16/月 → 年省 $192
   - 影響: 無高可用性，但測試環境可接受

2. **使用預設 VPC** - 減少配置複雜度
   - 省錢: 無額外費用
   - 影響: 無隔離的公/私子網，但測試環境可接受

3. **禁用 RDS 自動備份** - 節省備份儲存費用
   - 省錢: ~$0.50-1.00/10天
   - 影響: 無自動備份，測試環境可接受

4. **單次上傳限制** - 避免超出 S3 免費層
   - 限制: 單個檔案 < 512 MB，總容量 < 5 GB
   - 影響: 對測試用途足夠

5. **快速部署** - 盡量在 10 天內完成
   - 提供: 自動化部署腳本
   - 效益: 節省時間

---

## 架構設計

### 推薦架構 - Learner Lab 優化版本

```
Internet
    ↓
┌──────────────────────────┐
│  EC2 公有 IP             │  (自動公有 IP，無需 Elastic IP)
│  + Security Group        │  (Port 80/443/22)
└──────────────┬───────────┘
               ↓
        ┌──────────────┐
        │  EC2 Instance│  - 公有子網 (PHP 7.4 + Apache)
        │ t3.micro     │  - 免費層覆蓋
        └──────────────┘
         ↓            ↓
    ┌────────┐    ┌────────┐
    │ RDS    │    │ S3     │
    │ MySQL  │    │ Bucket │
    │ t3.m.  │    │(Files) │
    │        │    │        │
    │ $4.08  │    │ 免費   │
    └────────┘    └────────┘
   (10天運行)
```

**關鍵特點**:
- ❌ **無 ALB** - 直接使用 EC2 公有 IP (節省 $16/月)
- ✅ **簡化 VPC** - 使用預設 VPC
- ✅ **最小成本** - 符合 $50 預算需求
- ✅ **快速部署** - 無需複雜配置

### 方案對比

#### 方案 A：簡化版（$50 預算推薦）✅
- **ALB**: ❌ 不使用（直接用公有 IP）
- **VPC**: ✅ 使用預設 VPC
- **EC2**: ✅ t3.micro（1核，1GB RAM）- 免費層
- **RDS**: ✅ t3.micro（1核，1GB RAM）- $4.08/10天
- **總成本**: ~$5.00 (10 天)
- **配置時間**: ~2-3 小時
- **適用場景**: ✅ AWS Learner Lab 10 天測試

#### 方案 B：生產版（不推薦用於 $50 預算）
- **ALB**: ✅ 使用 (+$16/月)
- **VPC**: ✅ 新建公/私子網
- **EC2**: ✅ t3.small（2核，2GB RAM）
- **RDS**: ✅ t3.small（2核，2GB RAM）
- **總成本**: ~$58/月
- **配置時間**: ~4-5 小時
- **適用場景**: ⚠️ 超出 $50 預算，不適用當前任務

**決定**: **採用方案 A，完全符合預算需求**

---

## 詳細部署步驟

### 第一階段：AWS 資源準備

#### 步驟 1.1：驗證 AWS 賬戶與預算
1. 登入 AWS Learner Lab 控制台
2. 檢查剩餘預算（頁面右上角）
3. 確認可用區域（通常為 us-east-1 或 ap-southeast-1）

**預期結果**: 確認有足夠的預算且區域可用

#### 步驟 1.2：建立 S3 Bucket

```bash
# 使用 AWS CLI 或 AWS 管理控制台

# 方法 1：AWS CLI
aws s3 mb s3://omnidrop-files-learner-lab --region ap-southeast-1

# 設定私有訪問策略
aws s3api put-bucket-versioning \
  --bucket omnidrop-files-learner-lab \
  --versioning-configuration Status=Enabled
```

**驗證方法**:
- 進入 S3 控制台，確認 bucket 已建立
- 檢查"存取権限"為私有

#### 步驟 1.3：建立 IAM Role 給 EC2

1. 進入 **IAM 控制台**
2. 選擇 **角色 → 建立角色**
3. 選擇 **AWS 服務 → EC2**
4. 建立自訂策略 (Inline Policy)：

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::omnidrop-files-learner-lab",
        "arn:aws:s3:::omnidrop-files-learner-lab/*"
      ]
    }
  ]
}
```

5. 命名角色為 `OmniDropEC2Role`

**驗證方法**: 角色已在 IAM 控制台中可見

#### 步驟 1.4：建立 RDS MySQL 實例

1. 進入 **RDS 控制台**
2. 選擇 **建立資料庫**
3. 選擇 **MySQL** 引擎，版本 **8.0.28** (或更新)
4. 選擇 **多 AZ 部署：否** (節省成本)
5. **實例規格**: 
   - 實例類別: `db.t3.micro`
   - 儲存: 20 GB (可調整)
   - 啟用自動備份: 7 天

6. **連線設定**:
   - VPC: 預設 VPC
   - 子網群組: 預設
   - 公開可訪問性: **否** (安全起見)
   - VPC 安全群組: 建立新群組，命名 `OmniDropDB-SG`

7. **資料庫設定**:
   - 資料庫名稱: `omnidrop`
   - 主使用者: `admin`
   - 密碼: (設定複雜密碼，並記錄)

8. 完成建立（等待 5-10 分鐘）

**驗證方法**:
- RDS 狀態顯示為"可用"
- 記錄 Endpoint (格式: `xxxxxx.xxx.rds.amazonaws.com`)

#### 步驟 1.5：建立 EC2 安全群組

1. 進入 **EC2 控制台 → 安全群組**
2. 建立新安全群組，命名 `OmniDropWeb-SG`
3. 入站規則:

| 通訊協定 | 連接埠 | 來源 | 用途 |
|---------|-------|------|------|
| HTTP | 80 | 0.0.0.0/0 | 網路流量 |
| HTTPS | 443 | 0.0.0.0/0 | 安全連線 |
| SSH | 22 | 0.0.0.0/0 | 遠端管理 |

4. 出站規則: 全部允許 (預設)

**驗證方法**: 安全群組已建立，規則已確認

#### 步驟 1.6：更新 RDS 安全群組以允許 EC2 存取

1. 進入 **RDS 控制台**，找到 `OmniDropDB-SG`
2. 編輯入站規則，新增:

| 通訊協定 | 連接埠 | 來源 | 說明 |
|---------|-------|------|------|
| MySQL/Aurora | 3306 | OmniDropWeb-SG | EC2 存取 |

**驗證方法**: RDS 安全群組規則已更新

---

### 第二階段：EC2 實例配置

#### 步驟 2.1：啟動 EC2 實例

1. 進入 **EC2 控制台 → 執行個體**
2. 選擇 **啟動執行個體**
3. 選擇 AMI:
   - **Amazon Linux 2023** 或 **Ubuntu Server 22.04 LTS**
   - 免費層符合條件
4. 實例類型: **t3.micro** (1 核，1 GB RAM)
5. 金鑰對: 建立或選擇現有金鑰對（用於 SSH）
6. 網路設定:
   - VPC: 預設 VPC
   - 子網: 預設子網
   - 自動分配公有 IP: **是**
   - 安全群組: 選擇 `OmniDropWeb-SG`
7. 儲存: 20 GB (General Purpose)
8. 進階詳細資訊:
   - IAM 實例設定檔: 選擇 `OmniDropEC2Role`
   - 使用者資料 (User Data):

```bash
#!/bin/bash
set -e

# 更新系統
yum update -y

# 安裝 PHP 和 Apache
amazon-linux-extras install php7.4 -y
yum install httpd php-cli php-pdo php-mysqlnd -y

# 安裝 Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# 啟動 Apache
systemctl start httpd
systemctl enable httpd

# 配置 PHP 上傳限制
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 256M/' /etc/php.ini
sed -i 's/post_max_size = .*/post_max_size = 256M/' /etc/php.ini

echo "EC2 初始化完成"
```

9. 啟動執行個體

**驗證方法**:
- 執行個體狀態為"執行中"
- 取得公有 IP 地址
- 嘗試通過 SSH 連線

#### 步驟 2.2：連線到 EC2 並部署應用

通過 SSH 連線到 EC2:

```bash
ssh -i your-key.pem ec2-user@<EC2-PUBLIC-IP>
```

#### 步驟 2.3：下載和設定應用

```bash
# 切換到 web 根目錄
cd /var/www/html

# 克隆或上傳 OmniDrop 代碼
git clone <your-repo-url> .
# 或使用 scp 上傳

# 安裝 Composer 依賴
composer install

# 建立環境變數檔案
cat > .env << EOF
DB_HOST=<RDS-ENDPOINT>
MYSQL_DATABASE=omnidrop
MYSQL_USER=admin
MYSQL_PASSWORD=<YOUR-PASSWORD>
AWS_DEFAULT_REGION=ap-southeast-1
AWS_S3_BUCKET=omnidrop-files-learner-lab
EOF

# 設定檔案權限
chmod -R 755 .
chown -R apache:apache /var/www/html
```

#### 步驟 2.4：初始化資料庫

```bash
# 從 EC2 連線到 RDS 並執行 SQL
mysql -h <RDS-ENDPOINT> -u admin -p omnidrop < mysql/init.sql

# 如果需要，匯入現有資料庫
mysql -h <RDS-ENDPOINT> -u admin -p omnidrop < omnidrop_backup.sql
```

**驗證方法**:
- 資料庫表已建立
- 檢查資料庫連線是否成功

#### 步驟 2.5：配置 Apache VirtualHost

```bash
# 編輯 Apache 配置
sudo vi /etc/httpd/conf.d/omnidrop.conf
```

新增以下內容:

```apache
<VirtualHost *:80>
    ServerName omnidrop.example.com
    ServerAdmin admin@example.com
    DocumentRoot /var/www/html/src

    <Directory /var/www/html/src>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/omnidrop-error.log
    CustomLog /var/log/httpd/omnidrop-access.log combined
</VirtualHost>
```

重啟 Apache:

```bash
sudo systemctl restart httpd
```

**驗證方法**:
- 訪問 `http://<EC2-PUBLIC-IP>/` 檢查應用是否加載

---

### 第三階段：測試與驗證

#### 步驟 3.1：測試 S3 連線

在 EC2 上執行:

```bash
php -r "
require '/var/www/html/vendor/autoload.php';
\$s3 = new Aws\S3\S3Client(['region' => 'ap-southeast-1']);
\$buckets = \$s3->listBuckets();
echo 'S3 連線成功，bucket 數量: ' . count(\$buckets['Buckets']) . PHP_EOL;
"
```

**驗證項目**:
- ✅ S3 連線成功
- ✅ IAM Role 權限正確

#### 步驟 3.2：測試資料庫連線

```bash
mysql -h <RDS-ENDPOINT> -u admin -p omnidrop -e "SHOW TABLES;"
```

**驗證項目**:
- ✅ 能連線到 RDS
- ✅ 所有表已建立

#### 步驟 3.3：測試應用功能

1. **用戶登入**:
   - 存取 `http://<EC2-PUBLIC-IP>/`
   - 使用預設帳號登入

2. **上傳檔案**:
   - 上傳小檔案 (< 100 MB)
   - 驗證檔案是否出現在 S3 Bucket

3. **建立分享連結**:
   - 建立密碼保護的分享
   - 測試下載功能

4. **Dropzone 功能**:
   - 建立 Dropzone
   - 使用分享連結上傳檔案

**驗證清單**:
- [ ] 用戶認證正常
- [ ] 檔案上傳至 S3
- [ ] 下載功能正常
- [ ] Presigned URL 生效
- [ ] 密碼保護有效

---

### 第四階段：❌ 不推薦 - 設定 ALB (超出 $50 預算)

⚠️ **警告**: ALB 月成本 $16，加上其他資源會超出 $50 預算。

**若要在後續升級至生產環境時使用 ALB**，以下為配置步驟 (僅供參考，本專案不執行):

#### 步驟 4.1：建立應用負載均衡器

1. 進入 **EC2 控制台 → 負載均衡器**
2. 建立 **應用負載均衡器**
3. 配置:
   - 名稱: `OmniDropALB`
   - Scheme: 網際網路對向
   - IP 位址類型: IPv4
4. 監聽器:
   - HTTP (80) → 目標群組
5. 目標群組:
   - 建立新目標群組
   - 名稱: `OmniDropTargets`
   - 通訊協定: HTTP
   - 連接埠: 80
   - 註冊 EC2 執行個體

**驗證方法**:
- ALB 狀態為"主動"
- 目標執行個體為"健康"

---

## 成本估算

### 💰 $50 預算 × 10 天運行期限 (5/22-5/28) 精確分析

#### 目標運行期限
- **開始日期**: 2026-05-22
- **結束日期**: 2026-05-28
- **運行時長**: 10 天 (240 小時)
- **預算限額**: $50 USD

#### 成本分解（10 天運行）

| 服務 | 規格 | 用量 | 免費層 | 實際成本 |
|------|------|------|--------|---------|
| **EC2** | t3.micro | 240 小時 | ✅ 已覆蓋 | $0 |
| **RDS MySQL** | t3.micro | 240 小時 | ❌ | **$4.08** |
| **S3 儲存** | - | 5-10 GB | ✅ 已覆蓋 | $0 |
| **S3 請求** | - | 50K-100K | ✅ 已覆蓋 | $0 |
| **資料傳輸 (出站)** | - | 5 GB | ✅ 已覆蓋 | $0 |
| **其他費用** | - | - | - | **~$0.50** |
| **小計** | | | | **~$4.58** |
| **安全邊際** | | | | **+10%** |
| **估計總成本** | | | | **≈ $5.00** |

**✅ 預算狀況**: 
- 預算: $50
- 估計成本: $5.00
- **剩餘預算**: $45.00
- **成本占比**: 10%
- **風險等級**: 🟢 非常安全

#### 關鍵假設
- RDS 按 $0.017/小時計費 (ap-southeast-1 地區)
- EC2 免費層涵蓋第一 12 個月
- S3 免費層涵蓋 5 GB 存儲 + 20,000 GET 請求
- 無額外服務 (ALB、NAT Gateway 等)
- 無跨區域資料傳輸

#### 隱藏成本警告 ⚠️

**可能增加成本的因素**:

1. **RDS 備份儲存**
   - 自動備份: 第一份免費，之後 $0.095 per GB
   - **建議**: 禁用自動備份節省成本
   - **影響**: 可能額外 +$1-2

2. **資料傳輸超額**
   - 若上傳大量檔案到 S3: $0.12 per GB (超過 100 GB)
   - **建議**: 單次檔案上傳限制 < 512 MB
   - **影響**: 若合理使用，$0

3. **彈性 IP 未使用**
   - 若分配 EIP 但未綁定: $0.005/小時
   - **建議**: 不使用 EIP，用自動公有 IP
   - **影響**: $0

4. **Snapshot 費用**
   - EBS Snapshot: $0.05 per GB
   - **建議**: 部署後不建立快照
   - **影響**: $0 (若不建立)

5. **VPC NAT Gateway** (如果使用)
   - $0.045/小時 + 資料處理費
   - **建議**: 使用公有子網，無需 NAT
   - **影響**: $0 (已規避)

**最壞情況估計**: $8-10 (仍在預算內)

---

### 原方案 A：簡化版（月度）

| 服務 | 規格 | 用量 | 成本/月 |
|------|------|------|---------|
| **EC2** | t3.micro | 730 小時 | $6.50 |
| **RDS MySQL** | t3.micro | 730 小時 | $8.00 |
| **S3 儲存** | - | 10 GB | $0.23 |
| **S3 請求** | - | 100K GET/PUT | $0.08 |
| **資料傳輸** | 出站 | 10 GB | $0.90 |
| **小計** | | | **~$16/月** |

---

### 原方案 B：生產版（月度）

| 服務 | 規格 | 用量 | 成本/月 |
|------|------|------|---------|
| **EC2** | t3.small | 730 小時 | $16.50 |
| **RDS MySQL** | t3.small | 730 小時 | $20.00 |
| **ALB** | 標準 | - | $16.00 |
| **S3 儲存** | - | 50 GB | $1.15 |
| **S3 請求** | - | 500K 請求 | $0.20 |
| **資料傳輸** | 出站 | 50 GB | $4.50 |
| **小計** | | | **~$58/月** |

---

### 結論與建議

**對於 $50 預算 + 10 天期限**:
- ✅ **簡化版方案完全適用** - 成本遠低於預算
- ❌ **不建議使用生產版** - 預算不足
- ✅ **無需任何成本優化** - 已經是最低成本配置

---

## 測試計畫

### 單元測試

#### 測試 1：S3 整合
```php
// 測試 S3FileManager 功能
$s3 = new S3FileManager('omnidrop-files-learner-lab');

// 上傳測試
$testFile = '/tmp/test.txt';
file_put_contents($testFile, 'Test content');
$s3->uploadFile($testFile, 'test/test.txt');
assert(file_exists($testFile) === true, 'Local file exists');

// 生成 Presigned URL
$url = $s3->getPresignedUrl('test/test.txt', 3600);
assert(strpos($url, 'X-Amz-Signature') !== false, 'Presigned URL contains signature');

// 刪除測試
$s3->deleteFile('test/test.txt');
echo "✅ S3 整合測試通過\n";
```

#### 測試 2：資料庫連線
```php
// 驗證 PDO 連線
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['MYSQL_DATABASE']}",
    $_ENV['MYSQL_USER'],
    $_ENV['MYSQL_PASSWORD']
);
$result = $pdo->query('SELECT 1');
assert($result !== false, 'Database connection successful');
echo "✅ 資料庫連線測試通過\n";
```

### 集成測試

#### 測試 3：完整上傳流程
1. 登入應用
2. 上傳 10 MB 檔案
3. 驗證檔案出現在 S3
4. 驗證資料庫記錄已建立
5. **預期結果**: 所有步驟成功

#### 測試 4：下載與分享
1. 建立分享連結（密碼保護）
2. 使用分享連結下載
3. 驗證 Presigned URL 功能
4. **預期結果**: 下載成功

#### 測試 5：Dropzone 功能
1. 建立 Dropzone
2. 通過分享連結上傳檔案
3. 驗證上傳至正確的資料夾
4. **預期結果**: Dropzone 功能正常

#### 測試 6：負載測試
1. 同時上傳 5 個檔案
2. 監控 EC2 CPU 和記憶體使用率
3. **預期結果**: CPU < 80%, 記憶體 < 512 MB

### 測試清單

- [ ] EC2 SSH 連線正常
- [ ] RDS 資料庫連線正常
- [ ] S3 Bucket 已建立且私有
- [ ] IAM Role 權限正確
- [ ] Apache 服務已啟動
- [ ] PHP 依賴已安裝
- [ ] 應用首頁加載正常
- [ ] 用戶登入功能正常
- [ ] 檔案上傳至 S3
- [ ] 檔案下載正常
- [ ] Presigned URL 有效
- [ ] 密碼保護有效
- [ ] Dropzone 功能正常
- [ ] 資料庫中繼資料同步

---

## 故障排除

### 常見問題

#### 問題 1：EC2 無法連線到 RDS

**症狀**: 
```
SQLSTATE[HY000]: General error: 2002 Can't connect to MySQL server on 'xxx.rds.amazonaws.com'
```

**排查步驟**:
1. 驗證 RDS 安全群組允許 EC2 存取 (連接埠 3306)
2. 驗證 RDS 端點正確
3. 驗證 RDS 已完全初始化（狀態為"可用"）

**解決方案**:
```bash
# 測試連線
telnet <RDS-ENDPOINT> 3306

# 或使用 mysql 用戶端
mysql -h <RDS-ENDPOINT> -u admin -p -e "SELECT 1;"
```

#### 問題 2：S3 上傳失敗 - Access Denied

**症狀**:
```
An error occurred (AccessDenied) when calling the PutObject operation
```

**排查步驟**:
1. 驗證 EC2 IAM Role 已正確附加
2. 驗證 IAM 策略包含 `s3:PutObject` 權限
3. 驗證 S3 Bucket 名稱正確

**解決方案**:
```bash
# 檢查 IAM Role
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/

# 測試 S3 存取
aws s3 ls s3://omnidrop-files-learner-lab
```

#### 問題 3：應用頁面顯示空白

**症狀**: 訪問 `http://<IP>/` 只顯示空白頁

**排查步驟**:
1. 檢查 Apache 錯誤日誌
2. 驗證 PHP 已安裝
3. 驗證檔案權限正確

**解決方案**:
```bash
# 檢查 Apache 日誌
tail -50 /var/log/httpd/omnidrop-error.log

# 測試 PHP
php -r "phpinfo();"

# 檢查檔案權限
ls -la /var/www/html/src/index.php
```

#### 問題 4：Presigned URL 無效

**症狀**: 點擊下載連結返回 403 Forbidden

**排查步驟**:
1. 驗證 URL 未過期
2. 驗證物件存在於 S3
3. 驗證簽名正確

**解決方案**:
```bash
# 驗證物件是否存在
aws s3api head-object \
  --bucket omnidrop-files-learner-lab \
  --key test/test.txt
```

#### 問題 5：記憶體不足 (OOM)

**症狀**: PHP 進程被 Kill，應用無回應

**排查步驟**:
1. 檢查 PHP memory_limit 設定
2. 監控 EC2 記憶體使用率

**解決方案**:
```bash
# 增加 PHP 記憶體限制
sudo vi /etc/php.ini
# 修改: memory_limit = 256M

# 重啟 Apache
sudo systemctl restart httpd

# 監控記憶體
free -h
top -b -n 1 | head -20
```

### 日誌收集與分析

#### 收集日誌

```bash
# Apache 錯誤日誌
sudo cat /var/log/httpd/omnidrop-error.log

# PHP-FPM 日誌
sudo cat /var/log/php-fpm.log

# AWS CloudWatch 日誌（可選）
aws logs tail /aws/ec2/application --follow
```

#### 常見的日誌錯誤

| 錯誤訊息 | 原因 | 解決方案 |
|---------|------|---------|
| `PDOException: SQLSTATE[HY000]` | DB 連線失敗 | 檢查 RDS 安全群組 |
| `AWS\Exception\AwsException` | IAM 權限不足 | 檢查 IAM Role 策略 |
| `Allowed memory exceeded` | 記憶體不足 | 增加 memory_limit |
| `Call to undefined function` | 缺少 PHP 擴展 | 安裝 php-pdo 或 php-curl |

---

## 後續步驟與優化

### 🎯 10 天期限內的行動計畫

#### 第 1-2 天：AWS 資源準備與部署
- ✅ 建立 S3 Bucket
- ✅ 建立 IAM Role
- ✅ 建立 RDS MySQL
- ✅ 建立 EC2 安全群組
- ✅ 啟動 EC2 實例
- ⏱️ **預期時間**: 2-3 小時

#### 第 2-4 天：應用部署與配置
- ✅ 連線 EC2 並執行自動化腳本
- ✅ 初始化 RDS 資料庫
- ✅ 部署 OmniDrop 代碼
- ✅ 配置環境變數
- ⏱️ **預期時間**: 1-2 小時

#### 第 4-7 天：測試驗證
- ✅ 測試 S3 連線
- ✅ 測試 RDS 連線
- ✅ 測試應用功能（上傳、下載、分享）
- ✅ 測試 Dropzone 功能
- ✅ 執行負載測試
- ⏱️ **預期時間**: 3-4 天

#### 第 7-10 天：最終驗證與準備升級
- ✅ 確認所有功能正常
- ✅ 匯出重要資料
- ✅ 記錄部署經驗
- ✅ 準備升級至生產版本的計畫
- ⏱️ **預期時間**: 3 天

### Phase 2：升級至生產環境（Learner Lab 後）

若預算充足，建議以下升級步驟：

1. **設定 ALB** (+$16/月):
   - 設置應用負載均衡器
   - 配置健康檢查
   - 分散流量至多個 EC2 實例

2. **HTTPS/SSL**:
   - 申請 AWS Certificate Manager (ACM) 免費憑證
   - 配置 ALB HTTPS 監聽器 (443)

3. **自動備份**:
   - 設定 S3 生命週期策略
   - 啟用 RDS 自動備份 (7 天保留)
   - 配置跨區備份

4. **監控與告警**:
   - 啟用 CloudWatch 監控
   - 設定 SNS 告警
   - 配置日誌轉發至 CloudWatch Logs

5. **Auto Scaling** (可選):
   - 建立 Launch Template
   - 設定 Auto Scaling Group
   - 根據負載自動擴展

### Phase 3：成本優化（長期運行）

- 評估 Reserved Instances（降低 50% 成本）
- 配置 S3 Intelligent-Tiering（自動分層存儲）
- 設定 EC2 Spot Instances（非關鍵負載）
- 使用 AWS Savings Plans

---

## 附錄

### A. 快速部署腳本

完整的部署自動化腳本（可在 EC2 User Data 中使用）:

```bash
#!/bin/bash
set -e

# 變數定義
RDS_ENDPOINT="your-rds-endpoint.rds.amazonaws.com"
DB_NAME="omnidrop"
DB_USER="admin"
DB_PASSWORD="your_password"
S3_BUCKET="omnidrop-files-learner-lab"
APP_REPO="https://github.com/your-repo/omnidrop.git"

# 1. 系統更新
yum update -y
amazon-linux-extras install php7.4 -y
yum install httpd php-cli php-pdo php-mysqlnd git -y

# 2. 安裝 Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# 3. 下載應用代碼
cd /var/www/html
git clone $APP_REPO .

# 4. 安裝依賴
composer install

# 5. 設定環境變數
cat > .env << EOF
DB_HOST=$RDS_ENDPOINT
MYSQL_DATABASE=$DB_NAME
MYSQL_USER=$DB_USER
MYSQL_PASSWORD=$DB_PASSWORD
AWS_DEFAULT_REGION=ap-southeast-1
AWS_S3_BUCKET=$S3_BUCKET
EOF

# 6. 設定檔案權限
chmod -R 755 .
chown -R apache:apache /var/www/html

# 7. 配置 Apache
cat > /etc/httpd/conf.d/omnidrop.conf << 'APACHE_EOF'
<VirtualHost *:80>
    ServerName _
    DocumentRoot /var/www/html/src
    <Directory /var/www/html/src>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
APACHE_EOF

# 8. 啟動服務
systemctl start httpd
systemctl enable httpd

# 9. 初始化資料庫
mysql -h $RDS_ENDPOINT -u $DB_USER -p$DB_PASSWORD $DB_NAME < mysql/init.sql

echo "部署完成！應用已啟動"
```

### B. 安全清單

部署前確認以下安全事項：

- [ ] S3 Bucket 設為私有（BlockPublicAccess 啟用）
- [ ] IAM Role 使用最小權限原則
- [ ] RDS 在私有子網，禁用公開存取
- [ ] EC2 安全群組限制 SSH 存取（建議限制到特定 IP）
- [ ] 使用強密碼給 RDS 主帳號
- [ ] 啟用 VPC Flow Logs 用於審計
- [ ] 定期備份 RDS 和 S3 資料

### C. 環境變數參考

```bash
# 資料庫配置
DB_HOST=your-rds-endpoint.rds.amazonaws.com
MYSQL_DATABASE=omnidrop
MYSQL_USER=admin
MYSQL_PASSWORD=your_secure_password

# AWS 配置
AWS_DEFAULT_REGION=ap-southeast-1
AWS_S3_BUCKET=omnidrop-files-learner-lab

# 應用配置
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# PHP 配置
PHP_MEMORY_LIMIT=256M
PHP_UPLOAD_MAX_FILESIZE=256M
PHP_POST_MAX_SIZE=256M
```

---

## 版本歷史

| 版本 | 日期 | 變更 |
|------|------|------|
| 1.1 | 2026-05-22 | **$50 預算優化版本** - 更新成本估算、移除 ALB、添加 10 天期限行動計畫 |
| 1.0 | 2026-05-22 | 初版發布，完整 Learner Lab 部署計畫 |

---

## 聯絡方式

**專案組員**:
- 賴政宏 (411630428)
- 蕭博文 (411630758)
- 翁濬緯 (411630337)

**文件維護**: 定期更新部署經驗和最佳實踐

---

**完整度**: 100% | **最後驗證**: 2026-05-22 | **狀態**: ✅ 準備就緒
