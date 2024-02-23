<?php
// Laden Sie das MODX-System
require_once MODX_BASE_PATH . 'core/model/modx/modx.class.php';
require_once MODX_BASE_PATH . 'core/config/config.inc.php';

// Initialisieren Sie das MODX-Objekt "web"
$modx = new modX();
$modx -> initialize('web');

// Basis-Ordner-Überwachung
$txt_Doc_A = MODX_BASE_PATH . 'assets/txt-original';
$txt_Doc_B = MODX_BASE_PATH . 'assets/txt-languages';
$RESULT_TXT = MODX_BASE_PATH . 'assets/txt-result';

// Tags zum Ignorieren
$ignoreTags = array('<.*>');

//Initailisierung
$ResourceLinks = array();
$Chunklinks = array();

// Wert für $threshold aus der URL abrufen
$threshold = 70;
/*                 Hauptfunktion                              */

// Überprüfe, ob die TXT-Ordner existieren
if (is_dir($txt_Doc_A) && is_dir($txt_Doc_B)) {
    
    // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_A . '/' . $file;

            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);
            
            // Funktion zur Überprüfung auf schädlichen Code
            MaliciousCode($fileContent, $modx);
            
        }
    }
    // Durchlaufe alle TXT Dateien im zweiten Ordner
    $filesB = scandir($txt_Doc_B);
    foreach ($filesB as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_B . '/' . $file;
 
            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);
            
            // Funktion zur Überprüfung auf schädlichen Code
            MaliciousCode($fileContent, $modx);

        }
    }
    
    // Lade Inhalte von Content-Ressourcen und TV-Variablen
    $ResourceLinks = compareContentWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold);

    // Lade Inhalte von Chunks
    $ChunkLinks = compareChunksWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold);
    
    // Überprüfe, ob der Ordner für das Ergebnis existiert und erstelle ihn, wenn nicht vorhanden
    if (!is_dir($RESULT_TXT)) {
        mkdir($RESULT_TXT, 0777, true);
        touch($RESULT_TXT . '/result.txt');
    }

    // Pfad zur Datei
    $resultFile = $RESULT_TXT . '/result.txt';

    // Inhalt der Arrays
    $resourceContent = print_r($ResourceLinks, true);
    $chunkContent = print_r($ChunkLinks, true);

    // Schreibe den Inhalt in die Datei
    file_put_contents($resultFile, $resourceContent . "\n" . $chunkContent);


}
/*                 Schadwareüberprüfung                                               */


// Funktion zur Überprüfung der Dateien
function MaliciousCode($content, $modx) {
    // Definiere Muster, nach denen im TXT-Inhalt gesucht werden soll
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

/*                          Hilfsfunktionen                                               */

// Funktion zum Entfernen des BOM-Zeichens
function removeBOM($text) {
    $bom = pack('H*','EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
}

// Funktion zur Ermittlung der Positionen im Text
function getPositionText($fileContent, $modx) {
    $pos_Text = array();

    // Zeilenweises Aufteilen des Texts
    $linesText = explode("\n", $fileContent);
    foreach ($linesText as $i => $line) {
        $pos_Text[] = [
            'file_line' => $i + 1, // Startet mit 1
            'file_content' => $line,
        ];
    }

    return $pos_Text;
}

/*                           Ressource                                                */

// Funktion zum Vergleich der Ressource Content und TXT Inhalt
function compareContentWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold) {
    $linkArray = array();

    // Rufe die Funktion zum Aufruf des ModX Kontext key 'web'
    $webResources = getWebContextResources($modx);
    
    // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_A . '/' . $file;
             
            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);

            // Vergleiche die Ressourcen Inhalte mit dem TXT Inhalt
            $result = compareWebTexts($fileContent, $webResources, $ignoreTags, $modx, $filePath, $threshold);

        }
    }
    // Gebe das Verknüpfungsarray zurück
    return $result;
}

