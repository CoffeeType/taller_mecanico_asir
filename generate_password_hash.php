<?php
/**
 * Script auxiliar para generar hash de contraseña
 * Ejecutar desde línea de comandos: php generate_password_hash.php
 * 
 * Este script genera un hash válido para usar en la base de datos
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Contraseña: $password\n";
echo "Hash generado: $hash\n\n";
echo "Para usar este hash en database/database.sql, sustituye el valor de @admin_password_hash.\n";
echo "Ejemplo:\n";
echo "SET @admin_password_hash = '$hash';\n";

