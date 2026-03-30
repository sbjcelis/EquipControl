<?php

class Conexion
{
    public static function conectar()
    {
        $data_source = 'DBTemp';
        $user = 'tp';
        $password = 'tp';

        $conexion = odbc_connect($data_source, $user, $password);

        if (!$conexion) {
            die('ERROR_CONEXION_ODBC');
        }

        return $conexion;
    }
}
?>