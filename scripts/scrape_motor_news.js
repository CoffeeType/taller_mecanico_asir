// scrape_motor_news.js — extracción de noticias Motor.es
import axios from 'axios';
import { parseString } from 'xml2js';
import { writeFileSync } from 'fs';

const FEED_URL = 'https://www.motor.es/feed';
const OUTPUT_FILE = './cache/motor_news.json';

async function scrapeMotorNews() {
    try {
        console.log('Obteniendo feed RSS de motor.es...');

        // Obtener el feed RSS con axios (mejor soporte CORS)
        const response = await axios.get(FEED_URL, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            },
            timeout: 10000
        });

        console.log('Feed obtenido. Parseando XML...');

        // Parsear XML a JSON
        parseString(response.data, { trim: true, explicitArray: false }, (err, result) => {
            if (err) {
                console.error('Error parseando XML:', err);
                return;
            }

            const items = result.rss.channel.item;
            const articles = [];

            // Procesar hasta 20 elementos
            const itemsToProcess = Array.isArray(items) ? items.slice(0, 20) : [items];

            itemsToProcess.forEach(item => {
                let image = null;

                // Intentar obtener imagen desde media:content
                if (item['media:content'] && item['media:content'].$) {
                    image = item['media:content'].$.url;
                }

                // Intentar enclosure
                if (!image && item.enclosure && item.enclosure.$) {
                    if (item.enclosure.$.type && item.enclosure.$.type.includes('image')) {
                        image = item.enclosure.$.url;
                    }
                }

                // Extraer imagen de la descripción
                if (!image && item.description) {
                    const imgMatch = item.description.match(/<img[^>]+src="([^"]+)"/i);
                    if (imgMatch) {
                        image = imgMatch[1];
                    }
                }

                // Limpiar descripción
                let description = item.description || '';
                description = description.replace(/<[^>]*>/g, '').substring(0, 150);

                articles.push({
                    title: item.title || '',
                    link: item.link || '',
                    description: description,
                    image: image,
                    date: item.pubDate || new Date().toISOString()
                });
            });

            // Guardar en archivo JSON
            writeFileSync(OUTPUT_FILE, JSON.stringify(articles, null, 2));
            console.log(`✓ ${articles.length} noticias guardadas en ${OUTPUT_FILE}`);
        });

    } catch (error) {
        console.error('Error obteniendo noticias:', error.message);
        process.exit(1);
    }
}

scrapeMotorNews();
