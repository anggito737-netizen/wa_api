<?php
// =========================================================
// data_json.php
// Mengambil semua data dari tabel users lalu mengeluarkannya
// sebagai JSON. Diakses lewat: http://localhost/ujian_asts/data_json.php
// =========================================================
require "db_config.php";

header('Content-Type: application/json');

session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login sebagai admin."]);
    exit;
}

$sql = "SELECT id, name, nisn, ttl, gender, email, phone, address FROM users ORDER BY id ASC";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id"      => (int) $row["id"],
        "name"    => $row["name"],
        "nisn"    => $row["nisn"],
        "ttl"     => $row["ttl"],
        "gender"  => $row["gender"],
        "email"   => $row["email"],
        "phone"   => $row["phone"],
        "address" => $row["address"],
    ];
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();