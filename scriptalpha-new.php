<?php
// Laden Sie das MODX-System
require_once MODX_BASE_PATH . 'core/model/modx/modx.class.php';
require_once MODX_BASE_PATH . 'core/config/config.inc.php';

// Initialisieren Sie das MODX-Objekt "web"
$modx = new modX();
$modx -> initialize('web');

//Kontextschlüssel 'web'

// Basis-Ordner-Überwachung
$txt_Doc_A = MODX_BASE_PATH . 'assets/txt-original';
$txt_Doc_B = MODX_BASE_PATH . 'assets/txt-languages';

// Tags zum Ignorieren
$ignoreTags = array('<.*>');


// Überprüfe, ob die TXT-Ordner existieren
if (is_dir($txt_Doc_A) && is_dir($txt_Doc_B)) {
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Die TXT-Ordner existieren.'); 
     // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_A . '/' . $file;

            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Starte Überprüfung des Schädlichen Code bei '. $file );
            // Funktion zur Überprüfung auf schädlichen Code
            MaliciousCode($fileContent, $modx);
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Überprüfung der originalen Datei ist abgeschlossen');
        }
    }
     // Durchlaufe alle TXT Dateien im zweiten Ordner
     $filesB = scandir($txt_Doc_B);
     foreach ($filesB as $file) {
         if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
             $filePath = $txt_Doc_B . '/' . $file;
 
            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Starte Überprüfung des Schädlichen Code bei '. $file );
            // Funktion zur Überprüfung auf schädlichen Code
            MaliciousCode($fileContent, $modx);
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Überprüfung der Datei '. $file.' ist abgeschlossen');
        }
    }
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'TXT-Dateien geladen: ' . print_r($filesA, true) . ' | ' . print_r($filesB, true)); // DEBUG
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Funktion Textvergleich wird initialisiert...');  
    $links = compareContentWithTxt($txt_Doc_A, $ignoreTags, $modx);
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ergebnisse werden gepostet'); // DEBUG
    $modx->log(xPDO::LOG_LEVEL_ERROR, print_r($links, true)); // DEBUG
}
else {
    // Logge den Fehler für spätere Analyse
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Die TXT-Ordner existieren nicht.');

    // Du kannst auch eine spezifische Fehlermeldung für dein Log verwenden
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Die TXT-Ordner (A oder B) existieren nicht.');
}

// Funktion zum Vergleich der Ressource Content und TXT Inhalt
function compareContentWithTxt($txt_Doc_A, $ignoreTags = array(), $modx) {

    // rufe die Funktion zum Aufruf des ModX Kontext key 'web'
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Initialisiere Webkontext und Ressourcen...'); // DEBUG
    $webResources = getWebContextResources($modx);
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Webkontext und Ressourcen wurden geladen...'); // DEBUG

    // Initialisiere das Verknüpfungsarray
    $linkArray = array();

    // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
             $filePath = $txt_Doc_A . '/' . $file;
             // Lese der Txt-Datei lesen
             $modx->log(xPDO::LOG_LEVEL_ERROR, 'Hole Inhalt aus den Textdokumenten.');
             $fileContent = file_get_contents($filePath);
             $modx->log(xPDO::LOG_LEVEL_ERROR, ' Inhalte wurden geladen.');

             // Vergleiche die Ressourcen Inhalte mit dem TXT Inhalt
             $result = compareTexts($fileContent, $webResources, $ignoreTags, $modx, $filePath);

             // Wenn Übereinstimmung gefunden wurde, füge es zum Verknüpfungsarray hinzu
             if (!empty($result)) {
                 $linkArray = array_merge($linkArray, $result);
             }
        }
    }

    // Gebe das Verknüpfungsarray zurück
    return $linkArray;
}

// Funktion zum Aufruf des Modx Kontext key 'web'
function getWebContextResources($modx) {
    
    // Rufe ModX Kontext auf
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Rufe Kontext "web" auf.'); // DEBUG
    $webContext = $modx->getContext('web');
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Web-Kontext geladen: ' . print_r($webContext, true)); // DEBUG

    // Rufe die darin enthaltenen Ressourcen auf
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Lade die Ressourcen'); // DEBUG
    if (!$resources = $modx->getCollection('modResource', array('context_key' => 'web'))){
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Laden der Ressourcen: ' . $webContext->error); 
    }
    // Speichere ID, Content, Titel, Alias in einen Array ab
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Speichert die Inhalte'); // DEBUG
    $resultArray = array();
    foreach ($resources as $resource) {
        $resultArray[] = array(
            'id' => $resource->get('id'),
            'content' => $resource->get('content'),
            'title' => $resource->get('pagetitle'),
            'alias' => $resource->get('alias')
        );
    }
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ergebnisse werden gepostet'); // DEBUG
    # $modx->log(xPDO::LOG_LEVEL_ERROR, print_r($resultArray, true)); // DEBUG
    // Gebe den Array zurück
    return $resultArray;
}

