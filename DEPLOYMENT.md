# 部署指南

本文档介绍如何将此项目部署到你的服务器。

## 方法1：手动部署（最简单）

### 首次部署

1. SSH登录到服务器：
```bash
ssh user@your-server.com
```

2. 进入网站目录并克隆代码：
```bash
cd /var/www/html  # 或你的网站目录
git clone https://github.com/godblessbb/clip.git
cd clip
```

3. 设置权限：
```bash
chmod -R 755 .
chmod 666 files_data.json clipboard_data.json
chmod 777 uploads
```

4. 如果需要，切换到指定分支：
```bash
git checkout claude/file-sync-feature-019kLqYX6k5SdsgCct2Y5JFZ
```

### 更新代码

```bash
cd /var/www/html/clip
git pull
```

---

## 方法2：GitHub Actions自动部署（推荐）

### 配置步骤

#### 1. 在服务器上生成SSH密钥（如果还没有）

```bash
ssh-keygen -t rsa -b 4096 -C "deploy@your-server"
# 按回车使用默认路径：~/.ssh/id_rsa
# 可以设置密码或留空
```

查看公钥并添加到服务器的authorized_keys：
```bash
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

获取私钥（稍后用于GitHub Secrets）：
```bash
cat ~/.ssh/id_rsa
```

#### 2. 在服务器上首次克隆仓库

```bash
cd /var/www/html  # 你的网站目录
git clone https://github.com/godblessbb/clip.git
cd clip
git checkout claude/file-sync-feature-019kLqYX6k5SdsgCct2Y5JFZ
```

#### 3. 在GitHub仓库中配置Secrets

进入仓库 Settings → Secrets and variables → Actions → New repository secret

添加以下secrets：

- **SERVER_HOST**: 服务器IP或域名（例如：`123.45.67.89` 或 `example.com`）
- **SERVER_USER**: SSH用户名（例如：`root` 或 `www-data`）
- **SERVER_SSH_KEY**: 服务器的SSH私钥（步骤1中的 `~/.ssh/id_rsa` 内容）
- **SERVER_PORT**: SSH端口（默认22，如果是默认可以不设置）
- **DEPLOY_PATH**: 服务器上的项目路径（例如：`/var/www/html/clip`）

#### 4. 测试自动部署

推送代码到GitHub后，GitHub Actions会自动部署到服务器。

查看部署状态：
- 进入仓库的 Actions 标签页
- 查看最新的工作流运行记录

---

## 方法3：使用部署脚本

创建一个简单的部署脚本：

### 在本地创建 `deploy.sh`

```bash
#!/bin/bash

# 配置
SERVER_USER="your-user"
SERVER_HOST="your-server.com"
SERVER_PATH="/var/www/html/clip"
BRANCH="claude/file-sync-feature-019kLqYX6k5SdsgCct2Y5JFZ"

echo "开始部署到服务器..."

ssh $SERVER_USER@$SERVER_HOST << EOF
  cd $SERVER_PATH
  echo "拉取最新代码..."
  git pull origin $BRANCH
  echo "设置权限..."
  chmod -R 755 .
  chmod 666 files_data.json clipboard_data.json 2>/dev/null || true
  chmod 777 uploads 2>/dev/null || true
  echo "部署完成！"
EOF

echo "部署成功！"
```

### 使用方法

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 方法4：Webhook自动部署

在服务器上创建webhook接收脚本：

### 1. 创建webhook处理脚本 `/var/www/webhook.php`

```php
<?php
$secret = 'your-webhook-secret';  // 在GitHub设置中配置的密钥

// 验证签名
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($signature, $expected)) {
    http_response_code(403);
    die('Invalid signature');
}

// 执行部署
$output = shell_exec('cd /var/www/html/clip && git pull 2>&1');
echo $output;
?>
```

### 2. 在GitHub配置Webhook

仓库 Settings → Webhooks → Add webhook

- Payload URL: `http://your-server.com/webhook.php`
- Content type: `application/json`
- Secret: 设置一个密钥（与上面脚本中的相同）
- Events: 选择 `Just the push event`

---

## 服务器环境要求

### PHP配置

确保服务器PHP配置满足需求：

```ini
file_uploads = On
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

### 文件权限

```bash
# 代码文件
chmod -R 755 /var/www/html/clip

# 数据文件（需要可写）
chmod 666 /var/www/html/clip/files_data.json
chmod 666 /var/www/html/clip/clipboard_data.json

# 上传目录（需要可写）
chmod 777 /var/www/html/clip/uploads
```

### Web服务器配置

#### Apache (.htaccess)

如果使用Apache，确保启用了 `.htaccess` 文件。

#### Nginx

Nginx配置示例：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/clip;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 限制上传文件大小
    client_max_body_size 50M;
}
```

---

## 故障排查

### 部署后文件上传失败

1. 检查uploads目录权限：
```bash
ls -la /var/www/html/clip/uploads
chmod 777 /var/www/html/clip/uploads
```

2. 检查PHP配置：
```bash
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

3. 检查Web服务器配置（Nginx的client_max_body_size）

### Git pull失败

1. 检查Git配置：
```bash
cd /var/www/html/clip
git status
git remote -v
```

2. 重置本地更改：
```bash
git reset --hard origin/claude/file-sync-feature-019kLqYX6k5SdsgCct2Y5JFZ
```

### 权限问题

确保Web服务器用户（通常是www-data或nginx）有正确的权限：

```bash
chown -R www-data:www-data /var/www/html/clip
```

---

## 推荐方案

对于此项目，**推荐使用方法1或方法2**：

- **开发/测试阶段**：使用方法1（手动部署），简单快速
- **生产环境**：使用方法2（GitHub Actions），自动化且可靠

选择合适的方法根据你的需求和服务器环境决定！
