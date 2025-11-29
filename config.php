<?php
/**
 * 文件同步配置文件
 * 在这里配置文件存储相关的设置
 */

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
    // 示例: ['jpg', 'png', 'pdf', 'zip', 'doc', 'docx']
    'allowed_extensions' => [],

    // 禁止的文件类型（安全考虑）
    'forbidden_extensions' => ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'sh'],

    // 文件元数据存储文件
    'files_data_file' => __DIR__ . '/files_data.json',
];
