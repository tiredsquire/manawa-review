<?php
/**
 * GET /api/dashboard.php
 *   Penjual : data tenant sendiri.
 *   Admin   : ?tenant=<slug> untuk satu tenant, atau semua bila kosong.
 *
 * Response:
 *   {
 *     scope: 'seller' | 'admin',
 *     tenants: [ { slug, name, type, counts:{senyum,datar,sedih}, total } ],
 *     comments: [ { tenant, name, emote, kategori, komentar, at } ]
 *   }
 */
require __DIR__ . '/config.php';
require_method('GET');

$akun = require_login();
$pdo  = db();

// Tentukan filter tenant_id (NULL = semua, khusus admin).
$filterId = null;
if ($akun['role'] === 'penjual') {
    $filterId = $akun['tenant_id'];
} else { // admin
    $slug = $_GET['tenant'] ?? '';
    if ($slug !== '' && $slug !== 'all') {
        $st = $pdo->prepare('SELECT id FROM tenant WHERE slug = ?');
        $st->execute([$slug]);
        $filterId = $st->fetchColumn() ?: -1; // -1 = tidak ketemu → hasil kosong
    }
}

// ---- Ringkasan per tenant ----------------------------------
$sql = 'SELECT t.slug, t.nama_bisnis, t.tipe, t.logo_url,
               SUM(r.emote = "senyum") AS senyum,
               SUM(r.emote = "datar")  AS datar,
               SUM(r.emote = "sedih")  AS sedih,
               COUNT(r.id)             AS total
          FROM tenant t
          LEFT JOIN review r ON r.tenant_id = t.id';
$params = [];
if ($filterId !== null) {
    $sql .= ' WHERE t.id = ?';
    $params[] = $filterId;
}
$sql .= ' GROUP BY t.id ORDER BY t.nama_bisnis';

$st = $pdo->prepare($sql);
$st->execute($params);

$tenants = array_map(fn($row) => [
    'slug'   => $row['slug'],
    'name'   => $row['nama_bisnis'],
    'type'   => $row['tipe'],
    'logo'   => $row['logo_url'],
    'counts' => [
        'senyum' => (int) $row['senyum'],
        'datar'  => (int) $row['datar'],
        'sedih'  => (int) $row['sedih'],
    ],
    'total'  => (int) $row['total'],
], $st->fetchAll());

// ---- Daftar komentar ---------------------------------------
$csql = 'SELECT t.slug AS tenant, t.nama_bisnis AS name,
                r.emote, r.kategori, r.komentar, r.created_at
           FROM review r JOIN tenant t ON t.id = r.tenant_id
          WHERE r.komentar IS NOT NULL AND r.komentar <> ""';
$cparams = [];
if ($filterId !== null) {
    $csql .= ' AND r.tenant_id = ?';
    $cparams[] = $filterId;
}
$csql .= ' ORDER BY r.created_at DESC LIMIT 50';

$cst = $pdo->prepare($csql);
$cst->execute($cparams);

$comments = array_map(fn($row) => [
    'tenant'   => $row['tenant'],
    'name'     => $row['name'],
    'emote'    => $row['emote'],
    'kategori' => $row['kategori'],
    'komentar' => $row['komentar'],
    'at'       => $row['created_at'],
], $cst->fetchAll());

json_out([
    'scope'    => $akun['role'] === 'admin' ? 'admin' : 'seller',
    'tenants'  => $tenants,
    'comments' => $comments,
]);
