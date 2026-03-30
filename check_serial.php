<?php
// ============================================================
//  check_serial.php
//  Verifica si un serial_number ya existe en la BD.
//  GET: ?serial=XXX[&exclude_id=N]
//  Respuesta: { ok: true, existe: bool, equipo?: { etiqueta, modelo } }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/Class/Conexion.php';

$serial     = isset($_GET['serial'])     ? trim($_GET['serial'])     : '';
$excludeId  = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

if ($serial === '') {
    echo json_encode(['ok' => true, 'existe' => false]);
    exit;
}

function q($v) { return str_replace("'", "''", $v); }

try {
    $conn = Conexion::conectar();

    $excludeClause = $excludeId > 0 ? "AND id <> $excludeId" : '';

    $sql = "
        SELECT TOP 1 id, etiqueta, modelo
        FROM dbo.equipos_computacionales
        WHERE serial_number = '" . q($serial) . "'
          AND LTRIM(RTRIM(ISNULL(serial_number,''))) <> ''
          $excludeClause
    ";

    $r = odbc_exec($conn, $sql);
    if (!$r) throw new Exception(odbc_errormsg($conn));

    if (odbc_fetch_row($r)) {
        echo json_encode([
            'ok'     => true,
            'existe' => true,
            'equipo' => [
                'etiqueta' => trim((string)odbc_result($r, 'etiqueta')),
                'modelo'   => trim((string)odbc_result($r, 'modelo')),
            ]
        ]);
    } else {
        echo json_encode(['ok' => true, 'existe' => false]);
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
