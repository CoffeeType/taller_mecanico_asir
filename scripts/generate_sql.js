// scripts/generate_sql.js
import { readFileSync, writeFileSync } from 'fs';

const JSON_FILE = './cache/motor_news.json';
const SQL_FILE = './scripts/update_news.sql';

try {
    const rawData = readFileSync(JSON_FILE, 'utf8');
    const articles = JSON.parse(rawData);

    let sql = "USE trabajo_final_php;\n"; // Assumption: database name is taller_mecanico, need to verify from env.php
    sql += "SET NAMES utf8mb4;\n";
    sql += "DELETE FROM noticias;\n";
    sql += "SET @admin_id = (SELECT idUser FROM users_login WHERE rol = 'admin' LIMIT 1);\n";

    // Fallback if no admin
    sql += "SET @admin_id = IFNULL(@admin_id, 1);\n";

    sql += "INSERT INTO noticias (idUser, titulo, texto, imagen, fecha, enlace) VALUES \n";

    const values = articles.map(article => {
        // Clean data
        let title = article.title.substring(0, 200).replace(/'/g, "\\'");
        let text = (article.description || '').replace(/'/g, "\\'") + "\\n\\nFuente: Motor.es";
        let image = (article.image || '').replace(/'/g, "\\'");
        let link = (article.link || '').replace(/'/g, "\\'");

        // Date format
        let dateObj = new Date(article.date);
        let date = dateObj.toISOString().split('T')[0]; // YYYY-MM-DD

        return `(@admin_id, '${title}', '${text}', '${image}', '${date}', '${link}')`;
    });

    sql += values.join(",\n") + ";\n";

    writeFileSync(SQL_FILE, sql);
    console.log(`Generated ${SQL_FILE} with ${articles.length} articles.`);

} catch (error) {
    console.error("Error generating SQL:", error);
    process.exit(1);
}
