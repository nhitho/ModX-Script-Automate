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

/*
    Es gibt einige Zeilen die mit "// Debug : *" beginnen.
    Um die Debugmodus zu kommentieren benutze entweder # oder // vor der Funktion.
    Einige LOG_LEVEL_ERROR besiten kein Kommentar mit Debug. Die sollten natürlich nicht auskommentiert werden.
*/


// Überprüfe, ob die TXT-Ordner existieren
if (is_dir($txt_Doc_A) && is_dir($txt_Doc_B)) {
    
    //Debug: Initialsisierung
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
            
            // Debug: Ausgabe
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

            // Debug: Ausgabe
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Überprüfung der Datei '. $file.' ist abgeschlossen');
        }
    }
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'TXT-Dateien geladen: ' . print_r($filesA, true) . ' | ' . print_r($filesB, true)); 
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Funktion Textvergleich wird initialisiert...');  
    
    $links = compareContentWithTxt($txt_Doc_A, $ignoreTags, $modx);
    
    // Debug: Ausgabe
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ergebnisse werden gepostet');

    // Debug: Ausgabe
    $modx->log(xPDO::LOG_LEVEL_ERROR, print_r($links, true));
}

else {
    // Debug: Ausgabe
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Die TXT-Ordner existieren nicht.');

    // Debug: Wenn A oder B nicht existieren.
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Die TXT-Ordner (A oder B) existieren nicht.');
}

// Funktion zum Aufruf des Modx Kontext key 'web'
function getWebContextResources($modx) {
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Rufe Kontext "web" auf.'); // DEBUG
    
    // Rufe ModX Kontext auf
    $webContext = $modx->getContext('web');

    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Web-Kontext geladen: ' . print_r($webContext, true));

    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Lade die Ressourcen');

    // Rufe die darin enthaltenen Ressourcen auf
    if (!$resources = $modx->getCollection('modResource', array('context_key' => 'web'))){
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Laden der Ressourcen: ' . $webContext->error); 
    }
    
    // Debug: Ausgabe
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Speichert die Inhalte.');

    // Speichere ID, Content, Titel, Alias und Template-Variablen in einen Array ab
    $resultArray = array();
    foreach ($resources as $resource) {
        $tvArray = getTVfromRessource($resource);

         // Füge Informationen zur Ressource inkl. Template-Variablen dem Ergebnisarray hinzu
        $resultArray[] = array(
            'id' => $resource->get('id'),
            'content' => $resource->get('content'),
            'title' => $resource->get('pagetitle'),
            'alias' => $resource->get('alias'),
            'template_vars' => $tvArray,
        );
    }
    
    // Debug: Ausgabe 
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ergebnisse werden gepostet'); 
    $modx->log(xPDO::LOG_LEVEL_ERROR, print_r($resultArray, true));

    // Gebe den Array zurück
    return $resultArray;
}

// Funktion zum Vergleich der Ressource Content und TXT Inhalt
function compareContentWithTxt($txt_Doc_A, $ignoreTags = array(), $modx) {
    $linkArray = array();

    // Debug: Ausgabe 
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Initialisiere Webkontext und Ressourcen...');

    // Rufe die Funktion zum Aufruf des ModX Kontext key 'web'
    $webResources = getWebContextResources($modx);
    
    // Debug: Ausgabe 
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Webkontext und Ressourcen wurden geladen...'); 

    // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_A . '/' . $file;
            
            // Debug: Ausgabe 
            # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Hole Inhalt aus den Textdokumenten.');
            
            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);

            // Debug: Ausgabe 
            # $modx->log(xPDO::LOG_LEVEL_ERROR, ' Inhalte wurden geladen.');

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
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ressourcenpositionen werden geladen');
    
    // Rufe die Positionen für die Ressource auf.
    $pos_Ressource = getPositionRessource($webResources, $ignoreTags, $modx);
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Textpositionen werden geladen');
    
    // Rufe die Positionen für die Datei auf.
    $pos_Text = getPositionText($fileContent, $modx);
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ressource- und Textpositionen sind fertig.');
    
    // Debug: Ausgabe
    # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Starte Verknüpfung und Vergleich der Inhalte.');

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

                // Debug: Ausgabe
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

            // Debug: Ausgabe
            # $modx->log(xPDO::LOG_LEVEL_ERROR, 'HTML Line ' . ($i + 1) . ': ' . $line);
            
            $pos_Ressource[] = [
                'id' => $resource['id'],
                'html_line' => $i + 1, // Startet mit 1
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

        // Debug: Ausgabe
        # $modx->log(xPDO::LOG_LEVEL_ERROR, 'File Line ' . ($i + 1) . ': ' . $line);
        
        $pos_Text[] = [
            'file_line' => $i + 1, // Startet mit 1
            'file_content' => $line,
        ];
    }

    // Hier hast du nun alle Positionen mit den Zeilennummern und den Inhalten der Zeilen
    return $pos_Text;
}

