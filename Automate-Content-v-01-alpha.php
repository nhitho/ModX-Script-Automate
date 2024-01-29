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
    $linkArray = array();

    // rufe die Funktion zum Aufruf des ModX Kontext key 'web'
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Initialisiere Webkontext und Ressourcen...'); // DEBUG
    $webResources = getWebContextResources($modx);
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Webkontext und Ressourcen wurden geladen...'); // DEBUG

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
            $result = compareTexts($fileContent, $webResources, $ignoreTags, $modx);

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
function compareTexts($fileContent, $webResources, $ignoreTags, $modx) {
    $linkArray = array();
    
    // Rufe die Positionen für die Ressource und Textdatei ab
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ressourcenpositionen werden geladen');
    $pos_Ressource = getPositionRessource($webResources, $ignoreTags, $modx);
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Textpositionen werden geladen');
    $pos_Text = getPositionText($fileContent, $modx);
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ressource- und Textpositionen sind fertig.');
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Starte Verknüpfung und Vergleich der Inhalte.');

    foreach ($pos_Ressource as $positionRessource) {
        // Durchlaufe die ermittelten Positionen für den Text
        foreach ($pos_Text as $positionText) {
            // Vergleiche die Textinhalte genau mit strcmp
            $comparison = strcmp($positionRessource['html_content'], $positionText['file_content']);

            // Wenn die Texte genau übereinstimmen, füge es zum Verknüpfungsarray hinzu
            if ($comparison === 0) {
                $linkArray[] = array(
                    'resource_id' => $positionRessource['id'],
                    'position_in_resource' => $positionRessource['html_line'],
                    'file_line' => $positionText['file_line'],
                    'text_content' => $positionText['file_content']
                );

                // Du kannst den Vergleich für weitere Debugging-Zwecke loggen
                $modx->log(xPDO::LOG_LEVEL_ERROR, 'Exakter Textvergleich gefunden.');

                // Breche die innere Schleife ab, da ein Match gefunden wurde
                break;
            }
        }
    }
    return $linkArray;
}

// Funktion der Auslesung der Positionen vom Ressource
function getPositionRessource($webResources, $ignoreTags, $modx) {
    $pos_Ressource = array();

    foreach ($webResources as $resource) {
        // Ignoriere Tags der Ressourcen Content
        $cleanedResourceContent = strip_tags($resource['content'], implode('', $ignoreTags));

        // Suche nach Positionen im HTML-Text
        $linesResource = explode("\n", $cleanedResourceContent);
        foreach ($linesResource as $i => $line) {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'HTML Line ' . ($i + 1) . ': ' . $line);
            $pos_Ressource[] = [
                'id' => $resource['id'],
                'html_line' => $i + 1, // Line numbers start from 1
                'html_content' => $line,
            ];
        }
    }

    // Hier hast du nun alle Positionen mit den Zeilennummern und den Inhalten der Zeilen
    return $pos_Ressource;
}

// Funktion der Auslesung der Positionen vom Text
function getPositionText($fileContent, $modx) {
    $pos_Text = array();

    // Suche nach Positionen in der Textdatei
    $linesFile = explode("\n", $fileContent);
    foreach ($linesFile as $i => $line) {
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'File Line ' . ($i + 1) . ': ' . $line);
        $pos_Text[] = [
            'file_line' => $i + 1, // Line numbers start from 1
            'file_content' => $line,
        ];
    }

    // Hier hast du nun alle Positionen mit den Zeilennummern und den Inhalten der Zeilen
    return $pos_Text;
}

/* Funktionenbeispiel Anfang */

// Funktion der Auslesung der Position vom Template-Variable des Ressoruces 

function getTVfromRessource() {}

// Funktion der Auslesung der Position von Chunks

function getChunksContent() {

$pos_Chunks = getPositionChunks();
$pos_Text = getPositionText($fileContent, $modx);

}

function getPositonChunks() {
$pos_Chunks();
}

// Erstelle Kontext anhand der Sprache, wenn Sie vorhanden ist ansonsten füge dort Ressourcen ein.

function CheckContext() {
if() {
    
}
else {
    if(){
    }
    else{
    }
}

}

// Erstelle Ressource des neues Kontextes anhand der gefundende ID mit den Vergleich 

function CopyRessourcetoContext (){
    
}

// Erstelle Kategorie anhand der Sprache, wenn Sie vorhanden ist, ansonsten nicht erstellen.

function CheckorCreateCatogorie(){

fileNameLangExtract ()
}

function fileNameLangExtract (){
}
// Erstelle neue Chunks anhand der Sprache und verknüpfe Sie mit der Kategorie, wenn Sie vorhanden ist, ansonsten nicht erstellen.

function DuplicateOrUpdateChunks () {
    CheckorCreateCatogorie();
    getChunksContent();
}

// Funktion der Einfügen des Textes zu der Ressource

function InsertTextToRessource (){}

// Funktion des Einfügens des Textes in den Template-Variable des Ressoruces 

function InsertTextToTV(){}

// Funktion des Einfügen des Texten in den Chunks

function InsertTextToChunks(){}


/* Funktionenbeispiel Ende */

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
