
<?php
// 添加CORS头
header("Access-Control-Allow-Origin: *"); // 允许任何域名进行访问
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // 允许的HTTP方法
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type"); // 允许的请求头

require 'vendor/autoload.php';

// 连接数据库
$servername = "127.0.0.1";
$username = "xxxx";
$password = "xxxx";
$dbname = "xxxx";
$dk = "3306";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname, $dk);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 处理GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 获取任务ID
    if (!isset($_GET['id'])) {
        echo json_encode([
            'code' => 400,
            'message' => '缺少任务ID',
            'data' => null
        ]);
        exit;
    }
    
    $taskId = $_GET['id'];
    
    // 准备SQL查询
    $sql = "SELECT * FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 获取任务数据
        $taskData = $result->fetch_assoc();
        
        // 返回成功响应
        echo json_encode([
            'code' => 0,
            'message' => '查询成功',
            'data' => $taskData
        ]);
    } else {
        // 没有找到对应ID的任务
        echo json_encode([
            'code' => 404,
            'message' => '未找到指定任务',
            'data' => null
        ]);
    }
    
    $stmt->close();
} else {
    // 非GET请求返回错误
    echo json_encode([
        'code' => 405,
        'message' => '方法不允许',
        'data' => null
    ]);
}

// 关闭数据库连接
$conn->close();

/**
 * 查询任务API
 * 
 * 请求地址: https://xxxx/api/query_task.php
 * 
 * 请求方法: GET
 * 
 * 请求参数:
 * - id: 整数，必填，任务ID
 * 
 * 返回格式: JSON
 * 
 * 成功返回示例:
 * {
 *     "code": 0,
 *     "message": "查询成功",
 *     "data": {
 *         "id": 123,
 *         "wav": "音频文件路径",
 *         "mp4": "视频文件路径",
 *         "task_status": "任务状态",
 *         "task_note": "任务描述",
 *         "output_url": "最终合成的成片url",
 *         "add_time": "添加时间",
 *         "update_time": "更新时间",
 *         "status": 1
 *     }
 * }
 * 
 * 错误返回示例:
 * {
 *     "code": 400/404/405,
 *     "message": "错误信息",
 *     "data": null
 * }
 */
