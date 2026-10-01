<?php
// =========================================================
// api/wa.php  -  Helper kirim WhatsApp via Fonnte + notifikasi admin
// =========================================================
require_once __DIR__ . '/../wa_config.php';
date_default_timezone_set('Asia/Jakarta');

function waNormalize($phone)
{
    $p = preg_replace('/[^0-9]/', '', (string) $phone);
    if ($p === '') return '';
    if (strpos($p, '62') === 0) return $p;
    if (strpos($p, '0') === 0) return '62' . substr($p, 1);
    if (strpos($p, '8') === 0) return '62' . $p;
    return $p;
}

function waRequest($target, $message, $verify)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => ['target' => $target, 'message' => $message, 'countryCode' => '62'],
        CURLOPT_HTTPHEADER => ['Authorization: ' . FONNTE_TOKEN],
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
    ]);
    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return [$res, $errno, $err];
}

// Mengembalikan ['ok' => bool, 'note' => string]
function waSend($phone, $message)
{
    if (FONNTE_TOKEN === '') {
        return ['ok' => false, 'note' => ' (Notif WA tidak dikirim: token Fonnte belum diisi)'];
    }
    $target = waNormalize($phone);
    if ($target === '') {
        return ['ok' => false, 'note' => ' (Notif WA tidak dikirim: nomor HP kosong)'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'note' => ' (Notif WA gagal: ekstensi cURL PHP belum aktif)'];
    }

    list($res, $errno, $err) = waRequest($target, $message, true);
    if ($errno === 60 || $errno === 77) {
        // XAMPP Windows sering belum punya CA bundle; coba ulang tanpa verifikasi SSL
        list($res, $errno, $err) = waRequest($target, $message, false);
    }
    if ($errno) {
        return ['ok' => false, 'note' => ' (Notif WA gagal: ' . $err . ')'];
    }

    $json = json_decode($res, true);
    if (is_array($json) && !empty($json['status'])) {
        return ['ok' => true, 'note' => ' Notifikasi WA terkirim.'];
    }
    $reason = is_array($json) ? ($json['reason'] ?? 'tidak diketahui') : 'respon tidak valid';
    return ['ok' => false, 'note' => ' (Notif WA gagal: ' . $reason . ')'];
}

function ensureNotifTable($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(30) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function addNotification($conn, $type, $message)
{
    ensureNotifTable($conn);
    $st = $conn->prepare("INSERT INTO notifications (type, message) VALUES (?, ?)");
    $st->bind_param("ss", $type, $message);
    $st->execute();
}