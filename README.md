# 共享剪贴板 & 文件同步

这是一个支持文本分享和文件同步的Web应用。可以在多个设备间轻松分享文本内容和文件。

## 功能特性

### 文本分享
- 发布文本内容，所有人可以查看和复制
- 自动记录复制次数
- 显示剩余有效时间
- 24小时后自动删除

### 文件同步（新增）
- 上传文件到服务器
- 文件存储24小时后自动删除
- 任何人都可以下载分享的文件
- 显示文件大小、类型和下载次数
- 实时显示上传进度
- 支持文件夹大小限制和单个文件大小限制

## 配置说明

所有文件同步相关的配置都在 `config.php` 文件中：

```php
return [
    // 文件存储目录（相对路径或绝对路径）
    'upload_dir' => __DIR__ . '/uploads',

    // 单个文件大小限制（字节），默认50MB
    'max_file_size' => 50 * 1024 * 1024,

    // 文件夹总大小限制（字节），默认500MB
    'max_folder_size' => 500 * 1024 * 1024,

    // 文件过期时间（秒），默认24小时
    'file_expire_time' => 24 * 60 * 60,

    // 允许的文件类型（留空表示允许所有类型）
    'allowed_extensions' => [],

    // 禁止的文件类型（安全考虑）
    'forbidden_extensions' => ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'sh'],

    // 文件元数据存储文件
    'files_data_file' => __DIR__ . '/files_data.json',
];
```

### 配置项说明

1. **upload_dir**: 文件存储的位置
   - 可以是绝对路径：`/var/www/uploads`
   - 或相对路径：`__DIR__ . '/uploads'`
   - 确保该目录有写入权限（755或777）

2. **max_file_size**: 单个文件最大大小
   - 单位：字节
   - 示例：`50 * 1024 * 1024` = 50MB
   - 注意：也要检查PHP配置中的 `upload_max_filesize` 和 `post_max_size`

3. **max_folder_size**: 文件夹总大小限制
   - 单位：字节
   - 示例：`500 * 1024 * 1024` = 500MB
   - 当文件夹总大小超过此限制时，会拒绝新的上传

4. **file_expire_time**: 文件过期时间
   - 单位：秒
   - 默认：`24 * 60 * 60` = 24小时
   - 超过此时间的文件会被自动删除

5. **allowed_extensions**: 允许上传的文件类型
   - 留空数组 `[]` 表示允许所有类型
   - 示例：`['jpg', 'png', 'pdf', 'zip']` 只允许这些类型

6. **forbidden_extensions**: 禁止上传的文件类型
   - 出于安全考虑，默认禁止可执行文件
   - 建议保留默认设置

## 安装和部署

1. 确保PHP环境支持文件上传
2. 创建或确认 `uploads` 目录存在并有写入权限：
   ```bash
   mkdir uploads
   chmod 755 uploads
   ```
3. 根据需要修改 `config.php` 中的配置
4. 访问 `index.html` 开始使用

## PHP配置建议

在 `php.ini` 中确保以下设置：

```ini
file_uploads = On
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

## 安全建议

1. 定期清理过期文件（每次访问API时会自动清理）
2. 不要将 `uploads` 目录设置为可直接访问（通过API下载更安全）
3. 保持 `forbidden_extensions` 配置，防止上传恶意文件
4. 根据实际需求调整文件大小限制
5. 考虑添加用户认证机制（当前版本是公开的）

## 文件结构

```
.
├── index.html          # 主页面
├── api.php             # 文本分享API
├── file_api.php        # 文件同步API
├── config.php          # 配置文件
├── clipboard_data.json # 文本数据存储
├── files_data.json     # 文件元数据存储
├── uploads/            # 文件存储目录
└── README.md          # 说明文档
```

## 使用说明

### 文本分享
1. 在文本框中输入要分享的内容
2. 点击"发布内容"按钮
3. 其他人可以查看并复制该内容

### 文件同步
1. 点击"选择文件"按钮
2. 选择要上传的文件
3. 文件会自动上传，显示上传进度
4. 上传成功后，文件会出现在"共享文件"列表中
5. 任何人都可以点击"下载"按钮下载文件

## 自动清理

- 文本内容：24小时后自动删除（如果被复制过，从最后复制时间开始计算）
- 文件：24小时后自动删除
- 每次访问API时会自动检查并清理过期内容

## 技术栈

- 前端：HTML5 + CSS3 + JavaScript (原生)
- 后端：PHP
- 数据存储：JSON文件

## 许可证

MIT License
