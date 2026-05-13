<?php
// includes/rss_parser.php
// Parser RSS sencillo para motor.es

function fetchMotorNews($limit = 9) {
    $feedUrl = 'https://www.motor.es/feed';
    $cacheFile = __DIR__ . '/../cache/motor_news.json';
    $cacheTime = 3600; // caché 1 hora
    
    // Comprobar si la caché existe y sigue vigente
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $cached = @file_get_contents($cacheFile);
        $data = json_decode($cached, true);
        if (is_array($data) && !empty($data)) {
            return $data;
        }
    }
    
    // Obtener el RSS con cURL (más fiable que file_get_contents)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $feedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solo desarrollo local
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $xml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($xml === false || $httpCode != 200) {
        return [];
    }
    
    // Parsear XML
    $feed = @simplexml_load_string($xml);
    if ($feed === false) {
        return [];
    }
    
    $articles = [];
    $count = 0;
    
    foreach ($feed->channel->item as $item) {
        if ($count >= $limit) break;
        
        // Extraer datos básicos
        $title = (string) $item->title;
        $link = (string) $item->link;
        $description = (string) $item->description;
        $pubDate = (string) $item->pubDate;
        
        // Imagen desde media:content o enclosure
        $image = null;
        
        // media:content (habitual en RSS)
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->content)) {
            $image = (string) $media->content->attributes()->url;
        }
        
        // Etiqueta enclosure
        if (!$image && isset($item->enclosure)) {
            $encType = (string) $item->enclosure->attributes()->type;
            if (strpos($encType, 'image') !== false) {
                $image = (string) $item->enclosure->attributes()->url;
            }
        }
        
        // Buscar imagen en la descripción si no hubo otra
        if (!$image) {
            preg_match('/<img[^>]+src="([^"]+)"/i', $description, $matches);
            if (isset($matches[1])) {
                $image = $matches[1];
            }
        }
        
        // Limpiar HTML de la descripción
        $cleanDesc = strip_tags($description);
        $cleanDesc = substr($cleanDesc, 0, 150);
        
        $articles[] = [
            'title' => $title,
            'link' => $link,
            'description' => $cleanDesc,
            'image' => $image,
            'date' => $pubDate
        ];
        
        $count++;
    }
    
    // Guardar en caché
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    if (file_put_contents($cacheFile, json_encode($articles)) === false) {
        // Registrar error pero no fallar: la caché es opcional
        error_log("Failed to write cache file: " . $cacheFile);
    }
    
    return $articles;
}
