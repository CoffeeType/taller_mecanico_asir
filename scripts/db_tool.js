// scripts/db_tool.js — herramienta Node para actualizar noticias y volcar BD
import mysql from 'mysql2/promise';
import { readFileSync, writeFileSync } from 'fs';

const CONFIG = {
    host: 'localhost',
    user: 'root',
    password: 'root', // desde env.php (local)
    database: 'trabajo_final_php',
    multipleStatements: true
};

const NEWS_JSON = './cache/motor_news.json';
const DUMP_FILE = 'database_cloud.sql';

async function main() {
    let connection;
    try {
        console.log("Connecting to database...");
        connection = await mysql.createConnection(CONFIG);
        console.log("Connected.");

        // 1. ACTUALIZAR NOTICIAS
        console.log("--- Updating News ---");
        await updateNews(connection);

        // 2. VOLCAR BASE DE DATOS
        console.log("--- Dumping Database ---");
        await dumpDatabase(connection);

    } catch (err) {
        console.error("Fatal Error:", err);
    } finally {
        if (connection) await connection.end();
    }
}

async function updateNews(conn) {
    try {
        const rawData = readFileSync(NEWS_JSON, 'utf8');
        const articles = JSON.parse(rawData);

        // Obtener ID de administrador
        const [rows] = await conn.execute("SELECT idUser FROM users_login WHERE rol = 'admin' LIMIT 1");
        const adminId = rows.length > 0 ? rows[0].idUser : 1;

        await conn.beginTransaction();

        // Borrar noticias antiguas
        await conn.execute("DELETE FROM noticias");

        // Preparar inserción
        const sql = "INSERT INTO noticias (idUser, titulo, texto, imagen, fecha, enlace) VALUES (?, ?, ?, ?, ?, ?)";

        let count = 0;
        for (const article of articles) {
            const title = article.title.substring(0, 200);
            const text = (article.description || '') + "\n\nFuente: Motor.es";
            const image = article.image || '';
            const link = article.link || '';
            const date = new Date(article.date).toISOString().split('T')[0];

            await conn.execute(sql, [adminId, title, text, image, date, link]);
            count++;
        }

        await conn.commit();
        console.log(`Successfully updated ${count} news articles.`);

    } catch (err) {
        await conn.rollback();
        console.error("Error updating news:", err);
        throw err;
    }
}

async function dumpDatabase(conn) {
    let dump = "-- Volcado de base de datos generado por Node.js\n";
    dump += "-- Date: " + new Date().toISOString() + "\n\n";
    dump += "SET NAMES utf8mb4;\n";
    dump += "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Obtener tablas
    const [tables] = await conn.execute("SHOW TABLES");
    const tableKey = `Tables_in_${CONFIG.database}`;

    for (const row of tables) {
        const tableName = row[tableKey];
        // console.log(`Processing table: ${tableName}`);

        // Esquema
        const [createRows] = await conn.execute(`SHOW CREATE TABLE \`${tableName}\``);
        dump += `-- Estructura de la tabla \`${tableName}\`\n`;
        dump += `DROP TABLE IF EXISTS \`${tableName}\`;\n`;
        dump += createRows[0]['Create Table'] + ";\n\n";

        // Datos
        const [dataRows] = await conn.execute(`SELECT * FROM \`${tableName}\``);
        if (dataRows.length > 0) {
            dump += `-- Volcando datos de la tabla \`${tableName}\`\n`;

            // Inserciones por bloques
            const CHUNK_SIZE = 100;
            for (let i = 0; i < dataRows.length; i += CHUNK_SIZE) {
                const chunk = dataRows.slice(i, i + CHUNK_SIZE);

                const values = chunk.map(row => {
                    const vals = Object.values(row).map(val => {
                        if (val === null) return 'NULL';
                        if (typeof val === 'number') return val;
                        // Escapar cadena SQL
                        return "'" + String(val).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
                    });
                    return `(${vals.join(', ')})`;
                });

                dump += `INSERT INTO \`${tableName}\` VALUES ${values.join(', ')};\n`;
            }
            dump += "\n";
        }
    }

    dump += "SET FOREIGN_KEY_CHECKS = 1;\n";

    writeFileSync(DUMP_FILE, dump);
    console.log(`Database dumped to ${DUMP_FILE}`);
}

main();
