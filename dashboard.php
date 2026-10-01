<?php
// =========================================================
// api/dashboard.php  -  Statistik + notifikasi untuk dashboard admin
// =========================================================
session_start();
require "../db_config.php";
require __DIR__ . '/wa.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Akses ditolak."]);
    exit;
}

ensureNotifTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (is_array($in) && ($in['action'] ?? '') === 'read_all') {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    }
    echo json_encode(["success" => true]);
    exit;
}

$total = (int) $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$male = (int) $conn->query("SELECT COUNT(*) c FROM users WHERE gender = 'Laki-laki'")->fetch_assoc()['c'];
$female = (int) $conn->query("SELECT COUNT(*) c FROM users WHERE gender = 'Perempuan'")->fetch_assoc()['c'];

$accounts = 0;
try {
    $accounts = (int) $conn->query("SELECT COUNT(*) c FROM accounts WHERE role = 'user'")->fetch_assoc()['c'];
} catch (Throwable $e) {
    $accounts = 0;
}

$unread = (int) $conn->query("SELECT COUNT(*) c FROM notifications WHERE is_read = 0")->fetch_assoc()['c'];

$items = [];
$r = $conn->query("SELECT id, type, message, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 20");
while ($row = $r->fetch_assoc()) {
    $items[] = [
        "id" => (int) $row['id'],
        "type" => $row['type'],
        "message" => $row['message'],
        "is_read" => (int) $row['is_read'],
        "created_at" => $row['created_at'],
    ];
}

echo json_encode([
    "success" => true,
    "stats" => ["total" => $total, "male" => $male, "female" => $female, "accounts" => $accounts],
    "unread" => $unread,
    "items" => $items,
], JSON_UNESCAPED_UNICODE);