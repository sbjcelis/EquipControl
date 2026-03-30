<?php
// ============================================================
//  get_next_etiqueta.php
//  Devuelve la siguiente etiqueta correlativa disponible.
//  Formato: SBPV + número de 4 dígitos (ej. SBPV1030)
//  GET: (sin parámetros)
//  Respuesta: { ok: true, etiqueta: "SBPV1030" }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/Class/Conexion.php';

define('ETIQUETA_PREFIJO', 'SBPV');
define('ETIQUETA_DIGITOS', 4);

try {
    $conn = Conexion::conectar();

    $prefijo = ETIQUETA_PREFIJO;
    $largo   = strlen($prefijo);

    $sql = "
        SELECT MAX(CAST(SUBSTRING(etiqueta, " . ($largo + 1) . ", LEN(etiqueta)) AS INT)) AS ultimo
        FROM dbo.equipos_computacionales
        WHERE etiqueta LIKE '" . $prefijo . "%'
          AND ISNUMERIC(SUBSTRING(etiqueta, " . ($largo + 1) . ", LEN(etiqueta))) = 1
    ";

    $r = odbc_exec($conn, $sql);
    if (!$r) throw new Exception(odbc_errormsg($conn));

    odbc_fetch_row($r);
    $ultimo  = (int) odbc_result($r, 'ultimo');
    $siguiente = $ultimo + 1;

    $etiqueta = $prefijo . str_pad($siguiente, ETIQUETA_DIGITOS, '0', STR_PAD_LEFT);

    echo json_encode(['ok' => true, 'etiqueta' => $etiqueta]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
