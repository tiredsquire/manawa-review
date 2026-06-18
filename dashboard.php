<?php
/** GET /api/categories.php?tipe=fnb|non_fnb — daftar kategori aktif terurut. */
require __DIR__ . '/config.php';
require_method('GET');

$tipe = $_GET['tipe'] ?? '';
if (!in_array($tipe, ['fnb', 'non_fnb'], true)) {
    json_out(['error' => 'Parameter tipe harus fnb atau non_fnb'], 400);
}

$stmt = db()->prepare(
    'SELECT nama FROM kategori WHERE tipe = ? AND aktif = 1 ORDER BY urutan, nama'
);
$stmt->execute([$tipe]);

json_out(['categories' => array_column($stmt->fetchAll(), 'nama')]);
