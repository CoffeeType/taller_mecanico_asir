// scrape_motor_news.js
import axios from 'axios';
import { parseString } from 'xml2js';
import { writeFileSync } from 'fs';

const FEED_URL = 'https://www.motor.es/feed';
const OUTPUT_FILE = './cache/motor_news.json';

async function scrapeMotorNews() {
    try {
        console.log('Obteniendo feed RSS de motor.es...');

        // Fetch RSS feed with axios (handles CORS better)
        const response = await axios.get(FEED_URL, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            },
            timeout: 10000
        });

        console.log('Feed obtenido. Parseando XML...');

        // Parse XML to JSON
        parseString(response.data, { trim: true, explicitArray: false }, (err, result) => {
            if (err) {
                console.error('Error parseando XML:', err);
                return;
            }

            const items = result.rss.channel.item;
            const articles = [];

            // Process up to 20 items
            const itemsToProcess = Array.isArray(items) ? items.slice(0, 20) : [items];

            itemsToProcess.forEach(item => {
                let image = null;

                // Try to get image from media:content
                if (item['media:content'] && item['media:content'].$) {
                    image = item['media:content'].$.url;
                }

                // Try enclosure
                if (!image && item.enclosure && item.enclosure.$) {
                    if (item.enclosure.$.type && item.enclosure.$.type.includes('image')) {
                        image = item.enclosure.$.url;
                    }
                }

                // Extract image from description
                if (!image && item.description) {
                    const imgMatch = item.description.match(/<img[^>]+src="([^"]+)"/i);
                    if (imgMatch) {
                        image = imgMatch[1];
                    }
                }

                // Clean description
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

            // Save to JSON file
            writeFileSync(OUTPUT_FILE, JSON.stringify(articles, null, 2));
            console.log(`✓ ${articles.length} noticias guardadas en ${OUTPUT_FILE}`);
        });

    } catch (error) {
        console.error('Error obteniendo noticias:', error.message);
        process.exit(1);
    }
}

scrapeMotorNews();
