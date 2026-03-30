<?php
// ============================================================
//  get_trazabilidad.php
//  GET ?equipo_id=X  → historial completo de trazabilidad
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/Class/Conexion.php';

$equipoId = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;

if (!$equipoId) {
    echo json_encode(['ok' => false, 'msg' => 'equipo_id requerido.', 'eventos' => []]);
    exit;
}

function limpiar($v) {
    if ($v === null) return '';
    $v = trim((string)$v);
    if (mb_detect_encoding($v, 'UTF-8,ISO-8859-1,WINDOWS-1252', true) === 'UTF-8') return $v;
    $c = @mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1,WINDOWS-1252,UTF-8');
    return ($c !== false) ? $c : utf8_encode($v);
}

try {
    $conn = Conexion::conectar();

    $sql = "
        SELECT
            id,
            equipo_id,
            tipo_evento,
            usuario_anterior,
            usuario_nuevo,
            estado_anterior,
            estado_nuevo,
            ubicacion_anterior,
            ubicacion_nueva,
            registrado_por,
            observacion,
            CONVERT(VARCHAR(16), fecha_evento, 120) AS fecha_evento
        FROM dbo.equipos_trazabilidad
        WHERE equipo_id = $equipoId
        ORDER BY fecha_evento DESC
    ";

    $result = odbc_exec($conn, $sql);
    if (!$result) throw new Exception(odbc_errormsg($conn));

    $eventos = [];
    while ($row = odbc_fetch_array($result)) {
        $eventos[] = [
            'id'                 => (int)$row['id'],
            'equipo_id'          => (int)$row['equipo_id'],
            'tipo_evento'        => limpiar($row['tipo_evento']),
            'usuario_anterior'   => limpiar($row['usuario_anterior']),
            'usuario_nuevo'      => limpiar($row['usuario_nuevo']),
            'estado_anterior'    => limpiar($row['estado_anterior']),
            'estado_nuevo'       => limpiar($row['estado_nuevo']),
            'ubicacion_anterior' => limpiar($row['ubicacion_anterior']),
            'ubicacion_nueva'    => limpiar($row['ubicacion_nueva']),
            'registrado_por'     => limpiar($row['registrado_por']),
            'observacion'        => limpiar($row['observacion']),
            'fecha_evento'       => limpiar($row['fecha_evento']),
        ];
    }

    echo json_encode([
        'ok'     => true,
        'total'  => count($eventos),
        'eventos'=> $eventos,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage(), 'eventos' => []]);
}
