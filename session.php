<?php
/**
 * POST /api/reviews.php — buat review baru (kiosk).
 *   body: { emote, kategori, komentar? }
 *   kategori : satu nama, atau beberapa nama dipisah koma ("Rasa, Pelayanan").
 *   tenant diambil dari sesi penjual (bukan dari klien) demi keamanan.
 */
require __DIR__ . '/config.php';
require_method('POST');

$akun = require_login();
if ($akun['role'] !== 'penjual' || $akun['tenant_id'] === null) {
    json_out(['error' => 'Hanya akun penjual yang dapat mengirim review'], 403);
}

$in       = json_in();
$emote    = $in['emote']    ?? '';
$kategori = trim($in['kategori'] ?? '');
$komentar = trim($in['komentar'] ?? '');

if (!in_array($emote, ['senyum', 'datar', 'sedih'], true)) {
    json_out(['error' => 'Emote tidak valid'], 400);
}
if ($kategori === '') {
    json_out(['error' => 'Kategori wajib dipilih'], 400);
}

// Dukung multi-pilih: kategori dikirim sebagai daftar nama dipisah koma
// (mis. "Rasa, Pelayanan"). Pisahkan, bersihkan, dan validasi tiap nama.
$names = array_values(array_filter(
    array_map('trim', explode(',', $kategori)),
    fn($n) => $n !== ''
));
if (!$names) {
    json_out(['error' => 'Kategori wajib dipilih'], 400);
}

// Validasi tiap kategori terhadap tipe tenant.
$st = db()->prepare('SELECT tipe FROM tenant WHERE id = ?');
$st->execute([$akun['tenant_id']]);
$tipe = $st->fetchColumn();

$chk = db()->prepare('SELECT 1 FROM kategori WHERE tipe = ? AND nama = ? AND aktif = 1');
foreach ($names as $nama) {
    $chk->execute([$tipe, $nama]);
    if (!$chk->fetchColumn()) {
        json_out(['error' => 'Kategori tidak sesuai tipe tenant'], 400);
    }
}

// Simpan sebagai string gabungan ternormalisasi (mis. "Rasa, Pelayanan").
$kategori = implode(', ', $names);

$ins = db()->prepare(
    'INSERT INTO review (tenant_id, emote, kategori, komentar) VALUES (?, ?, ?, ?)'
);
$ins->execute([
    $akun['tenant_id'],
    $emote,
    $kategori,
    $komentar === '' ? null : $komentar,
]);

json_out(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
