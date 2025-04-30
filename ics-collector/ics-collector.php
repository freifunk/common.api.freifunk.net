<?php
/**
 * ICS-Collector
 * 
 * Sammelt ICS-Feed-URLs aus der Community-API und stellt sie als JSON zur Verfügung.
 * Wird vom ics-updater verwendet, um die Feeds zu aktualisieren.
 */

$configs = parse_ini_file(realpath(dirname(__FILE__)) . '/api-config.ini', true);
$communities = $configs['COMPONENT_URL']['SUMMARIZED_DIR_URL'];

// Standard-Feeds
// Format: [url, lastchange, community_id, city, community_name]
$standardFeeds = [
    ['https://ics.freifunk.net/tags/freifunk-common.ics', '2024-12-22', 'gemeinsam', 'Überall', 'Freifunk.net - Communityübergreifend']
];

// JSON-Ergebnis initialisieren
$result = [];

// Standard-Feeds in das Ergebnis einfügen
foreach ($standardFeeds as $feed) {
    $result[$feed[2]] = [
        'url' => $feed[0],
        'lastchange' => $feed[1],
        'city' => $feed[3],
        'communityname' => $feed[4]
    ];
}

// Community-API laden
try {
    $api = file_get_contents($communities);
    $json = json_decode($api, true);
    
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("Fehler beim Parsen der Community-API: " . json_last_error_msg());
    } else {
        // Community-Feeds in das Ergebnis einfügen
        foreach ($json as $name => $community) {
            if (!empty($community['feeds'])) {
                foreach ($community['feeds'] as $feed) {
                    if (isset($feed['category']) && $feed['category'] === "ics") {
                        $result[$name] = [
                            'url' => $feed['url'],
                            'lastchange' => $community['mtime'] ?? date('Y-m-d'),
                            'city' => $community['location']['city'] ?? 'Unbekannt',
                            'communityname' => $community['name'] ?? $name
                        ];
                        
                        // Optional die Community-URL hinzufügen, wenn vorhanden
                        if (isset($community['url'])) {
                            $result[$name]['communityurl'] = $community['url'];
                        }
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Fehler beim Laden der Community-API: " . $e->getMessage());
}

// Immer JSON zurückgeben
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
echo json_encode($result);
?>
