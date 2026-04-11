<?php
// scripts/update_schema_v2.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Updating schema...\n";

    $columnExistsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");

    $addColumn = function (string $table, string $column, string $ddl) use ($pdo, $columnExistsStmt): void {
        $columnExistsStmt->execute([$table, $column]);
        $exists = (int)$columnExistsStmt->fetchColumn() > 0;

        if ($exists) {
            echo "Column {$column} already exists.\n";
            return;
        }

        echo "Adding column {$column}...\n";
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$ddl}");
    };

    // Keep types aligned with database/database.sql
    $addColumn('users_data', 'calle', 'calle VARCHAR(255) NULL');
    $addColumn('users_data', 'codigo_postal', 'codigo_postal VARCHAR(10) NULL');
    $addColumn('users_data', 'ciudad', 'ciudad VARCHAR(100) NULL');
    $addColumn('users_data', 'provincia', 'provincia VARCHAR(100) NULL');

    echo "Schema update completed successfully.\n";

} catch (PDOException $e) {
    die("Error updating schema: " . $e->getMessage() . "\n");
}
