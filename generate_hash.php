<?php
// =============================
// GENERADOR DE HASH DE CONTRASEÑA
// =============================

// Cambia esta contraseña por la que quieras
$password = '1234';

// Generar hash seguro
$hash = password_hash($password, PASSWORD_DEFAULT);

// Mostrar resultado
echo "<h2>Generador de Hash</h2>";
echo "<p><strong>Password:</strong> $password</p>";
echo "<p><strong>Hash:</strong></p>";
echo "<textarea rows='3' cols='80'>$hash</textarea>";