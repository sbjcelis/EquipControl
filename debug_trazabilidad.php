<?php
// ============================================================
//  debug_trazabilidad.php — DIAGNÓSTICO TEMPORAL
//  Eliminar este archivo después de resolver el problema.
//  Acceder desde el navegador: http://tuservidor/debug_trazabilidad.php
// ============================================================
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__FILE__) . '/Class/Conexion.php';

echo "=== DIAGNÓSTICO TRAZABILIDAD ===\n\n";

try {
    $conn = Conexion::conectar();
    echo "✔ Conexión ODBC OK\n\n";

    // ── 1. Verificar que la tabla existe ─────────────────────
    echo "--- 1. ¿Existe dbo.equipos_trazabilidad? ---\n";
    $r = odbc_exec($conn, "
        SELECT COUNT(*) AS existe
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = 'dbo'
          AND TABLE_NAME   = 'equipos_trazabilidad'
    ");
    odbc_fetch_row($r);
    $existe = (int)odbc_result($r, 'existe');
    echo $existe ? "✔ La tabla EXISTE\n\n" : "✘ La tabla NO EXISTE — ejecuta migration_v4.sql\n\n";

    if (!$existe) exit;

    // ── 2. Verificar columnas de la tabla ────────────────────
    echo "--- 2. Columnas de equipos_trazabilidad ---\n";
    $r = odbc_exec($conn, "
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'equipos_trazabilidad'
        ORDER BY ORDINAL_POSITION
    ");
    while ($row = odbc_fetch_array($r)) {
        echo "  {$row['COLUMN_NAME']} ({$row['DATA_TYPE']}) nullable={$row['IS_NULLABLE']}\n";
    }
    echo "\n";

    // ── 3. Verificar el CHECK constraint en tipo_evento ──────
    echo "--- 3. CHECK constraint tipo_evento ---\n";
    $r = odbc_exec($conn, "
        SELECT cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
        FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS cc
        INNER JOIN INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE cu
            ON cc.CONSTRAINT_NAME = cu.CONSTRAINT_NAME
        WHERE cu.TABLE_NAME  = 'equipos_trazabilidad'
          AND cu.COLUMN_NAME = 'tipo_evento'
    ");
    if ($row = odbc_fetch_array($r)) {
        echo "  Constraint: {$row['CONSTRAINT_NAME']}\n";
        echo "  Cláusula: {$row['CHECK_CLAUSE']}\n\n";
    } else {
        echo "  (sin constraint CHECK en tipo_evento)\n\n";
    }

    // ── 4. Verificar FK a equipos_computacionales ────────────
    echo "--- 4. FK constraint ---\n";
    $r = odbc_exec($conn, "
        SELECT TOP 1 id FROM dbo.equipos_computacionales ORDER BY id DESC
    ");
    odbc_fetch_row($r);
    $equipoIdTest = (int)odbc_result($r, 'id');
    echo "  Último equipo en BD: id = $equipoIdTest\n\n";

    // ── 5. Intentar INSERT de prueba ─────────────────────────
    echo "--- 5. INSERT de prueba en equipos_trazabilidad ---\n";
    $sqlTest = "
        INSERT INTO dbo.equipos_trazabilidad
            (equipo_id, tipo_evento, registrado_por, fecha_evento)
        VALUES (
            $equipoIdTest,
            'Registro inicial',
            'Sistema (diagnóstico)',
            GETDATE()
        )
    ";
    echo "SQL:\n$sqlTest\n\n";

    $rTest = odbc_exec($conn, $sqlTest);
    if ($rTest) {
        echo "✔ INSERT exitoso\n\n";

        // Verificar que quedó
        $rCnt = odbc_exec($conn, "SELECT COUNT(*) AS cnt FROM dbo.equipos_trazabilidad");
        odbc_fetch_row($rCnt);
        echo "  Total filas en tabla: " . odbc_result($rCnt, 'cnt') . "\n\n";

        // Limpiar la fila de prueba
        odbc_exec($conn, "
            DELETE FROM dbo.equipos_trazabilidad
            WHERE registrado_por = 'Sistema (diagnóstico)'
        ");
        echo "  (fila de prueba eliminada)\n\n";
    } else {
        $err = odbc_errormsg($conn);
        echo "✘ INSERT FALLÓ\n";
        echo "  Error ODBC: $err\n\n";
    }

    // ── 6. Verificar encoding del tipo_evento ────────────────
    echo "--- 6. Test encoding caracteres especiales ---\n";
    $tipos = [
        'Asignación',
        'Devolución',
        'Envío a servicio técnico',
        'Retorno de servicio técnico',
        'Baja',
        'Cambio de ubicación',
        'Registro inicial',
    ];
    foreach ($tipos as $tipo) {
        $hex = bin2hex($tipo);
        echo "  '$tipo' → bytes: $hex\n";
    }
    echo "\n";

    // ── 7. Ver registros actuales ────────────────────────────
    echo "--- 7. Registros actuales en equipos_trazabilidad ---\n";
    $rAll = odbc_exec($conn, "SELECT TOP 10 * FROM dbo.equipos_trazabilidad ORDER BY id DESC");
    if ($rAll) {
        $count = 0;
        while ($row = odbc_fetch_array($rAll)) {
            $count++;
            echo "  id={$row['id']} equipo_id={$row['equipo_id']} tipo={$row['tipo_evento']} fecha={$row['fecha_evento']}\n";
        }
        if ($count === 0) echo "  (tabla vacía)\n";
    }
    echo "\n";

    echo "=== FIN DIAGNÓSTICO ===\n";

} catch (Exception $e) {
    echo "✘ EXCEPCIÓN: " . $e->getMessage() . "\n";
}
