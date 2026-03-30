<?php
// ============================================================
//  save_equipo.php — CREATE (INSERT) y UPDATE
//  Trazabilidad automática: detecta cambios de usuario,
//  estado y ubicación e inserta en equipos_trazabilidad.
//  POST: { id?, etiqueta, usuario, marca, modelo,
//          serial_number, antiguedad, comentario,
//          ubicacion, estado, registrado_por }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
require_once dirname(__FILE__) . '/Class/Conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

$id             = isset($data['id'])             ? (int)$data['id']              : null;
$etiqueta       = isset($data['etiqueta'])       ? trim($data['etiqueta'])        : '';
$usuario        = isset($data['usuario'])        ? trim($data['usuario'])         : '';
$marca          = isset($data['marca'])          ? trim($data['marca'])           : '';
$modelo         = isset($data['modelo'])         ? trim($data['modelo'])          : '';
$serial         = isset($data['serial_number'])  ? trim($data['serial_number'])   : '';
$antiguedad     = isset($data['antiguedad'])     ? trim($data['antiguedad'])      : '';
$comentario     = isset($data['comentario'])     ? trim($data['comentario'])      : '';
$ubicacion      = isset($data['ubicacion'])      ? trim($data['ubicacion'])       : '';
$estado         = isset($data['estado'])         ? trim($data['estado'])          : '';
$registradoPor  = isset($data['registrado_por']) ? trim($data['registrado_por'])  : '';

// ── Vocabulario controlado de estados ────────────────────────
$estadosValidos = ['Nuevo', 'Disponible', 'Asignado', 'Servicio técnico', 'Servicio tecnico', 'Dado de baja', ''];
if (!in_array($estado, $estadosValidos, true)) {
    echo json_encode(['ok' => false, 'msg' => "Estado no reconocido: '$estado'."]);
    exit;
}

// ── Registrador obligatorio en UPDATE ────────────────────────
$registradoresValidos = ['Luis Yagi', 'Fabian Velasquez', 'Juan Felipe Celis'];
if ($id && !in_array($registradoPor, $registradoresValidos, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Debes seleccionar quién registra el cambio.', 'campo' => 'registrado_por']);
    exit;
}

// ── Validar fecha ─────────────────────────────────────────────
function parseFecha($f) {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m))
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f))
        return $f;
    return null;
}

$fechaSQL = parseFecha($antiguedad);
if (!$fechaSQL) {
    echo json_encode(['ok' => false, 'msg' => 'Fecha inválida. Use DD/MM/YYYY o YYYY-MM-DD.']);
    exit;
}

function q($v) { return str_replace("'", "''", $v); }
function nv($v) { return $v !== '' ? "'" . q($v) . "'" : 'NULL'; }

