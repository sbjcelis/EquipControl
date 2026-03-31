<?php
// ============================================================
//  upload_comprobante.php
//  Sube un comprobante de compra para un equipo.
//  POST multipart/form-data: equipo_id (int) + file (archivo)
//  Tipos permitidos: PDF, JPG, JPEG, PNG · Máx 10 MB
//  Respuesta: { ok: true, path: "uploads/comprobantes/..." }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/Class/Conexion.php';

$equipoId = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;

if ($equipoId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'equipo_id inválido.']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'msg' => 'No se recibió ningún archivo.']);
    exit;
}

$file = $_FILES['file'];

// Validar error de upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errores = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo del servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo permitido.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_TMP_DIR => 'No hay directorio temporal disponible.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
    ];
    $msg = $errores[$file['error']] ?? 'Error al subir el archivo (código ' . $file['error'] . ').';
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

// Validar tamaño (10 MB)
$maxBytes = 10 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    echo json_encode(['ok' => false, 'msg' => 'El archivo supera el límite de 10 MB.']);
    exit;
}

// Validar extensión y tipo MIME
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
if (!in_array($ext, $extPermitidas, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Tipo de archivo no permitido. Use PDF, JPG o PNG.']);
    exit;
}

$mimePermitidos = ['application/pdf', 'image/jpeg', 'image/png'];
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $mimePermitidos, true)) {
    echo json_encode(['ok' => false, 'msg' => 'El contenido del archivo no corresponde al tipo declarado.']);
    exit;
}

// Crear carpeta de destino
$uploadDir = __DIR__ . '/uploads/comprobantes/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo crear el directorio de uploads.']);
        exit;
    }
}

// Nombre seguro del archivo: {equipo_id}_{timestamp}.{ext}
$nombreArchivo = $equipoId . '_' . time() . '.' . $ext;
$rutaFisica    = $uploadDir . $nombreArchivo;
$rutaBD        = 'uploads/comprobantes/' . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    echo json_encode(['ok' => false, 'msg' => 'No se pudo mover el archivo al directorio de destino.']);
    exit;
}

// Guardar la ruta en la BD (auto-crea la columna si no existe)
try {
    $conn = Conexion::conectar();

    // Verificar si la columna comprobante_path existe; si no, crearla
    $rCol = @odbc_exec($conn, "
        SELECT COUNT(*) AS cnt
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo'
          AND TABLE_NAME   = 'equipos_computacionales'
          AND COLUMN_NAME  = 'comprobante_path'
    ");
    if ($rCol && odbc_fetch_row($rCol) && (int)odbc_result($rCol, 'cnt') === 0) {
        @odbc_exec($conn, "
            ALTER TABLE dbo.equipos_computacionales
            ADD comprobante_path VARCHAR(500) NULL
        ");
    }

    // Eliminar archivo anterior si existe (liberar espacio)
    $rPrev = odbc_exec($conn, "
        SELECT comprobante_path FROM dbo.equipos_computacionales WHERE id = $equipoId
    ");
    if ($rPrev && odbc_fetch_row($rPrev)) {
        $prevPath = trim((string)odbc_result($rPrev, 'comprobante_path'));
        if ($prevPath !== '' && $prevPath !== $rutaBD) {
            $prevFisica = __DIR__ . '/' . ltrim($prevPath, '/');
            if (is_file($prevFisica)) {
                @unlink($prevFisica);
            }
        }
    }

    $pathEsc  = str_replace("'", "''", $rutaBD);
    $r = odbc_exec($conn, "
        UPDATE dbo.equipos_computacionales
        SET comprobante_path = '$pathEsc'
        WHERE id = $equipoId
    ");
    if (!$r) throw new Exception(odbc_errormsg($conn));

    echo json_encode(['ok' => true, 'path' => $rutaBD]);

} catch (Exception $e) {
    // El archivo ya fue guardado en disco; devolver warning pero path igual
    echo json_encode(['ok' => false, 'msg' => 'Archivo guardado pero error al actualizar BD: ' . $e->getMessage()]);
}