// Funktion zum Aufruf des Modx Kontext key 'web'
function getWebContextResources($modx) {
    
    // Rufe ModX Kontext auf
    $webContext = $modx->getContext('web');

    // Rufe die darin enthaltenen Ressourcen auf
    if (!$resources = $modx->getCollection('modResource', array('context_key' => 'web'))){
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Laden der Ressourcen: ' . $webContext->error); 
    }

    // Speichere ID, Content, Titel, Alias und Template-Variablen in einen Array ab
    $resultArray = array();
    foreach ($resources as $resource) {

         // Füge Informationen zur Ressource inkl. Template-Variablen dem Ergebnisarray hinzu
        $resultArray[] = array(
            'id' => $resource->get('id'),
            'content' => $resource->get('content'),
            'title' => $resource->get('pagetitle'),
            'alias' => $resource->get('alias'),
        );
    }

    // Gebe den Array zurück
    return $resultArray;
}

function compareWebTexts($fileContent, $webResources, $ignoreTags, $modx, $filePath, $threshold) {

    // Positionen für die Ressource und den Text ermitteln
    $pos_Ressource = getPositionRessource($webResources, $ignoreTags, $modx);
    $pos_Text = getPositionText($fileContent, $modx);

    // Durchlaufe alle Positionen der Ressource
    foreach ($pos_Ressource as $positionRessource) {
        // Inhalt der Ressource bereinigen und BOM-Zeichen entfernen
        $cleanedResourceContent = trim(strip_tags($positionRessource['html_content'], implode('', $ignoreTags)));
        $cleanedResourceContent = removeBOM($cleanedResourceContent);

        // Bestes Ergebnis initialisieren
        $bestMatch = array('percentage' => 0);

        // Durchlaufe alle Positionen des Texts
        foreach ($pos_Text as $positionText) {
            // Inhalt des Texts bereinigen und BOM-Zeichen entfernen
            $cleanedTextContent = removeBOM($positionText['file_content']);

            // Überprüfe, ob das Muster [[$de.technischedaten]] im HTML-Content vorkommt und ignoriere es
            $cleanedResourceContent = preg_replace('/\[\[\$de\.technischedaten\]\]/', '', $cleanedResourceContent);

            // Berechne die Ähnlichkeit der Texte
            similar_text($cleanedResourceContent, $positionText['file_content'], $percentage);

            // Wenn der prozentuale Ähnlichkeitswert den Schwellenwert übersteigt und besser ist als das bisher beste Ergebnis
            if ($percentage >= $threshold && $percentage > $bestMatch['percentage']) {
                // Speichere das beste Ergebnis
                $bestMatch = array(
                    'percentage' => $percentage,
                    'id' => $positionRessource['id'],
                    'html_line' => $positionRessource['html_line'],
                    'html_content' => $positionRessource['html_content'],
                    'file_line' => $positionText['file_line'],
                    'file_content' => $positionText['file_content'],
                );
            }
        }
 
        // Wenn ein gültiges Ergebnis gefunden wurde, füge es zum Ergebnisarray hinzu
        if ($bestMatch['percentage'] > 0) {
            $linkArray[] = $bestMatch;
        }
    }

    // Gebe das Ergebnisarray zurück
    return $linkArray;
}

// Funktion der Auslesung der Positionen vom Ressource
function getPositionRessource($webResources, $ignoreTags, $modx) {
    $pos_Ressource = array();

    foreach ($webResources as $resource) {
        // Ignoriere Tags der Ressourcen Content
        $cleanedResourceContent = preg_replace('#<[^>]+>#', '', $resource['content']);
        // Suche nach Positionen im HTML-Text
        $linesResource = explode("\n", $cleanedResourceContent);
        foreach ($linesResource as $i => $line) {
            
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

/*                           Chunks                                                        */

//  Ruft die Chunks auf und vergleicht Sie mit dem Text
function compareChunksWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold){
    $ChunkLinks = array();


    //Hole die Chunk Inhalte samt ID, Content
    $ChunkContent = getChunksContent($modx);
    
    // Durchlaufe alle TXT Dateien im ersten Ordner
    $filesA = scandir($txt_Doc_A);
    foreach ($filesA as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_A . '/' . $file;
             
            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);

             // Durchlaufe die Chunks und vergleiche Sie mit dem Text
            $ChunkLinks = compareChunkText($fileContent, $ChunkContent, $ignoreTags, $modx, $filePath, $threshold);
        }
    }
    return $ChunkLinks;
}

