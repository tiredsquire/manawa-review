<?php
/**
 * Manawa Review — pasang logo tenant.
 *
 * Mencocokkan file logo (nama file = nama bisnis) ke tenant di DB,
 * menyalinnya ke /logos/<slug>.<ext>, mengisi tenant.logo_url, lalu
 * mengecilkan resolusi gambar (maks 320px) agar ringan.
 *
 * Jalankan ULANG setiap habis import_srl.sql (TRUNCATE menghapus logo_url):
 *   D:\XAMPP\php\php.exe -d extension=gd -d memory_limit=3072M apply_logos.php
 *
 * Ubah SOURCE_DIR ke lokasi folder logo di komputpermu.
 */

const SOURCE_DIR = 'D:/KULIAH/LOGO TENANT-20260617T125954Z-3-001/LOGO TENANT';
const DEST_DIR   = __DIR__ . '/../logos';
const MAX_PX     = 320;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=manawa_review;charset=utf8mb4', 'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function norm($s) { return preg_replace('/[^a-z0-9]/', '', strtolower($s)); }

$byNorm = [];
foreach ($pdo->query('SELECT slug, nama_bisnis FROM tenant')->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $byNorm[norm($t['nama_bisnis'])] = $t['slug'];
}
// koreksi manual nama file -> nama bisnis di DB
$alias = [
    'mushimushi' => norm('Mushimushi Studio'),
    'berumalab'  => norm('Beruma Lab (pergantian dari Army Coffee)'),
];

if (!is_dir(DEST_DIR)) mkdir(DEST_DIR, 0777, true);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SOURCE_DIR, FilesystemIterator::SKIP_DOTS));
$imgExt = ['jpg', 'jpeg', 'png', 'webp', 'svg'];   // pdf/psd sengaja dilewati
$picked = [];
foreach ($rii as $f) {
    if (!$f->isFile()) continue;
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, $imgExt)) continue;
    $base = $f->getBasename('.' . $f->getExtension());
    $name = trim(preg_split('/\s*[-_]?\s*spot\b/i', $base)[0], " -_(");
    $key  = norm($name);
    if (isset($alias[$key])) $key = $alias[$key];
    if (!isset($byNorm[$key])) continue;
    $slug = $byNorm[$key];
    if (isset($picked[$slug])) continue;   // file pertama menang
    $picked[$slug] = [$f->getPathname(), $ext === 'jpeg' ? 'jpg' : $ext];
}

$ok = 0;
foreach ($picked as $slug => [$src, $ext]) {
    $dest = DEST_DIR . '/' . $slug . '.' . $ext;
    if (!copy($src, $dest)) { echo "GAGAL salin: $src\n"; continue; }
    optimize($dest, $ext);
    $pdo->prepare('UPDATE tenant SET logo_url = ? WHERE slug = ?')
        ->execute(['logos/' . $slug . '.' . $ext, $slug]);
    $ok++;
}
echo "Logo terpasang: $ok tenant\n";

/** Kecilkan gambar ke MAX_PX (butuh ekstensi GD). SVG dilewati. */
function optimize($path, $ext) {
    if ($ext === 'svg' || !function_exists('imagecreatetruecolor')) return;
    $info = @getimagesize($path);
    if (!$info) return;
    [$w, $h] = $info;
    $img = $ext === 'png' ? @imagecreatefrompng($path)
         : ($ext === 'webp' ? @imagecreatefromwebp($path) : @imagecreatefromjpeg($path));
    if (!$img) return;
    $scale = max($w, $h) > MAX_PX ? MAX_PX / max($w, $h) : 1;
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($ext === 'png' || $ext === 'webp') {
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if ($ext === 'png') imagepng($dst, $path, 6);
    elseif ($ext === 'webp') imagewebp($dst, $path, 82);
    else imagejpeg($dst, $path, 82);
    imagedestroy($img); imagedestroy($dst);
}
