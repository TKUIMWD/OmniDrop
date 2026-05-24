# OmniDrop AWS EC2 部署故障排除與解決方案指南

**版本**: 1.0  
**最後更新**: 2026-05-24  
**項目**: OmniDrop - 雲端檔案交換服務  
**部署環境**: AWS EC2 + Docker + Apache + PHP 8.2 + MariaDB  

---

## 📋 目錄

1. [部署摘要](#部署摘要)
2. [遇到的主要問題與解決方案](#遇到的主要問題與解決方案)
3. [Apache 配置調整](#apache-配置調整)
4. [Docker 配置](#docker-配置)
5. [AWS IAM 與 S3 配置](#aws-iam-與-s3-配置)
6. [最終驗證清單](#最終驗證清單)
7. [快速故障排除](#快速故障排除)

---

## 部署摘要

### 部署目標
- ✅ 在 AWS EC2 (Amazon Linux 3) 上成功部署 OmniDrop 應用
- ✅ 使用 Docker Compose 運行 PHP Apache + MariaDB
- ✅ 應用可通過公網 IP 訪問（http://[PUBLIC_IP]:12340）
- ✅ S3 文件上傳功能正常工作

### 最終架構
```
┌─────────────────────────────────────┐
│     AWS EC2 Instance (AL3)          │
│  ┌───────────────────────────────┐  │
│  │   Docker Container - Web      │  │
│  │  ├─ Apache 2.4.67            │  │
│  │  ├─ PHP 8.2.31               │  │
│  │  ├─ MySQLi Extension         │  │
│  │  └─ AWS SDK for PHP          │  │
│  └───────────────────────────────┘  │
│  ┌───────────────────────────────┐  │
│  │   Docker Container - DB       │  │
│  │  └─ MariaDB 10.6             │  │
│  └───────────────────────────────┘  │
│         Docker Network              │
└─────────────────────────────────────┘
         ↓ (IAM Role)
    ┌─────────────┐
    │  AWS S3     │
    │  Bucket     │
    └─────────────┘
```

---

## 遇到的主要問題與解決方案

### 1. **Amazon Linux 3 軟件包不兼容**

#### 問題
```
php-mysql: command not found
```

**原因**: Amazon Linux 3 不包含 `amazon-linux-extras`，且 PHP 軟件包命名不同

#### ✅ 解決方案
使用 `php-mysqlnd` 而非 `php-mysql`
```bash
# 正確的做法
yum install -y php-mysqlnd php-pdo

# 不正確的做法（Amazon Linux 3 中不存在）
yum install -y php-mysql amazon-linux-extras
```

**在 Dockerfile 中的實現**:
```dockerfile
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    ca-certificates curl git zip unzip && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli && \
    apt-get clean && rm -rf /var/lib/apt/lists/*
```

---

### 2. **PHP 7.4 apt 源已過期**

#### 問題
```
E: The repository 'http://deb.debian.org/debian buster Release' does not have a Release file.
```

**原因**: Debian Buster (PHP 7.4 base) 的 apt 源已停止支持

#### ✅ 解決方案
升級到 **PHP 8.2**（使用更新的 Debian Bookworm base image）

**Dockerfile 修改**:
```dockerfile
# ❌ 舊版（過期）
FROM php:7.4-apache

# ✅ 新版（有效）
FROM php:8.2-apache
```

**composer.json 也需更新**:
```json
{
  "require": {
    "php": "^8.0"    // 從 "^7.4" 改為 "^8.0"
  }
}
```

---

### 3. **Docker Build Context 問題**

#### 問題
```
COPY composer.json composer.lock* ./
Build error: composer.json not found
```

**原因**: docker-compose 的 volume mount 只掛載 `./src/`，build context 中沒有 composer.json

#### ✅ 解決方案
修改 volume 掛載以暴露整個項目目錄

**docker-compose.yml**:
```yaml
# ❌ 錯誤
volumes:
  - ./src:/var/www/html

# ✅ 正確
volumes:
  - ./:/var/www/html
```

**原因**: 
- Build 階段需要訪問 `composer.json` （在項目根目錄）
- Runtime 階段需要訪問整個應用代碼

---

### 4. **Apache 403 Forbidden - DirectoryIndex 問題**

#### 問題表現
```
GET / HTTP/1.1" 403 478
AH01276: Cannot serve directory /var/www/html/: 
No matching DirectoryIndex (index.php,index.html) found, 
and server-generated directory index forbidden by Options directive
```

**原因**: 
1. Apache 沒有設置 DirectoryIndex 來查找 index.php
2. Directory 權限/Options 配置不允許索引服務

#### ✅ 解決方案

**方案 A: Dockerfile 配置（推薦）**

```dockerfile
# 設置 DirectoryIndex
RUN sed -i 's/DirectoryIndex index.html/DirectoryIndex index.php index.html/' \
    /etc/apache2/mods-enabled/dir.conf

# 允許 .htaccess 覆蓋
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 啟用 rewrite 模塊
RUN a2enmod rewrite
```

**方案 B: 創建 VirtualHost（更重要）**

```dockerfile
# 在 Dockerfile 中添加
RUN cat > /etc/apache2/sites-available/omnidrop.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/src
    
    <Directory /var/www/html/src>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

RUN a2dissite 000-default && a2ensite omnidrop
```

**為什麼需要 VirtualHost？**
- 直接設置 `DocumentRoot /var/www/html/src` 而不是掛載根目錄
- 確保文件所有者和權限正確
- 避免 Docker volume mount 的權限問題

---

### 5. **Docker Volume 權限問題**

#### 問題
```
chown -R www-data:www-data /var/www/html/src
# 執行後仍然顯示原始所有者
drwxr-xr-x. 6 1000 1000 16384 May 23 09:31 src
```

**原因**: Docker volume mount 時，文件所有者綁定到主機的 UID/GID，容器內 chown 無法持久化

#### ✅ 解決方案

**在容器啟動時一次性修復**:

```bash
docker-compose exec -T web bash << 'EOF'
chown -R www-data:www-data /var/www/html/src
chmod -R 755 /var/www/html/src
chmod 644 /var/www/html/src/*.php
EOF
```

**或在 Dockerfile 中預防**:

```dockerfile
# 確保新文件由 www-data 創建
RUN chown -R www-data:www-data /var/www/html
```

---

### 6. **容器無法訪問 AWS Metadata Service**

#### 問題
```
容器內執行:
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/
# 無返回內容
```

**原因**: Docker bridge network 隔離了對主機 metadata service 的訪問

#### ✅ 解決方案

**修改 docker-compose.yml 使用 host network**:

```yaml
services:
  web:
    build:
      context: .
      dockerfile: php/Dockerfile
    ports:
      - "${PORT}:80"
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=db  # 仍然可以通過容器名訪問數據庫
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET}
    networks:
      - ctf_net
    depends_on:
      - db
```

**為什麼 DB_HOST 仍然是 "db"？**
- Docker DNS 會自動解析服務名稱為其容器 IP
- 即使使用 host network，同一網絡內的容器仍可通過服務名通信

---

## Apache 配置調整

### 完整的 Apache 配置清單

#### 1. **DirectoryIndex 配置**

位置: `/etc/apache2/mods-enabled/dir.conf`

```apache
<IfModule mod_dir.c>
    DirectoryIndex index.php index.html index.cgi index.pl index.php index.xhtml index.htm
</IfModule>
```

**修改命令**:
```bash
sed -i 's/DirectoryIndex index.html/DirectoryIndex index.php index.html/' \
    /etc/apache2/mods-enabled/dir.conf
```

#### 2. **全局 AllowOverride 配置**

位置: `/etc/apache2/apache2.conf`

```apache
<Directory /var/www>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**修改命令**:
```bash
sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
```

#### 3. **VirtualHost 配置（最重要）**

位置: `/etc/apache2/sites-available/omnidrop.conf`

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/src
    
    # 對 DocumentRoot 目錄的權限設置
    <Directory /var/www/html/src>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # PHP 執行
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

**啟用方法**:
```bash
a2dissite 000-default      # 禁用默認配置
a2ensite omnidrop          # 啟用自定義配置
apache2ctl restart         # 重啟 Apache
```

#### 4. **模塊啟用**

```bash
# 啟用必要的模塊
a2enmod rewrite            # URL 重寫
a2enmod dir                # 目錄索引
a2enmod php8.2             # PHP 執行
```

#### 5. **檢查 Apache 配置語法**

```bash
# 在容器內執行
apache2ctl configtest

# 預期輸出
# AH00558: apache2: Could not reliably determine the server's fully qualified domain name...
# Syntax OK
```

---

## Docker 配置

### 完整的 docker-compose.yml

```yaml
services:
  web:
    build:
      context: .
      dockerfile: php/Dockerfile
    ports:
      - "${PORT}:80"
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=db
      - MYSQL_DATABASE=${MYSQL_DATABASE}
      - MYSQL_USER=${MYSQL_USER}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION}
      - AWS_S3_BUCKET=${AWS_S3_BUCKET}
    networks:
      - ctf_net
    depends_on:
      - db

  db:
    image: mariadb:10.6
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - ./mysql/data:/var/lib/mysql
      - ./mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - ctf_net

networks:
  ctf_net:
    driver: bridge
```

### 完整的 Dockerfile

```dockerfile
FROM php:8.2-apache

# 安裝系統依賴
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    zip \
    unzip && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# 安裝 Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# 設置工作目錄
WORKDIR /var/www/html

# 從項目根目錄複製 composer 文件
COPY composer.json composer.lock* ./

# 安裝項目依賴
RUN composer install --no-dev --optimize-autoloader 2>&1 | \
    grep -v "^Deprecation Warning" || true

# 設置文件所有者
RUN chown -R www-data:www-data /var/www/html

# ===== Apache 配置 =====

# 啟用 rewrite 模塊
RUN a2enmod rewrite

# 允許 .htaccess 覆蓋
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 設置 DirectoryIndex
RUN sed -i 's/DirectoryIndex index.html/DirectoryIndex index.php index.html/' \
    /etc/apache2/mods-enabled/dir.conf

# 創建 VirtualHost 配置
RUN cat > /etc/apache2/sites-available/omnidrop.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/src
    
    <Directory /var/www/html/src>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# 啟用 VirtualHost
RUN a2dissite 000-default && a2ensite omnidrop
```

---

## AWS IAM 與 S3 配置

### AWS EC2 IAM Role 設置

#### 1. 創建 IAM Role

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
        "arn:aws:s3:::omnidrop-files",
        "arn:aws:s3:::omnidrop-files/*"
      ]
    }
  ]
}
```

#### 2. 附加 Role 到 EC2 實例

```bash
# 通過 AWS Console 或 CLI
aws ec2 associate-iam-instance-profile \
  --instance-id i-0123456789abcdef0 \
  --iam-instance-profile Name=OmniDropRole
```

#### 3. 驗證 IAM Role

```bash
# 在 EC2 上執行
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/
# 應返回 role 名稱

# 獲取臨時憑證
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/[ROLE_NAME]
# 應返回 AccessKeyId, SecretAccessKey, Token 等
```

### .env 文件配置

```bash
# Web Server Configuration
PORT=12340
DB_HOST=db

# MySQL Database Configuration
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=omnidrop
MYSQL_USER=omniuser
MYSQL_PASSWORD=omnipassword!!

# AWS Configuration
AWS_DEFAULT_REGION=us-east-1
AWS_S3_BUCKET=omnidrop-files

# 注意：由於使用 IAM Role，以下不需要配置
# AWS_ACCESS_KEY_ID 和 AWS_SECRET_ACCESS_KEY 會自動從 metadata service 獲取
```

---

## 最終驗證清單

在部署完成後，依次驗證以下項目：

### 1. Docker 容器狀態
```bash
docker-compose ps
# 預期：web 和 db 容器都 Running

docker-compose logs web | tail -20
# 預期：Apache successfully started, no errors
```

### 2. Apache 配置驗證
```bash
docker-compose exec -T web apache2ctl configtest
# 預期：Syntax OK

docker-compose exec -T web ls -la /etc/apache2/sites-enabled/
# 預期：omnidrop.conf 符號鏈接存在
```

### 3. PHP 和擴展驗證
```bash
docker-compose exec -T web php -v
# 預期：PHP 8.2.31

docker-compose exec -T web php -m | grep -i mysql
# 預期：mysqli 和 pdo_mysql 出現
```

### 4. 數據庫連接驗證
```bash
docker-compose exec -T web php -r "
require 'src/db.php';
echo 'Database connected successfully' . PHP_EOL;
"
```

### 5. Web 服務訪問
```bash
# 本地測試
curl http://localhost:12340

# 外部測試
curl http://[PUBLIC_IP]:12340
```

預期返回登錄頁面 HTML：
```html
<div class="login-box">
    <h1>OmniDrop</h1>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        ...
    </form>
</div>
```

### 6. AWS IAM 訪問驗證
```bash
docker-compose exec -T web curl -s http://169.254.169.254/latest/meta-data/iam/security-credentials/
# 應返回 role 名稱

# 驗證 S3 訪問
aws s3 ls s3://omnidrop-files --region us-east-1
# 應列出 S3 bucket 內容
```

---

## 快速故障排除

### 症狀：403 Forbidden

**檢查步驟**:
```bash
# 1. 檢查 VirtualHost 配置
docker-compose exec -T web cat /etc/apache2/sites-available/omnidrop.conf

# 2. 檢查目錄權限
docker-compose exec -T web ls -la /var/www/html/src | head -5

# 3. 檢查文件所有者
docker-compose exec -T web stat /var/www/html/src/index.php

# 4. 查看 Apache 錯誤日誌
docker-compose logs web | grep -i "error\|403\|forbidden"
```

**快速修復**:
```bash
docker-compose exec -T web bash << 'EOF'
# 確保目錄可訪問
chmod 755 /var/www/html/src
chmod 644 /var/www/html/src/*.php

# 重啟 Apache
apache2ctl restart
EOF
```

---

### 症狀：無法連接數據庫

**檢查步驟**:
```bash
# 1. 驗證環境變數
docker-compose exec -T web printenv | grep MYSQL

# 2. 測試連接
docker-compose exec -T db mysql -u omniuser -pomnipassword!! -h db omnidrop -e "SELECT 1"

# 3. 檢查容器網絡
docker-compose exec -T web ping db
```

---

### 症狀：S3 上傳失敗

**檢查步驟**:
```bash
# 1. 驗證 S3 Bucket 訪問
docker-compose exec -T web bash << 'EOF'
aws s3 ls s3://omnidrop-files --region us-east-1
EOF

# 2. 檢查 IAM 角色
aws ec2 describe-instances --instance-ids [INSTANCE_ID] \
  --query 'Reservations[0].Instances[0].IamInstanceProfile'

# 3. 驗證 Bucket 策略
aws s3api get-bucket-policy --bucket omnidrop-files
```

---

### 症狀：容器啟動慢或失敗

**檢查步驟**:
```bash
# 1. 查看構建日誌
docker-compose build --no-cache

# 2. 查看啟動日誌
docker-compose logs

# 3. 檢查磁盤空間
df -h

# 4. 檢查 Docker 系統
docker system df
```

---

## 部署命令參考

### 首次部署

```bash
# 1. SSH 連接到 EC2
ssh -i hung-ssh-key.pem ec2-user@[PUBLIC_IP]

# 2. 克隆或上傳項目
git clone [REPO] /var/www/html/OmniDrop
cd /var/www/html/OmniDrop

# 3. 構建並啟動
docker-compose down
docker-compose up -d --build

# 4. 驗證
docker-compose ps
curl http://localhost:12340
```

### 後續更新

```bash
# 更新代碼後
docker-compose down
docker-compose up -d --build

# 或僅重啟容器
docker-compose restart web

# 應用 Apache 配置變更
docker-compose exec -T web apache2ctl restart
```

### 調試模式

```bash
# 進入容器 shell
docker-compose exec -T web bash

# 查看實時日誌
docker-compose logs -f web

# 執行特定命令
docker-compose exec -T web php /var/www/html/src/index.php
```

---

## 總結

此部署經歷的關鍵要點：

1. **使用較新的 Docker 基礎映像** (PHP 8.2 而非 7.4)
2. **正確的 Volume 掛載策略** (掛載整個項目目錄)
3. **適當的 Apache VirtualHost 配置** (指向正確的 DocumentRoot)
4. **Docker 網絡考慮** (使用 service names for internal communication)
5. **AWS IAM Role 優於硬編碼憑證** (安全最佳實踐)

成功部署後，OmniDrop 應能：
- ✅ 通過公網 IP 訪問
- ✅ 文件上傳到 S3
- ✅ 數據庫正常運作
- ✅ 用戶認證和授權功能完整