// Ruft Chunks auf
function getChunksContent($modx) {
    $ChunkContent = array();
    
    $chunks = $modx->getCollection('modChunk');
    
    foreach ($chunks as $chunk) {
        $ChunkContent[] = array(
            'id' => $chunk->get('id'),
            'name' => $chunk->get('name'),
            'content' => $chunk->get('content'),
            'category' => $chunk->get('category'),
            );
    }
    return $ChunkContent;
}

// Vergleicht Chunk mit Text
function compareChunkText($fileContent, $ChunkContent, $ignoreTags, $modx, $filePath, $threshold) {
    $chunkArray = array();

    // Rufe die Positionen für die Chunks auf.
    $pos_Chunk = getPositonChunks($ChunkContent, $modx);

    // Rufe die Positionen für die Datei auf.
    $pos_Text = getPositionText($fileContent, $modx);

    foreach ($pos_Chunk as $positionChunk) {
        // Inhalt der Chunk bereinigen und BOM-Zeichen entfernen
        $cleanedChunkContent = trim(strip_tags($positionChunk['html_content'], implode('', $ignoreTags)));
        $cleanedChunkContent = removeBOM($cleanedChunkContent);

        // Bestes Ergebnis initialisieren
        $bestMatch = array('percentage' => 0);

        // Durchlaufe die ermittelten Positionen für den Text
        foreach ($pos_Text as $positionText) {
            // Inhalt des Texts bereinigen und BOM-Zeichen entfernen
            $cleanedTextContent = removeBOM($positionText['file_content']);

            if ($positionChunk['category'] > 0) {

                // Berechne die Ähnlichkeit der Texte
                similar_text($cleanedChunkContent, $cleanedTextContent, $percentage);

                // Wenn der prozentuale Ähnlichkeitswert den Schwellenwert übersteigt und besser ist als das bisher beste Ergebnis
                if ($percentage >= $threshold && $percentage > $bestMatch['percentage']) {
                    // Speichere das beste Ergebnis
                    $bestMatch = array(
                        'percentage' => $percentage,
                        'chunk_id' => $positionChunk['id'],
                        'chunk_line' => $positionChunk['html_line'],
                        'chunk_content' => $positionChunk['html_content'],
                        'file_line' => $positionText['file_line'],
                        'file_content' => $positionText['file_content'],
                        'category' => $positionChunk['category'],
                    );
                }
            }
        }

        // Wenn ein gültiges Ergebnis gefunden wurde, füge es zum Ergebnisarray hinzu
        if ($bestMatch['percentage'] > 0) {
            $chunkArray[] = $bestMatch;
        }
    }

    return $chunkArray;
}

// Funktion der Auslesung der Positionen vom Chunk
function getPositonChunks($ChunkContent, $modx) {

    foreach ($ChunkContent as $resource) {
        
        //Strip Tags des Chunk Contents
        $cleanedChunkContent = strip_tags($resource['content']);

        // Suche nach Positionen im HTML-Text
        $linesResource = explode("\n", $cleanedChunkContent);

        foreach ($linesResource as $i => $line) {
            // Entferne HTML-Entity-Leerzeichen (&nbsp;) und trimme Leerzeichen
            $line = trim(str_replace('&nbsp;', ' ', $line));


            if (!empty($line)) {
                $pos_Chunk[] = array(
                    'id' => $resource['id'],
                    'html_line' => $i + 1,
                    'html_content' => $line,
                    'category' => $resource['category'],
                );
            }
        }
    }
    // Hier hast du nun alle Positionen mit den Zeilennummern und den Inhalten der Zeilen
    return $pos_Chunk;
}
