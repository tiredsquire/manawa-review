<?php
/**
 * POST /api/login.php
 *   Penjual : { slug, password }   → password = NIM ketua tim
 *   Panitia : { admin: true, password }
 *
 * Tidak ada login via email — penjual memilih tenant lalu memasukkan NIM.
 */
require __DIR__ . '/config.php';
require_method('POST');

$in      = json_in();
$pass    = (string) ($in['password'] ?? '');
$slug    = trim($in['slug'] ?? '');
$asAdmin = !empty($in['admin']);

if ($pass === '') {
    json_out(['error' => $asAdmin ? 'Kata sandi wajib diisi' : 'NIM wajib diisi'], 400);
}

if ($asAdmin || $slug === '') {
    // ---- Login panitia ----
    $stmt = db()->prepare("SELECT id, email, password_hash FROM akun WHERE peran = 'admin' LIMIT 1");
    $stmt->execute();
    $acc = $stmt->fetch();
    if (!$acc || !password_verify($pass, $acc['password_hash'])) {
        json_out(['error' => 'Kata sandi salah'], 401);
    }
    $_SESSION['akun'] = [
        'id'        => (int) $acc['id'],
        'role'      => 'admin',
        'email'     => $acc['email'],
        'tenant_id' => null,
    ];
    json_out(['role' => 'admin', 'email' => $acc['email'], 'tenant' => null]);
}

// ---- Login penjual (pilih tenant + NIM) ----
$stmt = db()->prepare(
    'SELECT a.id, a.email, a.password_hash,
            t.id AS tid, t.slug, t.nama_bisnis, t.tipe, t.spot, t.logo_url
       FROM akun a
       JOIN tenant t ON t.id = a.tenant_id
      WHERE t.slug = ? AND a.peran = "penjual"
      LIMIT 1'
);
$stmt->execute([$slug]);
$acc = $stmt->fetch();

if (!$acc || !password_verify($pass, $acc['password_hash'])) {
    json_out(['error' => 'NIM salah'], 401);
}

$_SESSION['akun'] = [
    'id'        => (int) $acc['id'],
    'role'      => 'penjual',
    'email'     => $acc['email'],
    'tenant_id' => (int) $acc['tid'],
];

json_out([
    'role'   => 'penjual',
    'email'  => $acc['email'],
    'tenant' => tenant_public([
        'id'          => $acc['tid'],
        'slug'        => $acc['slug'],
        'nama_bisnis' => $acc['nama_bisnis'],
        'tipe'        => $acc['tipe'],
        'spot'        => $acc['spot'],
        'logo_url'    => $acc['logo_url'],
    ]),
]);