/* Funktionenbeispiel Anfang */

// Filenamen Extrahieren für Sprache

function fileNameLangExtract ($filePath, $modx){
    
    //Debug: Initialisierung
    $modx->log(xPDO::LOG_LEVEL_ERROR, "Sprachenkürzel wird extrahiert aus den Dateinamen...");

    // Suchmuster für Sprachenkürzel
    $pattern = '/^[^-]+-(.*?)-[a-zA-Z]+\.txt/';

    // Lese der Txt-Datei lesen
    $fileContent = file_get_contents($filePath);

    // Überprüfe, ob das Muster in der Datei gefunden wird
    if (preg_match($pattern, $fileContent, $matches)) {
        
        $LangInit = $matches[1];
        
        // Debug: Anzeige des Inhalts von $LangInit
        $modx->log(xPDO::LOG_LEVEL_ERROR, "Sprachenkürzel wurde extrahiert: $LangInit");
        
        return $LangInit;
    }

    $modx->log(xPDO::LOG_LEVEL_ERROR, "Kein Sprachenkürzel gefunden.");
    return null;
}

// Funktion der Auslesung der Position vom Template-Variable des Ressoruces 

function getTVfromRessource($resource) {
    $tvArray = array();

    // Lese alle Template-Variablen der Ressource
    $tvList = $resource->getTemplateVars();

    // Durchlaufe die Template-Variablen und speichere sie in einem Array
    foreach ($tvList as $tv) {
        $tvArray[] = array(
            'tv_id' => $tv->get('id'),
            'tv_name' => $tv->get('name'),
            'tv_value' => $tv->get('value'),
        );
    }

    // Gebe das Array mit den Template-Variablen zurück
    return $tvArray;
}

function compareTVTextsWithTxt($txt_Doc_A, $ignoreTags, $modx, $tvArray) {
    $linkArray = array();

    foreach ($tvArray as $tv) {
        
        // Debug: Ausgabe
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Positionen für TV-Text werden geladen');

        // Rufe die Positionen für den Text und Template-Variablen-Inhalt ab
        $pos_TVText = getPositionTV($tv['tv_value'], $modx);
        
        // Debug: Ausgabe
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'TV-Textpositionen sind fertig.');
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Starte Verknüpfung und Vergleich der TV-Inhalte.');

        // Durchlaufe alle TXT Dateien im ersten Ordner
        $filesA = scandir($txt_Doc_A);
        foreach ($filesA as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
                $filePath = $txt_Doc_A . '/' . $file;
                
                // Debug: Ausgabe
                $modx->log(xPDO::LOG_LEVEL_ERROR, 'Hole Inhalt aus den Textdokumenten.');
                
                // Lese der Txt-Datei lesen
                $fileContent = file_get_contents($filePath);
                
                // Debug: Ausgabe
                $modx->log(xPDO::LOG_LEVEL_ERROR, ' Inhalte wurden geladen.');

                // Vergleiche die Template-Variablen-Inhalte mit dem TXT Inhalt
                $result = compareTexts($fileContent, $pos_TVText, $ignoreTags, $modx);

                // Wenn Übereinstimmung gefunden wurde, füge es zum Verknüpfungsarray hinzu
                if (!empty($result)) {
                    $linkArray = array_merge($linkArray, $result);
                }
            }
        }
    }

    // Gebe das Verknüpfungsarray zurück
    return $linkArray;
}

function getPositionTV() {
    $pos_TVText = array();



    return $pos_TVText;
}


// Funktion der Auslesung der Position von Chunks

function getChunksContent() {

$pos_Chunks = getPositionChunks();
$pos_Text = getPositionText($fileContent, $modx);

}

function getPositonChunks() {
$pos_Chunks= array();
}

// Erstelle Kontext anhand der Sprache, wenn Sie vorhanden ist ansonsten füge dort Ressourcen ein.

function CheckContext() {


}

// Erstelle Ressource des neues Kontextes anhand der gefundende ID mit den Vergleich 

function CopyRessourcetoContext (){
    
}

// Erstelle Kategorie anhand der Sprache, wenn Sie vorhanden ist, ansonsten nicht erstellen.

function CheckorCreateCatogorie(){

fileNameLangExtract();
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
