<?php
// ============================================================
//  get_mantenciones.php
//  GET ?equipo_id=X   → historial de un equipo
//  GET ?dashboard=1   → estadísticas globales para gráficos
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/Class/Conexion.php';

$conn      = Conexion::conectar();
$dashboard = isset($_GET['dashboard']) && $_GET['dashboard'] == '1';
$equipoId  = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : 0;

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

// ── DASHBOARD: estadísticas globales ─────────────────────────
if ($dashboard) {

    // Total mantenciones
    $r = odbc_exec($conn, "SELECT COUNT(*) AS total FROM dbo.mantenciones");
    odbc_fetch_row($r);
    $total = (int) odbc_result($r, 'total');

    // Por tipo
    $r = odbc_exec($conn, "SELECT tipo, COUNT(*) AS cnt FROM dbo.mantenciones GROUP BY tipo ORDER BY cnt DESC");
    $porTipo = [];
    while ($row = odbc_fetch_array($r)) {
        $porTipo[] = ['tipo' => $row['tipo'], 'cantidad' => (int)$row['cnt']];
    }

    // Por estado
    $r = odbc_exec($conn, "SELECT estado, COUNT(*) AS cnt FROM dbo.mantenciones GROUP BY estado");
    $porEstado = [];
    while ($row = odbc_fetch_array($r)) {
        $porEstado[] = ['estado' => $row['estado'], 'cantidad' => (int)$row['cnt']];
    }

    // Por mes (últimos 12 meses)
    $r = odbc_exec($conn, "
        SELECT FORMAT(fecha, 'yyyy-MM') AS mes, COUNT(*) AS cnt
        FROM dbo.mantenciones
        WHERE fecha >= DATEADD(MONTH, -11, DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1))
        GROUP BY FORMAT(fecha, 'yyyy-MM')
        ORDER BY mes ASC
    ");
    $porMes = [];
    while ($row = odbc_fetch_array($r)) {
        $porMes[] = ['mes' => $row['mes'], 'cantidad' => (int)$row['cnt']];
    }

    // Equipos con más mantenciones
    $r = odbc_exec($conn, "
        SELECT TOP 5 e.etiqueta, e.usuario, e.marca, COUNT(m.id) AS cnt
        FROM dbo.mantenciones m
        INNER JOIN dbo.equipos_computacionales e ON e.id = m.equipo_id
        GROUP BY e.etiqueta, e.usuario, e.marca
        ORDER BY cnt DESC
    ");
    $topEquipos = [];
    while ($row = odbc_fetch_array($r)) {
        $topEquipos[] = [
            'etiqueta' => $row['etiqueta'],
            'usuario'  => $row['usuario'],
            'marca'    => $row['marca'],
            'cantidad' => (int)$row['cnt']
        ];
    }

    // Próximas revisiones (en los próximos 60 días)
    $r = odbc_exec($conn, "
        SELECT TOP 5 e.etiqueta, e.usuario, m.tecnico,
               CONVERT(VARCHAR(10), m.proxima_revision, 103) AS proxima_revision,
               DATEDIFF(DAY, GETDATE(), m.proxima_revision) AS dias_restantes
        FROM dbo.mantenciones m
        INNER JOIN dbo.equipos_computacionales e ON e.id = m.equipo_id
        WHERE m.proxima_revision IS NOT NULL
          AND m.proxima_revision >= CAST(GETDATE() AS DATE)
          AND m.proxima_revision <= DATEADD(DAY, 60, GETDATE())
        ORDER BY m.proxima_revision ASC
    ");
    $proximasRevisiones = [];
    while ($row = odbc_fetch_array($r)) {
        $proximasRevisiones[] = [
            'etiqueta'         => $row['etiqueta'],
            'usuario'          => $row['usuario'],
            'tecnico'          => $row['tecnico'],
            'proxima_revision' => $row['proxima_revision'],
            'dias_restantes'   => (int)$row['dias_restantes']
        ];
    }

    echo json_encode([
        'total'             => $total,
        'por_tipo'          => $porTipo,
        'por_estado'        => $porEstado,
        'por_mes'           => $porMes,
        'top_equipos'       => $topEquipos,
        'proximas_revisiones'=> $proximasRevisiones
    ]);
    exit;
}

// ── HISTORIAL: mantenciones de un equipo específico ──────────
if (!$equipoId) {
    echo json_encode(['error' => 'equipo_id requerido']);
    exit;
}

$sql = "
    SELECT
        id,
        equipo_id,
        CONVERT(VARCHAR(10), fecha, 103)            AS fecha,
        tipo,
        descripcion,
        tecnico,
        estado,
        CONVERT(VARCHAR(10), proxima_revision, 103) AS proxima_revision,
        CONVERT(VARCHAR(16), fecha_registro, 120)   AS fecha_registro
    FROM dbo.mantenciones
    WHERE equipo_id = $equipoId
    ORDER BY fecha DESC
";

$result      = odbc_exec($conn, $sql);
$mantenciones = [];

if ($result) {
    while ($row = odbc_fetch_array($result)) {
        $mantenciones[] = [
            'id'               => (int)$row['id'],
            'equipo_id'        => (int)$row['equipo_id'],
            'fecha'            => $row['fecha'],
            'tipo'             => $row['tipo'],
            'descripcion'      => $row['descripcion'],
            'tecnico'          => $row['tecnico'],
            'estado'           => $row['estado'],
            'proxima_revision' => $row['proxima_revision'],
            'fecha_registro'   => $row['fecha_registro'],
        ];
    }
}

echo json_encode(['mantenciones' => $mantenciones]);