try {
    $conn = Conexion::conectar();

    // ── Validar unicidad de serial_number ─────────────────────
    if ($serial !== '') {
        $excludeClause = $id ? "AND id <> $id" : '';
        $rChk = odbc_exec($conn, "
            SELECT COUNT(*) AS cnt
            FROM dbo.equipos_computacionales
            WHERE serial_number = '" . q($serial) . "'
              AND LTRIM(RTRIM(ISNULL(serial_number,''))) <> ''
              $excludeClause
        ");
        if (!$rChk) throw new Exception(odbc_errormsg($conn));
        odbc_fetch_row($rChk);
        if ((int)odbc_result($rChk, 'cnt') > 0) {
            echo json_encode([
                'ok'    => false,
                'msg'   => "El número de serie '$serial' ya está registrado en otro equipo.",
                'campo' => 'serial_number'
            ]);
            exit;
        }
    }

    // ════════════════════════════════════════════════════════════
    //  UPDATE
    // ════════════════════════════════════════════════════════════
    if ($id) {

        // ── 1. Leer estado ANTERIOR antes de actualizar ───────
        $rAntes = odbc_exec($conn, "
            SELECT usuario, estado, ubicacion
            FROM dbo.equipos_computacionales
            WHERE id = $id
        ");
        if (!$rAntes) throw new Exception(odbc_errormsg($conn));
        $antes = odbc_fetch_array($rAntes);
        if (!$antes) {
            echo json_encode(['ok' => false, 'msg' => 'Equipo no encontrado.']);
            exit;
        }

        $usuarioAntes  = trim((string)($antes['usuario']  ?? ''));
        $estadoAntes   = trim((string)($antes['estado']   ?? ''));
        $ubicAntes     = trim((string)($antes['ubicacion'] ?? ''));

        // ── 2. Ejecutar el UPDATE ──────────────────────────────
        $limpiarBaja = ($estado !== 'Dado de baja')
            ? ', fecha_baja = NULL, motivo_baja = NULL'
            : '';

        $sqlUpd = "
            UPDATE dbo.equipos_computacionales SET
                etiqueta      = " . nv($etiqueta)   . ",
                usuario       = " . nv($usuario)    . ",
                marca         = " . nv($marca)      . ",
                modelo        = " . nv($modelo)     . ",
                serial_number = " . nv($serial)     . ",
                antiguedad    = '$fechaSQL',
                comentario    = " . nv($comentario) . ",
                ubicacion     = " . nv($ubicacion)  . ",
                estado        = " . nv($estado)     . "
                $limpiarBaja
            WHERE id = $id
        ";
        $r = odbc_exec($conn, $sqlUpd);
        if (!$r) throw new Exception(odbc_errormsg($conn));

        // ── 3. Detectar cambios y registrar trazabilidad ──────
        $eventos = [];

        // Cambio de usuario
        if ($usuarioAntes !== $usuario) {
            if ($usuario === '' && $usuarioAntes !== '') {
                $tipoEv = 'Devolucion';
            } elseif ($usuarioAntes === '' && $usuario !== '') {
                $tipoEv = 'Asignacion';
            } else {
                $tipoEv = 'Asignacion'; // reasignación directa
            }
            $eventos[] = [
                'tipo'    => $tipoEv,
                'u_ant'   => $usuarioAntes,
                'u_nvo'   => $usuario,
                'est_ant' => $estadoAntes,
                'est_nvo' => $estado,
                'ub_ant'  => $ubicAntes,
                'ub_nvo'  => $ubicacion,
            ];
        }

        // Cambio de estado (sin cambio de usuario)
        if ($estadoAntes !== $estado && $usuarioAntes === $usuario) {
            $tipoEv = 'Asignacion'; // fallback
            if ($estado === 'Servicio técnico' || $estado === 'Servicio tecnico') {
                $tipoEv = 'Envio a servicio tecnico';
            } elseif (($estadoAntes === 'Servicio técnico' || $estadoAntes === 'Servicio tecnico')
                    && $estado !== 'Dado de baja') {
                $tipoEv = 'Retorno de servicio tecnico';
            } elseif ($estado === 'Dado de baja') {
                $tipoEv = 'Baja';
            } elseif ($estado === 'Disponible') {
                $tipoEv = 'Devolucion';
            }
            $eventos[] = [
                'tipo'    => $tipoEv,
                'u_ant'   => $usuarioAntes,
                'u_nvo'   => $usuario,
                'est_ant' => $estadoAntes,
                'est_nvo' => $estado,
                'ub_ant'  => $ubicAntes,
                'ub_nvo'  => $ubicacion,
            ];
        }

        // Cambio de ubicación (independiente de lo anterior)
        if ($ubicAntes !== $ubicacion
            && !in_array('Cambio de ubicacion', array_column($eventos, 'tipo'))
        ) {
            $eventos[] = [
                'tipo'    => 'Cambio de ubicacion',
                'u_ant'   => $usuarioAntes,
                'u_nvo'   => $usuario,
                'est_ant' => $estadoAntes,
                'est_nvo' => $estado,
                'ub_ant'  => $ubicAntes,
                'ub_nvo'  => $ubicacion,
            ];
        }

        // ── 4. Insertar filas de trazabilidad (no bloquea si tabla no existe) ──
        $trazOk = true;
        foreach ($eventos as $ev) {
            $sqlTraz = "
                INSERT INTO dbo.equipos_trazabilidad
                    (equipo_id, tipo_evento,
                     usuario_anterior, usuario_nuevo,
                     estado_anterior,  estado_nuevo,
                     ubicacion_anterior, ubicacion_nueva,
                     registrado_por, fecha_evento)
                VALUES (
                    $id,
                    '" . q($ev['tipo'])    . "',
                    " . nv($ev['u_ant'])   . ",
                    " . nv($ev['u_nvo'])   . ",
                    " . nv($ev['est_ant']) . ",
                    " . nv($ev['est_nvo']) . ",
                    " . nv($ev['ub_ant'])  . ",
                    " . nv($ev['ub_nvo'])  . ",
                    '" . q($registradoPor) . "',
                    GETDATE()
                )
            ";
            $rTraz = odbc_exec($conn, $sqlTraz);
            if (!$rTraz) {
                // Si la tabla de trazabilidad aún no existe, loguear pero no fallar
                $trazOk = false;
            }
        }

        echo json_encode([
            'ok'                  => true,
            'msg'                 => 'Equipo actualizado correctamente.',
            'accion'              => 'update',
            'eventos_registrados' => $trazOk ? count($eventos) : 0,
            'traz_pendiente'      => !$trazOk ? 'Ejecuta migration_v4.sql para activar trazabilidad.' : null,
        ]);

    // ════════════════════════════════════════════════════════════
    //  INSERT
    // ════════════════════════════════════════════════════════════
    } else {

        $sqlIns = "
            INSERT INTO dbo.equipos_computacionales
                (etiqueta, usuario, marca, modelo, serial_number,
                 antiguedad, comentario, ubicacion, estado)
            VALUES (
                " . nv($etiqueta)   . ",
                " . nv($usuario)    . ",
                " . nv($marca)      . ",
                " . nv($modelo)     . ",
                " . nv($serial)     . ",
                '$fechaSQL',
                " . nv($comentario) . ",
                " . nv($ubicacion)  . ",
                " . nv($estado)     . "
            )
        ";
        $r = odbc_exec($conn, $sqlIns);
        if (!$r) throw new Exception(odbc_errormsg($conn));

        // Obtener el ID recién insertado
        $rId = odbc_exec($conn, "SELECT SCOPE_IDENTITY() AS nuevo_id");
        odbc_fetch_row($rId);
        $nuevoId = (int)odbc_result($rId, 'nuevo_id');

        // Registro inicial en trazabilidad (no bloquea si tabla no existe)
        if ($nuevoId > 0) {
            $regPor = in_array($registradoPor, $registradoresValidos)
                ? $registradoPor : 'Sistema';
            @odbc_exec($conn, "
                INSERT INTO dbo.equipos_trazabilidad
                    (equipo_id, tipo_evento,
                     usuario_nuevo, estado_nuevo, ubicacion_nueva,
                     registrado_por, fecha_evento)
                VALUES (
                    $nuevoId,
                    'Registro inicial',
                    " . nv($usuario)   . ",
                    " . nv($estado)    . ",
                    " . nv($ubicacion) . ",
                    '" . q($regPor)    . "',
                    GETDATE()
                )
            ");
        }

        echo json_encode([
            'ok'     => true,
            'msg'    => 'Equipo registrado correctamente.',
            'accion' => 'insert',
            'id'     => $nuevoId,
        ]);
    }

} catch (Exception $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'UQ_equipos_serial_number') !== false
        || stripos($msg, 'unique') !== false) {
        echo json_encode(['ok' => false, 'msg' => 'El número de serie ya existe.', 'campo' => 'serial_number']);
    } else {
        echo json_encode(['ok' => false, 'msg' => $msg]);
    }
}