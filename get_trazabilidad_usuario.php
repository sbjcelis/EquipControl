<?php
// ============================================================
//  get_trazabilidad_usuario.php
//  GET ?usuario=LOGIN  → eventos de trazabilidad donde
//  aparece el usuario como usuario_nuevo o usuario_anterior
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__FILE__) . '/Class/Conexion.php';

$usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';

if (!$usuario) {
    echo json_encode(['ok' => false, 'msg' => 'usuario requerido.', 'eventos' => []]);
    exit;
}

function limpiar($v) {
    if ($v === null) return '';
    $v = trim((string)$v);
    if (mb_detect_encoding($v, 'UTF-8,ISO-8859-1,WINDOWS-1252', true) === 'UTF-8') return $v;
    $c = @mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1,WINDOWS-1252,UTF-8');
    return ($c !== false) ? $c : utf8_encode($v);
}

function q($v) { return str_replace("'", "''", $v); }

try {
    $conn = Conexion::conectar();

    $sql = "
        SELECT
            t.id,
            t.equipo_id,
            t.tipo_evento,
            t.usuario_anterior,
            t.usuario_nuevo,
            t.estado_anterior,
            t.estado_nuevo,
            t.ubicacion_anterior,
            t.ubicacion_nueva,
            t.registrado_por,
            t.observacion,
            CONVERT(VARCHAR(16), t.fecha_evento, 120) AS fecha_evento,
            e.etiqueta,
            e.marca,
            e.modelo
        FROM dbo.equipos_trazabilidad t
        LEFT JOIN dbo.equipos_computacionales e ON e.id = t.equipo_id
        WHERE t.usuario_nuevo     = '" . q($usuario) . "'
           OR t.usuario_anterior  = '" . q($usuario) . "'
        ORDER BY t.fecha_evento DESC
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
            'registrado_por'     => limpiar($row['registrado_por']),
            'observacion'        => limpiar($row['observacion']),
            'fecha_evento'       => limpiar($row['fecha_evento']),
            'etiqueta'           => limpiar($row['etiqueta']),
            'marca'              => limpiar($row['marca']),
            'modelo'             => limpiar($row['modelo']),
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
