<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 加载配置
$config = require_once 'config.php';

// 确保上传目录存在
if (!file_exists($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0755, true);
}

// 确保文件元数据文件存在
if (!file_exists($config['files_data_file'])) {
    file_put_contents($config['files_data_file'], json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 读取文件元数据
function readFilesData($file) {
    $data = file_get_contents($file);
    if ($data === false) {
        return [];
    }
    $decoded = json_decode($data, true);
    return $decoded === null ? [] : $decoded;
}

// 写入文件元数据
function writeFilesData($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

// 获取文件夹总大小
function getFolderSize($dir) {
    $size = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        // 如果目录为空或无法读取，返回0
        return 0;
    }
    return $size;
}

// 清理过期文件
function cleanExpiredFiles(&$filesData, $config) {
    $now = time();
    $cleaned = false;

    foreach ($filesData as $index => $file) {
        $uploadTime = strtotime($file['uploadTime']);
        $expireTime = $uploadTime + $config['file_expire_time'];

        if ($now > $expireTime) {
            // 删除物理文件
            $filePath = $config['upload_dir'] . '/' . $file['savedName'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 从元数据中移除
            unset($filesData[$index]);
            $cleaned = true;
        }
    }

    if ($cleaned) {
        $filesData = array_values($filesData);
    }

    return $cleaned;
}

// 生成唯一文件名
function generateFileName($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid(time() . '_') . '.' . $ext;
}

// 格式化文件大小
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// JSON响应
function jsonResponse($success, $message = '', $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $result = ['success' => $success];
    if ($message) $result['message'] = $message;
    if ($data !== null) $result['data'] = $data;
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// 文件下载响应
function downloadFile($filePath, $originalName) {
    if (!file_exists($filePath)) {
        jsonResponse(false, '文件不存在');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

try {
    // 处理文件上传
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = $_FILES['file'];

        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(false, '文件上传失败: ' . $file['error']);
        }

        // 检查文件大小
        if ($file['size'] > $config['max_file_size']) {
            jsonResponse(false, '文件大小超过限制（最大 ' . formatFileSize($config['max_file_size']) . '）');
        }

        // 检查文件类型
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $config['forbidden_extensions'])) {
            jsonResponse(false, '不允许上传此类型的文件');
        }

        if (!empty($config['allowed_extensions']) && !in_array($ext, $config['allowed_extensions'])) {
            jsonResponse(false, '只允许上传以下类型的文件: ' . implode(', ', $config['allowed_extensions']));
        }

        // 检查文件夹大小
        $currentFolderSize = getFolderSize($config['upload_dir']);
        if ($currentFolderSize + $file['size'] > $config['max_folder_size']) {
            jsonResponse(false, '存储空间不足（文件夹已使用 ' . formatFileSize($currentFolderSize) . '，最大 ' . formatFileSize($config['max_folder_size']) . '）');
        }

        // 生成唯一文件名
        $savedName = generateFileName($file['name']);
        $filePath = $config['upload_dir'] . '/' . $savedName;

        // 移动上传的文件
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            jsonResponse(false, '文件保存失败');
        }

        // 读取文件元数据
        $filesData = readFilesData($config['files_data_file']);

        // 清理过期文件
        cleanExpiredFiles($filesData, $config);

        // 添加新文件信息
        $fileInfo = [
            'id' => uniqid(time()),
            'originalName' => $file['name'],
            'savedName' => $savedName,
            'size' => $file['size'],
            'sizeFormatted' => formatFileSize($file['size']),
            'type' => $file['type'],
            'uploadTime' => date('Y-m-d H:i:s'),
            'downloadCount' => 0
        ];

        array_unshift($filesData, $fileInfo);

        // 保存元数据
        if (writeFilesData($config['files_data_file'], $filesData)) {
            jsonResponse(true, '文件上传成功', $fileInfo);
        } else {
            // 如果元数据保存失败，删除已上传的文件
            unlink($filePath);
            jsonResponse(false, '元数据保存失败');
        }
    }

    // 处理其他请求（GET和POST JSON）
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    if ($requestMethod === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];

        // 下载文件
        if ($action === 'download' && isset($_GET['id'])) {
            $id = $_GET['id'];
            $filesData = readFilesData($config['files_data_file']);

            foreach ($filesData as &$file) {
                if ($file['id'] === $id) {
                    $filePath = $config['upload_dir'] . '/' . $file['savedName'];

                    // 增加下载计数
                    $file['downloadCount']++;
                    writeFilesData($config['files_data_file'], $filesData);

                    // 开始下载
                    downloadFile($filePath, $file['originalName']);
                }
            }

            jsonResponse(false, '文件不存在或已过期');
        }
    }

    if ($requestMethod === 'POST') {
        $input = file_get_contents('php://input');
        $request = json_decode($input, true);

        if (!$request || !isset($request['action'])) {
            jsonResponse(false, '无效的请求');
        }

        $action = $request['action'];
        $filesData = readFilesData($config['files_data_file']);

        // 清理过期文件
        $needSave = cleanExpiredFiles($filesData, $config);

        switch ($action) {
            case 'list':
                // 获取文件列表
                if ($needSave) {
                    writeFilesData($config['files_data_file'], $filesData);
                }

                // 计算当前文件夹使用情况
                $folderSize = getFolderSize($config['upload_dir']);
                $folderInfo = [
                    'currentSize' => $folderSize,
                    'currentSizeFormatted' => formatFileSize($folderSize),
                    'maxSize' => $config['max_folder_size'],
                    'maxSizeFormatted' => formatFileSize($config['max_folder_size']),
                    'usagePercent' => round(($folderSize / $config['max_folder_size']) * 100, 2)
                ];

                jsonResponse(true, '', [
                    'files' => $filesData,
                    'folderInfo' => $folderInfo
                ]);
                break;

            case 'delete':
                // 删除文件
                if (!isset($request['id'])) {
                    jsonResponse(false, '缺少ID参数');
                }

                $id = $request['id'];
                $found = false;

                foreach ($filesData as $index => $file) {
                    if ($file['id'] === $id) {
                        // 删除物理文件
                        $filePath = $config['upload_dir'] . '/' . $file['savedName'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }

                        // 从元数据中移除
                        unset($filesData[$index]);
                        $filesData = array_values($filesData);
                        $found = true;

                        if (writeFilesData($config['files_data_file'], $filesData)) {
                            jsonResponse(true, '文件删除成功');
                        } else {
                            jsonResponse(false, '删除失败');
                        }
                        break;
                    }
                }

                if (!$found) {
                    jsonResponse(false, '文件不存在');
                }
                break;

            default:
                jsonResponse(false, '未知操作');
        }
    }

    jsonResponse(false, '无效的请求');

} catch (Exception $e) {
    jsonResponse(false, '服务器错误: ' . $e->getMessage());
}
