<?php
header('Content-Type: application/json; charset=utf-8');

// En producción los errores no deben imprimirse en el output
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/Class/Conexion.php';
 
function limpiar_utf8($valor)
{
    if ($valor === null) {
        return '';
    }

    if (is_bool($valor) || is_int($valor) || is_float($valor)) {
        return $valor;
    }

    $valor = trim((string)$valor);

   
    if (mb_detect_encoding($valor, 'UTF-8, ISO-8859-1, WINDOWS-1252', true) === 'UTF-8') {
        return $valor;
    }

    
    $convertido = @mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, UTF-8');

    if ($convertido === false) {
        $convertido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $valor);
    }

    if ($convertido === false || $convertido === null) {
        
        $convertido = utf8_encode($valor);
    }

    return $convertido;
}

$conn = Conexion::conectar();

$sql = "
    SELECT
        id,
        etiqueta,
        usuario,
        equipo,
        marca,
        modelo,
        serial_number,
        antiguedad,
        comentario,
        fecha_registro,
        ubicacion,
        estado,
        CONVERT(VARCHAR(10), fecha_baja, 103) AS fecha_baja,
        motivo_baja,
        comprobante_path
    FROM dbo.equipos_computacionales
    ORDER BY antiguedad ASC, id ASC
";

$result = odbc_exec($conn, $sql);

if (!$result) {
    echo json_encode(array(
        'ok' => false,
        'error' => 'ERROR_SQL',
        'detalle' => limpiar_utf8(odbc_errormsg($conn)),
        'equipos' => array()
    ));
    exit;
}

$equipos = array();

while ($row = odbc_fetch_array($result)) {

    $fecha_compra = '';
    $anios = 0;
    $meses = 0;
    $dias = 0;

    if (!empty($row['antiguedad'])) {
        $timestamp = strtotime($row['antiguedad']);

        if ($timestamp !== false) {
            $fecha_compra = date('d/m/Y', $timestamp);

            $fechaInicio = new DateTime(date('Y-m-d', $timestamp));
            $fechaHoy = new DateTime(date('Y-m-d'));
            $diff = $fechaInicio->diff($fechaHoy);

            $anios = (int)$diff->y;
            $meses = (int)$diff->m;
            $dias = (int)$diff->d;
        } else {
            $fecha_compra = limpiar_utf8($row['antiguedad']);
        }
    }

    $equipos[] = array(
        'id' => isset($row['id']) ? (int)$row['id'] : 0,
        'etiqueta' => isset($row['etiqueta']) ? limpiar_utf8($row['etiqueta']) : '',
        'usuario' => isset($row['usuario']) ? limpiar_utf8($row['usuario']) : '',
        'equipo' => isset($row['equipo']) ? limpiar_utf8($row['equipo']) : '',
        'marca' => isset($row['marca']) ? limpiar_utf8($row['marca']) : '',
        'modelo' => isset($row['modelo']) ? limpiar_utf8($row['modelo']) : '',
        'serial_number' => isset($row['serial_number']) ? limpiar_utf8($row['serial_number']) : '',
        'fecha_compra' => $fecha_compra,
        'antiguedad' => $fecha_compra,
        'anios' => $anios,
        'meses' => $meses,
        'dias' => $dias,
        'comentario'  => isset($row['comentario'])  ? limpiar_utf8($row['comentario'])  : '',
        'fecha_registro' => isset($row['fecha_registro']) ? limpiar_utf8($row['fecha_registro']) : '',
        'ubicacion'   => isset($row['ubicacion'])   ? limpiar_utf8($row['ubicacion'])   : '',
        'estado'      => isset($row['estado'])      ? limpiar_utf8($row['estado'])      : '',
        'fecha_baja'  => isset($row['fecha_baja'])  ? limpiar_utf8($row['fecha_baja'])  : '',
        'motivo_baja' => isset($row['motivo_baja']) ? limpiar_utf8($row['motivo_baja']) : '',
        'comprobante_path' => isset($row['comprobante_path']) ? limpiar_utf8($row['comprobante_path']) : '',
    );
}

$json = json_encode(
    array(
        'ok' => true,
        'total' => count($equipos),
        'equipos' => $equipos
    ),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    echo json_encode(array(
        'ok' => false,
        'error' => 'ERROR_JSON_ENCODE',
        'detalle' => json_last_error_msg(),
        'equipos' => array()
    ));
    exit;
}

echo $json;
exit;
?>