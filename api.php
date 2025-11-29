<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 数据文件路径
$dataFile = 'clipboard_data.json';

// 确保数据文件存在
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 读取数据
function readData($file) {
    $data = file_get_contents($file);
    if ($data === false) {
        return [];
    }
    $decoded = json_decode($data, true);
    return $decoded === null ? [] : $decoded;
}

// 写入数据
function writeData($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

// 清理过期数据
function cleanExpiredData(&$data) {
    $now = time();
    $cleaned = false;
    
    foreach ($data as $index => $item) {
        $created = strtotime($item['createdAt']);
        $expiredTime = $created + (24 * 60 * 60); // 24小时后过期
        
        // 如果超过24小时且没有被复制过，删除
        if ($now > $expiredTime && $item['copyCount'] == 0) {
            unset($data[$index]);
            $cleaned = true;
            continue;
        }
        
        // 如果超过24小时但被复制过，检查最后复制时间
        if ($now > $expiredTime && isset($item['lastCopied'])) {
            $lastCopied = strtotime($item['lastCopied']);
            $copyExpiredTime = $lastCopied + (24 * 60 * 60);
            if ($now > $copyExpiredTime) {
                unset($data[$index]);
                $cleaned = true;
            }
        }
    }
    
    if ($cleaned) {
        $data = array_values($data); // 重新索引数组
    }
    
    return $cleaned;
}

// 生成ID
function generateId() {
    return uniqid(time());
}

// 响应函数
function response($success, $message = '', $data = null) {
    $result = ['success' => $success];
    if ($message) $result['message'] = $message;
    if ($data !== null) $result['data'] = $data;
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 获取请求数据
    $input = file_get_contents('php://input');
    $request = json_decode($input, true);
    
    if (!$request || !isset($request['action'])) {
        response(false, '无效的请求');
    }
    
    $action = $request['action'];
    
    // 读取现有数据
    $data = readData($dataFile);
    
    // 清理过期数据
    $needSave = cleanExpiredData($data);
    
    switch ($action) {
        case 'add':
            // 添加新内容
            if (!isset($request['content']) || trim($request['content']) === '') {
                response(false, '内容不能为空');
            }
            
            $newItem = [
                'id' => generateId(),
                'content' => trim($request['content']),
                'createdAt' => date('Y-m-d H:i:s'),
                'copyCount' => 0,
                'lastCopied' => null
            ];
            
            array_unshift($data, $newItem); // 添加到数组开头
            
            if (writeData($dataFile, $data)) {
                response(true, '发布成功', $newItem);
            } else {
                response(false, '保存失败');
            }
            break;
            
        case 'get':
            // 获取所有数据
            if ($needSave) {
                writeData($dataFile, $data);
            }
            response(true, '', $data);
            break;
            
        case 'copy':
            // 复制内容
            if (!isset($request['id'])) {
                response(false, '缺少ID参数');
            }
            
            $id = $request['id'];
            $found = false;
            
            foreach ($data as &$item) {
                if ($item['id'] === $id) {
                    $item['copyCount']++;
                    $item['lastCopied'] = date('Y-m-d H:i:s');
                    $found = true;
                    
                    if (writeData($dataFile, $data)) {
                        response(true, '复制成功', ['content' => $item['content']]);
                    } else {
                        response(false, '更新失败');
                    }
                    break;
                }
            }
            
            if (!$found) {
                response(false, '内容不存在或已过期');
            }
            break;
            
        case 'delete':
            // 删除内容
            if (!isset($request['id'])) {
                response(false, '缺少ID参数');
            }
            
            $id = $request['id'];
            $found = false;
            
            foreach ($data as $index => $item) {
                if ($item['id'] === $id) {
                    unset($data[$index]);
                    $data = array_values($data); // 重新索引
                    $found = true;
                    
                    if (writeData($dataFile, $data)) {
                        response(true, '删除成功');
                    } else {
                        response(false, '删除失败');
                    }
                    break;
                }
            }
            
            if (!$found) {
                response(false, '内容不存在');
            }
            break;
            
        default:
            response(false, '未知操作');
    }
    
} catch (Exception $e) {
    response(false, '服务器错误: ' . $e->getMessage());
}