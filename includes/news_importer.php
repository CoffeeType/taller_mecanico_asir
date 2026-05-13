<?php
// includes/news_importer.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/rss_parser.php';

function logImport($msg) {
    $logPath = __DIR__ . '/../import_log.txt';
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logPath, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

function importMotorNews($pdo, $limit = 20) {
    try {
        logImport("Starting import...");
        
        // Paso 1: obtener noticias de motor.es primero
        logImport("Fetching news from RSS...");
        $motorNews = fetchMotorNews($limit);
        
        // Comprobación estricta de tipo: debe ser array
        if (!is_array($motorNews)) {
            logImport("CRITICAL: fetchMotorNews returned non-array type: " . gettype($motorNews));
            $motorNews = []; // Reserva: array vacío
        }
        
        logImport("Fetched " . count($motorNews) . " items.");
        
        if (empty($motorNews)) {
            logImport("No news fetched. Aborting.");
            return [
                'success' => false, 
                'message' => "No se pudieron obtener noticias del RSS feed. No se han realizado cambios.",
                'count' => 0
            ];
        }

        // Paso 2: ID del usuario administrador (primer admin)
        $stmt = $pdo->query("
            SELECT ul.idUser 
            FROM users_login ul 
            WHERE ul.rol = 'admin' 
            LIMIT 1
        ");
        $adminUser = $stmt->fetch();
        
        if (!$adminUser) {
            logImport("No admin found.");
            return [
                'success' => false, 
                'message' => "No se encontró ningún usuario administrador. Por favor, crea un usuario admin primero.",
                'count' => 0
            ];
        }
        $adminId = $adminUser['idUser'];
        logImport("Admin ID: $adminId");
        
        // Paso 3: borrar noticias antiguas solo si hay datos nuevos
        logImport("Clearing old news...");
        $stmt = $pdo->prepare("DELETE FROM noticias");
        $stmt->execute();
        
        // Paso 4: insertar noticias en la base de datos
        // UTF-8 en la conexión si no estaba ya
        $pdo->exec("SET NAMES utf8mb4");
        
        $stmt = $pdo->prepare("
            INSERT INTO noticias (idUser, titulo, texto, imagen, fecha, enlace) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $insertedCount = 0;
        foreach ($motorNews as $news) {
            $fecha = date('Y-m-d', strtotime($news['date']));
            
            // Limpiar y sanear texto
            $texto = $news['description'];
            if (function_exists('mb_convert_encoding')) {
                $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
            }
            $texto = preg_replace('/[^\p{L}\p{N}\s\.,;:?!¿¡\-]/u', '', $texto);
            $texto = trim($texto);
            
            // Enlace al artículo original
            $textoCompleto = $texto . "\n\nFuente: Motor.es";
            
            try {
                $stmt->execute([
                    $adminId,
                    mb_substr($news['title'], 0, 200), // Límite de longitud del título
                    $textoCompleto,
                    !empty($news['image']) ? $news['image'] : '', // Garantizar string
                    $fecha,
                    $news['link'] ?: null, // Guardar enlace
                ]);
                $insertedCount++;
            } catch (PDOException $e) {
                logImport("Insert error: " . $e->getMessage());
                // Omitir duplicados u otros errores en silencio
                continue;
            }
        }
        
        logImport("Inserted $insertedCount news.");
        return [
            'success' => true,
            'message' => "Se han importado $insertedCount noticias correctamente.",
            'count' => $insertedCount
        ];
        
    } catch (Exception $e) {
        logImport("Fatal error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Error: " . $e->getMessage(),
            'count' => 0
        ];
    }
}
