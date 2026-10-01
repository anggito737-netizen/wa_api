<?php
// =========================================================
// api/users.php
// Endpoint REST API. Diakses lewat:
// http://localhost/ujian_asts/api/users.php
// =========================================================
require "../db_config.php";
require __DIR__ . '/wa.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Akses ditolak. Silakan login sebagai admin."]);
    exit;
}

function readJsonInput()
{
    $raw = file_get_contents('php://input');
    if (empty(trim($raw))) {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        parse_str($raw, $data);
    }

    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
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
    exit;
}

$data = readJsonInput();

if ($method === 'POST') {
    $required = ['name', 'nisn', 'ttl', 'gender', 'email', 'phone', 'address'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
            $conn->close();
            exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO users (name, nisn, ttl, gender, email, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $data['name'], $data['nisn'], $data['ttl'], $data['gender'], $data['email'], $data['phone'], $data['address']);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambah data: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil ditambahkan.',
        'id' => $conn->insert_id
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'PUT') {
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
        $conn->close();
        exit;
    }

    $required = ['name', 'nisn', 'ttl', 'gender', 'email', 'phone', 'address'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
            $conn->close();
            exit;
        }
    }

    $q = $conn->prepare("SELECT name, nisn, ttl, gender, email, phone, address FROM users WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $old = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$old) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, nisn = ?, ttl = ?, gender = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $data['name'], $data['nisn'], $data['ttl'], $data['gender'], $data['email'], $data['phone'], $data['address'], $id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $labels = ['name' => 'Nama', 'nisn' => 'NISN', 'ttl' => 'TTL', 'gender' => 'Gender', 'email' => 'Email', 'phone' => 'No. Telepon', 'address' => 'Alamat'];
    $changes = [];
    foreach ($labels as $k => $label) {
        $new = trim((string) $data[$k]);
        if ((string) $old[$k] !== $new) {
            $changes[] = "- " . $label . ": " . $old[$k] . " -> " . $new;
        }
    }

    $note = '';
    $msg = 'Data berhasil diperbarui.';
    if (count($changes) === 0) {
        $msg = 'Tidak ada data yang berubah.';
    } else {
        $wa = waSend($data['phone'], "Halo " . trim($data['name']) . ", data Anda berhasil diubah:\n" . implode("\n", $changes));
        $note = $wa['note'];
    }

    echo json_encode([
        'success' => true,
        'message' => $msg . $note
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'DELETE') {
    $id = isset($data['id']) ? (int) $data['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
        $conn->close();
        exit;
    }

    $q = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $old = $q->get_result()->fetch_assoc();
    $q->close();

    try {
        $conn->query("DELETE FROM accounts WHERE user_id = " . (int) $id);
    } catch (Throwable $e) {
        // tabel accounts belum ada, abaikan
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $note = '';
    if ($old) {
        $wa = waSend($old['phone'], "Halo " . $old['name'] . ", akun Anda telah dihapus.");
        $note = $wa['note'];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Akun berhasil dihapus.' . $note
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
$conn->close();