<?php
// =========================================================
// Konfigurasi koneksi database.
// Sesuaikan $host, $user, $pass jika beda dengan XAMPP/Laragon default.
// =========================================================
$host = "localhost";
$user = "root";
$pass = "";        // default XAMPP kosong. Kalau Laragon biasanya juga kosong.
$dbname = "ujian_asts";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(["error" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8mb4");
