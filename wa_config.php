<?php
// =========================================================
// wa_config.php  -  Pengaturan WhatsApp Gateway (Fonnte)
// 1. Daftar di https://fonnte.com, tambah device, scan QR dengan WhatsApp.
// 2. Salin TOKEN device ke FONNTE_TOKEN di bawah.
// 3. Isi ADMIN_PHONE dengan nomor WA admin (untuk notif user baru).
// Kosongkan token = notifikasi WA dimatikan (aplikasi tetap jalan).
// =========================================================
define('FONNTE_TOKEN', '71b3iP9DsBttkERh7Rqt');          // contoh: 'AbCdEf123456'
define('ADMIN_PHONE',  '089677358024');          // contoh: '081234567890'