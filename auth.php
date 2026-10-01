<?php
// =========================================================
// api/auth.php
// Login, register, logout, dan cek sesi (me).
// Tabel `accounts` dibuat otomatis kalau belum ada, dan akun
// admin bawaan (admin / admin123) dibuat otomatis.
// =========================================================
session_start();
require "../db_config.php";
require __DIR__ . '/wa.php';

header('Content-Type: application/json');

function out($ok, $message = '', $extra = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(array_merge(["success" => $ok, "message" => $message], $extra));
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    user_id INT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$cek = $conn->query("SELECT id FROM accounts WHERE username = 'admin' LIMIT 1");
if ($cek && $cek->num_rows === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $st = $conn->prepare("INSERT INTO accounts (username, password_hash, role) VALUES ('admin', ?, 'admin')");
    $st->bind_param("s", $hash);
    $st->execute();
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    $in = [];
}
$action = $in['action'] ?? ($_GET['action'] ?? '');

// ---------- ME ----------
if ($action === 'me') {
    if (empty($_SESSION['account_id'])) {
        out(false, 'Belum login.', [], 401);
    }
    $st = $conn->prepare("SELECT id FROM accounts WHERE id = ?");
    $st->bind_param("i", $_SESSION['account_id']);
    $st->execute();
    if ($st->get_result()->num_rows === 0) {
        $_SESSION = [];
        session_destroy();
        out(false, 'Akun tidak ditemukan (mungkin sudah dihapus).', [], 401);
    }
    $profile = null;
    if (!empty($_SESSION['user_id'])) {
        $st = $conn->prepare("SELECT id, name, nisn, ttl, gender, email, phone, address FROM users WHERE id = ?");
        $st->bind_param("i", $_SESSION['user_id']);
        $st->execute();
        $profile = $st->get_result()->fetch_assoc();
    }
    out(true, '', [
        "username" => $_SESSION['username'],
        "role" => $_SESSION['role'],
        "profile" => $profile
    ]);
}

// ---------- UPDATE PROFIL (oleh user sendiri) ----------
if ($action === 'update_profile') {
    if (empty($_SESSION['account_id']) || empty($_SESSION['user_id'])) {
        out(false, 'Belum login.', [], 401);
    }
    $uid = (int) $_SESSION['user_id'];
    $d = [];
    foreach (['name', 'ttl', 'gender', 'email', 'phone', 'address'] as $k) {
        $d[$k] = trim($in[$k] ?? '');
        if ($d[$k] === '') {
            out(false, 'Semua data wajib diisi.', [], 422);
        }
    }
    if (!in_array($d['gender'], ['Laki-laki', 'Perempuan'], true)) {
        out(false, 'Gender tidak valid.', [], 422);
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        out(false, 'Format email tidak valid.', [], 422);
    }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $d['phone'])) {
        out(false, 'No. telepon tidak valid (8-20 digit).', [], 422);
    }

    $q = $conn->prepare("SELECT id, name, nisn, ttl, gender, email, phone, address FROM users WHERE id = ?");
    $q->bind_param("i", $uid);
    $q->execute();
    $old = $q->get_result()->fetch_assoc();
    if (!$old) {
        out(false, 'Data profil tidak ditemukan.', [], 404);
    }

    $labels = ['name' => 'Nama', 'ttl' => 'TTL', 'gender' => 'Gender', 'email' => 'Email', 'phone' => 'No. Telepon', 'address' => 'Alamat'];
    $changes = [];
    foreach ($labels as $k => $label) {
        if ((string) $old[$k] !== $d[$k]) {
            $changes[] = "- " . $label . ": " . $old[$k] . " -> " . $d[$k];
        }
    }
    if (count($changes) === 0) {
        out(true, 'Tidak ada data yang berubah.', ["profile" => $old]);
    }

    $st = $conn->prepare("UPDATE users SET name = ?, ttl = ?, gender = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $st->bind_param("ssssssi", $d['name'], $d['ttl'], $d['gender'], $d['email'], $d['phone'], $d['address'], $uid);
    if (!$st->execute()) {
        out(false, 'Gagal menyimpan perubahan.', [], 500);
    }

    $note = waSend($d['phone'], "Halo " . $d['name'] . ", data Anda berhasil diubah:\n" . implode("\n", $changes))['note'];
    addNotification($conn, 'edit_profile', $d['name'] . " mengubah profilnya (" . count($changes) . " data).");

    $profile = array_merge($old, $d);
    out(true, 'Data Anda berhasil diubah.' . $note, ["profile" => $profile]);
}

