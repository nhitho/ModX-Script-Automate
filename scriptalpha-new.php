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
            $result = compareTexts($fileContent, $webResources, $ignoreTags, $modx, $filePath, $searchTerms);

            // Wenn Übereinstimmung gefunden wurde, füge es zum Verknüpfungsarray hinzu
            if (!empty($result)) {
                $linkArray = array_merge($linkArray, $result);
            }
        }
    }

    // Gebe das Verknüpfungsarray zurück
    return $linkArray;
}

// Funktion zum Vergleich von Texten
function compareTexts($fileContent, $webResources, $ignoreTags, $modx, $txtFilePath, $searchTerms) {
    $linkArray = array();

    // Durchlaufe alle Web-Ressourcen
    foreach ($webResources as $resource) {
        // Ignoriere Tags der Ressourcen Content
        $resourceContent = strip_tags($resource['content'], implode('', $ignoreTags));

        // Rufe die Positionen für die Ressource und Textdatei ab
        $positions = getPosition($txtFilePath, $resourceContent, $fileContent, $ignoreTags, $modx, $searchTerms);
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Positionen aus getPosition: ' . print_r($positions, true)); // Debug
        
        // Überprüfe, ob Positionen vorhanden sind, bevor du weitermachst
        if (!empty($positions['position_in_resource'])) {
            // Durchlaufe die ermittelten Positionen
            foreach ($positions['position_in_resource'] as $position) {
                // Überprüfe, ob der Text in der Ressource Content vorhanden ist
                $textInResource = mb_substr($resourceContent, $position['html_start'], $position['html_end'] - $position['html_start']);
                
                // Überprüfe, ob der Text in der Textdatei vorhanden ist
                $textInTxtFile = mb_substr($fileContent, $position['file_start'], $position['file_end'] - $position['file_start']);
                
                // Vergleiche die beiden Texte
                if ($textInResource == $textInTxtFile) {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Text in Ressource ID ' . $resource['id'] . ' stimmt mit dem Text in der TXT-Datei überein.');
                    
                    // Füge die Ressourcen ID, Positionen und Pfad zur TXT-Datei zum Verknüpfungsarray hinzu
                    $linkArray[] = array(
                        'resource_id' => $resource['id'],
                        'position_in_resource' => $position['html_start'],
                        'position_in_txt_file' => $position['file_start'],
                        'txt_file_path' => $txtFilePath
                    );
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Nothit: Text in Ressource ID ' . $resource['id'] . ' stimmt nicht mit dem Text in der TXT-Datei überein.');
                }
            }
        } else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Die Funktion getPosition hat keine Positionen zurückgegeben.');
        }
    }

    // Gebe das Verknüpfungsarray zurück
    return $linkArray;
}

function getPosition($txtFilePath, $resourceContent, $fileContent, $ignoreTags, $modx, $searchTerms) {
    $positions = array();

    // Ignoriere Tags der Ressourcen Content
    $cleanedResourceContent = strip_tags($resourceContent, implode('', $ignoreTags));
    // Logge den bereinigten Ressourcen-Content
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Bereinigter Ressourcen-Content: ' . $cleanedResourceContent);

    // Texte reinigen und Tags entfernen
    $cleanedFileContent = strip_tags($fileContent, implode('', $ignoreTags));

    // Suche nach Positionen im HTML-Text
    foreach ($searchTerms as $term) {
        $start = strpos($cleanedResourceContent, $term);
        $end = $start + strlen($term);
        $positions[] = ['term' => $term, 'html_start' => $start, 'html_end' => $end];
    }

    // Suche nach Positionen in der Textdatei
    foreach ($searchTerms as $term) {
        $lines = explode("\n", $cleanedFileContent);

        foreach ($lines as $i => $line) {
            if (strpos($line, $term) !== false) {
                $positions[] = [
                    'term' => $term,
                    'file_line' => $i + 1, // Line numbers start from 1
                    'file_start' => strpos($line, $term),
                    'file_end' => strpos($line, $term) + strlen($term)
                ];
            }
        }
    }

    // Logge die Positionen
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Positionen: ' . print_r($positions, true));

    // Weitere Verarbeitung der Positionen hier...

    // Rückgabe der Positionen
    return array(
        'position_in_resource' => $positions,
        'txt_file_path' => $txtFilePath
    );
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
