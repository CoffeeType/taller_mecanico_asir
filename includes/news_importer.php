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
        
        // Step 1: Fetch news from motor.es FIRST
        logImport("Fetching news from RSS...");
        $motorNews = fetchMotorNews($limit);
        
        // STRICT TYPE CHECK: Ensure we have an array
        if (!is_array($motorNews)) {
            logImport("CRITICAL: fetchMotorNews returned non-array type: " . gettype($motorNews));
            $motorNews = []; // Fallback to empty array
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

        // Step 2: Get admin user ID (first admin user)
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
        
        // Step 3: Clear existing news ONLY if we have new data
        logImport("Clearing old news...");
        $stmt = $pdo->prepare("DELETE FROM noticias");
        $stmt->execute();
        
        // Step 4: Insert news into database
        // Set UTF-8 for the connection if not already set
        $pdo->exec("SET NAMES utf8mb4");
        
        $stmt = $pdo->prepare("
            INSERT INTO noticias (idUser, titulo, texto, imagen, fecha, enlace) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $insertedCount = 0;
        foreach ($motorNews as $news) {
            $fecha = date('Y-m-d', strtotime($news['date']));
            
            // Clean and sanitize text
            $texto = $news['description'];
            if (function_exists('mb_convert_encoding')) {
                $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
            }
            $texto = preg_replace('/[^\p{L}\p{N}\s\.,;:?!¿¡\-]/u', '', $texto);
            $texto = trim($texto);
            
            // Add link to original article
            $textoCompleto = $texto . "\n\nFuente: Motor.es";
            
            try {
                $stmt->execute([
                    $adminId,
                    mb_substr($news['title'], 0, 200), // Limit title length
                    $textoCompleto,
                    !empty($news['image']) ? $news['image'] : '', // Ensure string
                    $fecha,
                    $news['link'] ?: null, // Insert link
                ]);
                $insertedCount++;
            } catch (PDOException $e) {
                logImport("Insert error: " . $e->getMessage());
                // Skip duplicates or errors silently
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