// Funktion zum Vergleich von Texten
function compareTexts($txtContent, $webResources, $ignoreTags, $modx, $txtFilePath) {
    $linkArray = array();

    foreach ($webResources as $resource) {
        // Ignoriere Tags der Ressourcen Content
        $resourceContent = strip_tags($resource['content'], implode('', $ignoreTags));

        // Texte reinigen und Tags entfernen
        $cleanedResourceContent = preg_replace('/<\/?[^>]+>/', '', $resourceContent);
        $cleanedResourceContent = str_replace(["\r", "\n"], '', $cleanedResourceContent);

        // Wort-für-Wort-Vergleich
        $resourceWords = explode(' ', $cleanedResourceContent);
        $txtWords = explode(' ', $txtContent);

        // Vergleich der Worte
        $commonWords = array_intersect($resourceWords, $txtWords);
        $wordSimilarity = count($commonWords) / max(count($resourceWords), count($txtWords)) * 100;

        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Wort-Ähnlichkeitsprozentsatz: ' . $wordSimilarity);

        if ($wordSimilarity > 80) {
            // Stimmen diese überein, speichere die Position des jeweiligen Quelle
            // Füge dort die entsprechende Ressourcen ID hinzu als Verknüpfung
            $positionInResource = mb_strpos($resourceContent, strip_tags($txtContent));
            $positionInTxtFile = mb_strpos(strip_tags($txtContent), strip_tags($resourceContent));

            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Common Words: ' . implode(' ', $commonWords));
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Resource Content: ' . $cleanedResourceContent);

            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Inhalt von Ressource ID ' . $resource['id'] . ' stimmt mit dem Inhalt der TXT-Datei überein.');
            // Füge die Ressourcen ID, Positionen und Pfad zur TXT-Datei zum Verknüpfungsarray hinzu
            $linkArray[] = array(
                'resource_id' => $resource['id'],
                'position_in_resource' => $positionInResource,
                'position_in_txt_file' => $positionInTxtFile,
                'txt_file_path' => $txtFilePath
            );
        } else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Common Words: ' . implode(' ', $commonWords));
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Resource Content: ' . $cleanedResourceContent);
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Nothit: Inhalt von Ressource ID ' . $resource['id'] . ' stimmt nicht mit dem Inhalt der TXT-Datei überein.');
        }
    }

    return $linkArray;
}
function MaliciousCode($content, $modx) {
    // Definiere Muster, nach denen im XML-Inhalt gesucht werden soll
    $keywords_pattern = array(
        'SQL' => '/\b(?:UPDATE|DELETE|DROP|CREATE|ALTER)\b/i',                              // SQL-Injection
        'JavaScript/HTML' => '/<script\b[^>]*>.*<\/script>/i',                              // JavaScript/HTML-Injection
        'PHP' => '/<\?php.*\?>/i',                                                          // PHP-Code
        'PHP Object Injection' => '/\b(?:unserialize|__wakeup|Serializable)\b/i',           // PHP Object Injection
        'Kommentierte PHP-Tags' => '/<!--.*<\?php.*-->.*-->/s',                             // Kommentierte PHP-Tags
        'Dateieinbindung' => '/<\?include.*\?>/i',                                          // Dateieinbindung
        'Systembefehle' => '/\b(?:system|exec|shell_exec|passthru)\b/i',                    // Systembefehle
        'XSS' => '/\b(?:alert|prompt|confirm|document\.write)\b/i',                         // XSS
        'XSS' => '/<(script|img|svg|iframe|input|a|form|embed|object|style|meta).*?>/i',    // XSS
        'Base64' => '/base64_decode\s*\(\s*["\']?[a-zA-Z0-9+\/=]+["\']?\s*\)/i',            // Base64-kodierte Inhalte
        'Path Traversal' => '/\.\.(?:\/|\\|%2F|%5C)/i',                                     // Strukturaufrufe
        'Remote Code Execution' => '/\b(?:eval|assert|system|exec|shell_exec|passthru)\b/i',// Remote-Code
        'CSRF' => '/<input.*\s+name=[\'"]?token[\'"]?.*?>/i',                               // Token
        );

    foreach ($keywords_pattern as $key => $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            // Schädlichen Code gefunden, logge einen Fehler mit den Informationen über den gefundenen Code
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Schädlicher Code gefunden: ' . $key . '. Gefundener Code: ' . print_r($matches, true));
        }
    }
}
