<?php
/** GET /api/session.php — kembalikan akun login saat ini (untuk restore sesi). */
require __DIR__ . '/config.php';
require_method('GET');

if (empty($_SESSION['akun'])) {
    json_out(['authenticated' => false]);
}

$akun   = $_SESSION['akun'];
$tenant = null;
if ($akun['tenant_id'] !== null) {
    $stmt = db()->prepare('SELECT id, slug, nama_bisnis, tipe, spot, logo_url FROM tenant WHERE id = ?');
    $stmt->execute([$akun['tenant_id']]);
    if ($t = $stmt->fetch()) {
        $tenant = tenant_public($t);
    }
}

json_out([
    'authenticated' => true,
    'role'          => $akun['role'],
    'email'         => $akun['email'],
    'tenant'        => $tenant,
]);
