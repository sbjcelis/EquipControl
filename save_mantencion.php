<?php
// ============================================================
//  save_mantencion.php — CREATE y UPDATE mantención
//  POST: { id?, equipo_id, fecha, tipo, descripcion,
//          tecnico, estado, proxima_revision? }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/Class/Conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

$id              = isset($data['id'])              ? (int)$data['id']                  : null;
$equipoId        = isset($data['equipo_id'])       ? (int)$data['equipo_id']           : 0;
$fecha           = isset($data['fecha'])           ? trim($data['fecha'])              : '';
$tipo            = isset($data['tipo'])            ? trim($data['tipo'])               : '';
$descripcion     = isset($data['descripcion'])     ? trim($data['descripcion'])        : '';
$tecnico         = isset($data['tecnico'])         ? trim($data['tecnico'])            : '';
$estado          = isset($data['estado'])          ? trim($data['estado'])             : 'Completada';
$proximaRevision = isset($data['proxima_revision'])? trim($data['proxima_revision'])   : '';

if (!$equipoId || !$fecha || !$tipo) {
    echo json_encode(['ok' => false, 'msg' => 'Equipo, fecha y tipo son obligatorios.']);
    exit;
}

function parseFecha($f) {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m))
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f))
        return $f;
    return null;
}

function q($v) { return str_replace("'", "''", $v); }

$fechaSQL    = parseFecha($fecha);
$proximaSQL  = $proximaRevision ? parseFecha($proximaRevision) : null;

if (!$fechaSQL) {
    echo json_encode(['ok' => false, 'msg' => 'Fecha inválida.']);
    exit;
}

$proxVal = $proximaSQL ? "'$proximaSQL'" : 'NULL';

try {
    $conn = Conexion::conectar();

    if ($id) {
        // UPDATE
        $sql = "
            UPDATE dbo.mantenciones SET
                fecha            = '$fechaSQL',
                tipo             = '" . q($tipo)        . "',
                descripcion      = " . ($descripcion ? "'" . q($descripcion) . "'" : 'NULL') . ",
                tecnico          = " . ($tecnico     ? "'" . q($tecnico)     . "'" : 'NULL') . ",
                estado           = '" . q($estado)       . "',
                proxima_revision = $proxVal
            WHERE id = $id AND equipo_id = $equipoId
        ";
        $r = odbc_exec($conn, $sql);
        if (!$r) throw new Exception(odbc_errormsg($conn));
        echo json_encode(['ok' => true, 'msg' => 'Mantención actualizada correctamente.', 'accion' => 'update']);
    } else {
        // INSERT
        $sql = "
            INSERT INTO dbo.mantenciones
                (equipo_id, fecha, tipo, descripcion, tecnico, estado, proxima_revision)
            VALUES (
                $equipoId,
                '$fechaSQL',
                '" . q($tipo)        . "',
                " . ($descripcion ? "'" . q($descripcion) . "'" : 'NULL') . ",
                " . ($tecnico     ? "'" . q($tecnico)     . "'" : 'NULL') . ",
                '" . q($estado)       . "',
                $proxVal
            )
        ";
        $r = odbc_exec($conn, $sql);
        if (!$r) throw new Exception(odbc_errormsg($conn));
        echo json_encode(['ok' => true, 'msg' => 'Mantención registrada correctamente.', 'accion' => 'insert']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
