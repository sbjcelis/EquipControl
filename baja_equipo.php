<?php
// ============================================================
//  baja_equipo.php — SOFT DELETE + trazabilidad
//  POST: { id, motivo_baja, registrado_por }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__FILE__) . '/Class/Conexion.php';

$data          = json_decode(file_get_contents('php://input'), true);
$id            = isset($data['id'])             ? (int)$data['id']             : 0;
$motivo        = isset($data['motivo_baja'])    ? trim($data['motivo_baja'])   : '';
$registradoPor = isset($data['registrado_por']) ? trim($data['registrado_por']): '';

if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}
if (!$motivo) {
    echo json_encode(['ok' => false, 'msg' => 'El motivo de baja es obligatorio.']);
    exit;
}

$registradoresValidos = ['Luis Yagi', 'Fabian Velasquez', 'Juan Felipe Celis'];
if (!in_array($registradoPor, $registradoresValidos, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Debes seleccionar quién registra la baja.', 'campo' => 'registrado_por']);
    exit;
}

function q($v) { return str_replace("'", "''", $v); }
function nv($v) { return $v !== '' ? "'" . q($v) . "'" : 'NULL'; }

try {
    $conn = Conexion::conectar();

    // Leer estado anterior
    $rAntes = odbc_exec($conn, "
        SELECT usuario, estado, ubicacion
        FROM dbo.equipos_computacionales WHERE id = $id
    ");
    if (!$rAntes) throw new Exception(odbc_errormsg($conn));
    $antes = odbc_fetch_array($rAntes);
    if (!$antes) {
        echo json_encode(['ok' => false, 'msg' => 'Equipo no encontrado.']);
        exit;
    }

    $usuarioAntes = trim((string)($antes['usuario']   ?? ''));
    $estadoAntes  = trim((string)($antes['estado']    ?? ''));
    $ubicAntes    = trim((string)($antes['ubicacion'] ?? ''));

    // Soft delete
    $rUpd = odbc_exec($conn, "
        UPDATE dbo.equipos_computacionales SET
            estado      = 'Dado de baja',
            fecha_baja  = CAST(GETDATE() AS DATE),
            motivo_baja = '" . q($motivo) . "'
        WHERE id = $id
    ");
    if (!$rUpd) throw new Exception(odbc_errormsg($conn));

    // Trazabilidad (no bloquea si tabla no existe aún)
    @odbc_exec($conn, "
        INSERT INTO dbo.equipos_trazabilidad
            (equipo_id, tipo_evento,
             usuario_anterior, estado_anterior, estado_nuevo,
             ubicacion_anterior, registrado_por, observacion, fecha_evento)
        VALUES (
            $id, 'Baja',
            " . nv($usuarioAntes) . ",
            " . nv($estadoAntes)  . ",
            'Dado de baja',
            " . nv($ubicAntes)    . ",
            '" . q($registradoPor) . "',
            '" . q($motivo)        . "',
            GETDATE()
        )
    ");

    echo json_encode([
        'ok'  => true,
        'msg' => 'Equipo dado de baja correctamente. El registro se conserva en el historial.'
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}