// ---------- LOGOUT ----------
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    out(true, 'Logout berhasil.');
}

// ---------- LOGIN ----------
if ($action === 'login') {
    $username = trim($in['username'] ?? '');
    $password = $in['password'] ?? '';
    $st = $conn->prepare("SELECT id, username, password_hash, role, user_id FROM accounts WHERE username = ?");
    $st->bind_param("s", $username);
    $st->execute();
    $acc = $st->get_result()->fetch_assoc();

    if (!$acc || !password_verify($password, $acc['password_hash'])) {
        out(false, 'Username atau password salah.', [], 401);
    }

    session_regenerate_id(true);
    $_SESSION['account_id'] = $acc['id'];
    $_SESSION['username'] = $acc['username'];
    $_SESSION['role'] = $acc['role'];
    $_SESSION['user_id'] = $acc['user_id'];
    $note = '';
    $waktu = date('d-m-Y H:i');
    if ($acc['role'] === 'admin') {
        if (ADMIN_PHONE !== '') {
            $note = waSend(ADMIN_PHONE, "Admin '" . $acc['username'] . "' berhasil login pada " . $waktu . ".")['note'];
        }
    } elseif (!empty($acc['user_id'])) {
        $q = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
        $q->bind_param("i", $acc['user_id']);
        $q->execute();
        $p = $q->get_result()->fetch_assoc();
        if ($p) {
            $note = waSend($p['phone'], "Halo " . $p['name'] . ", Anda berhasil login pada " . $waktu . ".")['note'];
        }
    }
    out(true, 'Anda berhasil login.' . $note, ["role" => $acc['role']]);
}

// ---------- REGISTER ----------
if ($action === 'register') {
    $username = trim($in['username'] ?? '');
    $password = $in['password'] ?? '';
    $d = [];
    foreach (['name', 'nisn', 'ttl', 'gender', 'email', 'phone', 'address'] as $k) {
        $d[$k] = trim($in[$k] ?? '');
        if ($d[$k] === '') {
            out(false, 'Semua data wajib diisi.', [], 422);
        }
    }

    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        out(false, 'Username 3-30 karakter (huruf, angka, titik, atau underscore).', [], 422);
    }
    if (strlen($password) < 6) {
        out(false, 'Password minimal 6 karakter.', [], 422);
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        out(false, 'Format email tidak valid.', [], 422);
    }

    $st = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
    $st->bind_param("s", $username);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        out(false, 'Username sudah dipakai.', [], 409);
    }

    $st = $conn->prepare("SELECT id FROM users WHERE nisn = ?");
    $st->bind_param("s", $d['nisn']);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        out(false, 'NISN sudah terdaftar.', [], 409);
    }

    $st = $conn->prepare("INSERT INTO users (name, nisn, ttl, gender, email, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param("sssssss", $d['name'], $d['nisn'], $d['ttl'], $d['gender'], $d['email'], $d['phone'], $d['address']);
    if (!$st->execute()) {
        out(false, 'Gagal menyimpan data diri.', [], 500);
    }
    $newId = $conn->insert_id;

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $conn->prepare("INSERT INTO accounts (username, password_hash, role, user_id) VALUES (?, ?, 'user', ?)");
    $st->bind_param("ssi", $username, $hash, $newId);
    if (!$st->execute()) {
        $conn->query("DELETE FROM users WHERE id = " . (int)$newId);
        out(false, 'Gagal membuat akun.', [], 500);
    }
    $note = waSend($d['phone'], "Halo " . $d['name'] . ", registrasi akun Anda berhasil. Selamat datang!")['note'];
    addNotification($conn, 'new_user', "User baru terdaftar: " . $d['name'] . " (NISN " . $d['nisn'] . ", HP " . $d['phone'] . ")");
    if (ADMIN_PHONE !== '') {
        waSend(ADMIN_PHONE, "User baru terdaftar:\nNama: " . $d['name'] . "\nNISN: " . $d['nisn'] . "\nHP: " . $d['phone'] . "\nEmail: " . $d['email']);
    }
    out(true, 'Registrasi berhasil. Silakan login.' . $note);
}

out(false, 'Aksi tidak dikenal.', [], 400);