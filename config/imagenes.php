<?php
// Fotos de productos: subir, reducir, mostrar y borrar.
const URL_IMG = '/sistema_ventas/uploads/productos/';
const DIR_IMG = __DIR__ . '/../uploads/productos/';
const MAX_IMG = 2 * 1024 * 1024;

// Guarda la foto subida (JPG/PNG/WebP), la reduce a 900 px y devuelve el nombre del archivo.
// Devuelve null si no se subió nada; lanza RuntimeException con un mensaje claro si algo falla.
function guardarImagenProducto(?array $f): ?string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_IMG) {
        throw new RuntimeException('La foto no se pudo subir. Usa una de menos de 2 MB.');
    }
    $info = @getimagesize($f['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]])) {
        throw new RuntimeException('La foto debe ser una imagen JPG, PNG o WebP.');
    }
    if (!is_dir(DIR_IMG) && !mkdir(DIR_IMG, 0775, true)) {
        throw new RuntimeException('No se pudo crear la carpeta uploads/productos.');
    }
    $base = bin2hex(random_bytes(10));

    if (function_exists('imagecreatetruecolor')) {
        $origen = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name'])
            : ($info[2] === IMAGETYPE_WEBP ? @imagecreatefromwebp($f['tmp_name']) : @imagecreatefromjpeg($f['tmp_name']));
        if ($origen) {
            [$w, $h] = [imagesx($origen), imagesy($origen)];
            $k = min(1, 900 / max($w, $h));
            [$nw, $nh] = [max(1, (int)round($w * $k)), max(1, (int)round($h * $k))];
            $lienzo = imagecreatetruecolor($nw, $nh);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $nombre = $base . '.jpg';
            $ok = imagejpeg($lienzo, DIR_IMG . $nombre, 85);
            if ($ok) {
                return $nombre;
            }
        }
    }
    $nombre = $base . '.' . $tipos[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], DIR_IMG . $nombre)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }
    return $nombre;
}

function borrarImagenProducto(?string $nombre): void
{
    if ($nombre && is_file(DIR_IMG . basename($nombre))) {
        @unlink(DIR_IMG . basename($nombre));
    }
}

function urlImagenProducto(?string $nombre): ?string
{
    return ($nombre && is_file(DIR_IMG . basename($nombre))) ? URL_IMG . rawurlencode(basename($nombre)) : null;
}

// Foto del producto; si no tiene, un cuadro con las iniciales de la marca.
// $tam = 0 → versión grande para tarjetas.
function fotoProducto(?string $imagen, ?string $marca, string $nombre, int $tam = 44): string
{
    $alt = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $url = urlImagenProducto($imagen);
    if ($url) {
        return $tam > 0
            ? '<img class="foto-prod" loading="lazy" src="' . $url . '" alt="' . $alt . '" width="' . $tam . '" height="' . $tam . '">'
            : '<img class="foto-prod foto-grande" loading="lazy" src="' . $url . '" alt="' . $alt . '">';
    }
    $base = trim((string)$marca) !== '' ? $marca : $nombre;
    $tono = hexdec(substr(md5(mb_strtolower($base)), 0, 4)) % 360;
    $letras = htmlspecialchars(mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $base), 0, 2)), ENT_QUOTES, 'UTF-8');
    $color = 'background:hsl(' . $tono . ',60%,92%);color:hsl(' . $tono . ',45%,28%)';
    return $tam > 0
        ? '<span class="avatar-prod" style="width:' . $tam . 'px;height:' . $tam . 'px;' . $color . '">' . $letras . '</span>'
        : '<span class="foto-prod foto-grande" style="' . $color . '">' . $letras . '</span>';
}

// Campo de subida con vista previa (sirve para crear y editar).
function campoImagen(?string $actual = null): string
{
    $url = urlImagenProducto($actual);
    $vista = $url ? '<img src="' . $url . '" alt="">'
        : '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/></svg>';
    $js = "var f=this.files[0];if(f){var v=this.parentNode.querySelector('.subida-vista');v.innerHTML='';var i=new Image();i.src=URL.createObjectURL(f);v.appendChild(i);}";
    $html = '<div class="campo ancho"><label>Foto del producto <small>(opcional)</small></label>'
        . '<label class="subida"><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" onchange="' . $js . '">'
        . '<span class="subida-vista">' . $vista . '</span>'
        . '<span><strong>' . ($url ? 'Cambiar la foto' : 'Elegir una foto o arrastrarla aquí') . '</strong>'
        . '<small>JPG, PNG o WebP de hasta 2 MB. Se ajusta sola.</small></span></label>';
    if ($url) {
        $html .= '<label class="quitar-foto"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la foto actual</label>';
    }
    return $html . '</div>';
}
