<?php
// ============================================================
//  delete_mantencion.php — DELETE mantención por id
//  POST: { id: number }
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__) . '/Class/Conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

try {
    $conn = Conexion::conectar();
    $r    = odbc_exec($conn, "DELETE FROM dbo.mantenciones WHERE id = $id");
    if (!$r) throw new Exception(odbc_errormsg($conn));
    echo json_encode(['ok' => true, 'msg' => 'Mantención eliminada correctamente.']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
