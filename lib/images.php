<?php
/**
 * Upload & KOMPRESI gambar via GD.
 * Kunci agar thread tetap ringan: setiap gambar di-resize
 * (sisi terpanjang <= MAX_IMAGE_DIM) lalu disimpan sebagai JPEG/WebP
 * dengan kualitas IMAGE_QUALITY — hasilnya biasanya puluhan KB saja.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Proses satu file upload ($_FILES['x']) menjadi gambar terkompresi di uploads/.
 * $maxDim: sisi terpanjang hasil (default MAX_IMAGE_DIM; avatar pakai 256).
 * Return path relatif untuk disimpan ke DB, atau null bila tidak ada file.
 * Throw RuntimeException bila file tidak valid / gagal diproses.
 */
function process_image_upload(array $file, int $maxDim = MAX_IMAGE_DIM): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal, coba lagi.');
    }
    if (($file['size'] ?? 0) > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Gambar terlalu besar! Maksimal 3 MB.');
    }

    // Deteksi tipe dari ISI file, bukan nama/ekstensi.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }
    [$w, $h] = $info;
    $type = $info[2];

    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($file['tmp_name']);
            break;
        case IMAGETYPE_GIF:
            $src = @imagecreatefromgif($file['tmp_name']);
            break;
        case IMAGETYPE_WEBP:
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false;
            break;
        default:
            $src = false;
    }
    if ($src === false) {
        throw new RuntimeException('Format gambar tidak didukung (pakai JPG/PNG/GIF/WebP).');
    }

    // Pertahankan transparansi untuk PNG/GIF.
    imagepalettetotruecolor($src);
    imagealphablending($src, true);
    imagesavealpha($src, true);

    // Resize proporsional bila melebihi batas dimensi.
    $longest = max($w, $h);
    if ($longest > $maxDim) {
        $scale    = $maxDim / $longest;
        $newW     = max(1, (int)round($w * $scale));
        $newH     = max(1, (int)round($h * $scale));
        $resized  = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, true);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);
        $src = $resized;
    }

    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        imagedestroy($src);
        throw new RuntimeException('Gagal menyiapkan folder uploads/.');
    }

    // Pilih format output: WebP bila tersedia, selain itu JPEG.
    $useWebp  = function_exists('imagewebp');
    $name     = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . ($useWebp ? '.webp' : '.jpg');
    $target   = $targetDir . $name;
    $saved    = $useWebp ? imagewebp($src, $target, IMAGE_QUALITY) : imagejpeg($src, $target, IMAGE_QUALITY);
    imagedestroy($src);

    if (!$saved) {
        throw new RuntimeException('Gagal memproses gambar.');
    }
    return 'uploads/' . $name;
}
