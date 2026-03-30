<?php
// ============================================================
//  get_usuarios.php
//  GET → lista de usuarios desde dbo.USUARIOS_TPU
//  Devuelve: [ { login, nombre }, ... ] ordenado por LOGIN
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/Class/Conexion.php';

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
        SELECT LOGIN, FULL_NAME
        FROM dbo.USUARIOS_TPU
        ORDER BY LOGIN ASC
    ";

    $result = odbc_exec($conn, $sql);
    if (!$result) throw new Exception(odbc_errormsg($conn));

    $usuarios = [];
    while ($row = odbc_fetch_array($result)) {
        $login  = limpiar($row['LOGIN']);
        $nombre = limpiar($row['FULL_NAME']);
        if ($login === '') continue; // saltar filas sin login
        $usuarios[] = [
            'login'  => $login,
            'nombre' => $nombre,
        ];
    }

    echo json_encode(
        ['ok' => true, 'total' => count($usuarios), 'usuarios' => $usuarios],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage(), 'usuarios' => []]);
}
