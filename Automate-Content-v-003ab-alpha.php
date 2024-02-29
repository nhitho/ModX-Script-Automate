<?php
// Laden Sie das MODX-System
require_once MODX_BASE_PATH . 'core/model/modx/modx.class.php';
require_once MODX_BASE_PATH . 'core/config/config.inc.php';

// Initialisieren Sie das MODX-Objekt "web"
$modx = new modX();
$modx -> initialize('web');  

// Basis-Ordner-Überwachung
$txt_Doc_A = MODX_BASE_PATH . 'assets/txt-original/';
$txt_Doc_B = MODX_BASE_PATH . 'assets/txt-languages/';

// Tags zum Ignorieren
$ignoreTags = array('<.*>');

//Initailisierung
$ResourceLinks = array();
$Chunklinks = array();

// Schwellenwert für die Ähnlichkeit der Texte in Prozent von 0-100 (0 für keine Ähnlichkeit, 100 für identische Texte)
$threshold = 70;

// Kontextschlüssel für den Quellkontext
$sourceContextKey = 'web';

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
    $RessourceLinks = compareContentWithTxt($sourceContextKey, $txt_Doc_A, $ignoreTags, $modx, $threshold);
    
    #$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($RessourceLinks, true));

    // Lade Inhalte von Chunks
    $ChunkLinks = compareChunksWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold);

    #$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($ChunkLinks, true));
    
    // Füge die Inhalte in die neuen Kontext, Kategorieren und Chunks ein
    allgetTogether($txt_Doc_B, $RessourceLinks, $ChunkLinks, $modx, $sourceContextKey);
} 
else {
    // Debug: Ausgabe
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Die TXT-Ordner existieren nicht.');
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
function compareContentWithTxt($sourceContextKey, $txt_Doc_A, $ignoreTags, $modx, $threshold) {
    $linkArray = array();

    // Rufe die Funktion zum Aufruf des ModX Kontext key 'web'
    $webResources = getWebContextResources($sourceContextKey, $modx);
    
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
function getWebContextResources($sourceContextKey, $modx) {
    
    // Rufe ModX Kontext auf
    $webContext = $modx->getContext($sourceContextKey);

    // Rufe die darin enthaltenen Ressourcen auf
    if (!$resources = $modx->getCollection('modResource', array('context_key' => $sourceContextKey))){
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

    // Wenn Sie für Debugging-Zwecke das Ergebnisarray protokollieren möchten, können Sie dies hier tun
    // $modx->log(xPDO::LOG_LEVEL_DEBUG, print_r($chunkArray, true));

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

/*                  Dateinamen und Dateiinhalt extrahieren                                 */

// Filenamen Extrahieren für Sprache und Produktnamen
function fileNameLangExtract($filePath, $modx) {

    // Suchmuster für Sprachenkürzel
    $patternLang = '/-([a-zA-Z]+)\.txt/';
    
    // Suchmuster für Produktnamen (gesamter Name ohne ".txt" am Ende)
    $patternProduct = '/^(.*?)\.txt/';

    // Extrahiere den Dateinamen aus dem Dateipfad
    $fileName = basename($filePath);

    // Überprüfe, ob das Muster im Dateinamen gefunden wird
    if (preg_match($patternLang, $fileName, $matchesLang) && preg_match($patternProduct, $fileName, $matchesProduct)) {
                
        $langCode = $matchesLang[1];
        $productName = $matchesProduct[1];
                
        // Debug: Anzeige des extrahierten Sprachenkürzels und Produktnamens
        # $modx->log(xPDO::LOG_LEVEL_ERROR, "Sprachenkürzel wurde extrahiert: $langCode");
        # $modx->log(xPDO::LOG_LEVEL_ERROR, "Produktname wurde extrahiert: $productName");
                
        return [$langCode, $productName];
    }
    else {    
        $modx->log(xPDO::LOG_LEVEL_ERROR, "Kein Sprachenkürzel oder Produktnamen gefunden.");
    }
}


/*                  Duplizierung und Erstellung der neuen Inhalten                         */

// Hauptkategorie für die Duplizierung und Erstellung der neuen Inhalte
function allgetTogether ($txt_Doc_B, $RessourceLinks, $ChunkLinks, $modx, $sourceContextKey){
    // Mapping-Tabelle für gemappte IDs
    $IDMap = array('resource' => array());
    
    // Durchlaufe alle TXT Dateien im zweiten Ordner
    $filesB = scandir($txt_Doc_B);
    foreach ($filesB as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filePath = $txt_Doc_B . '/' . $file;

            // Lese der Txt-Datei lesen
            $fileContent = file_get_contents($filePath);

            // Extrahiere den Sprachcode aus dem Dateinamen
            $langCode = '';
            $productCode = '';
            list($langCode, $productCode) = fileNameLangExtract($filePath, $modx);

            if ($IDMap=duplicateContext($modx, $sourceContextKey, $langCode, $IDMap, $productCode)) {
                // Debug: IDMap
               #$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($IDMap, true));
            } else {
                // Fehler bei der Kontextduplizierung
                $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler bei der Kontextduplizierung.');
            }

            // Überprüfe ob Kategorie bereits existiert, wenn nicht erstelle Sie
            if (!$langCategory = $modx->getObject('modCategory', array('category' => $langCode))) {
                $langCategory = createLangCategory($langCode, $productCode, $modx);
            }
            
            // Füge die Inhalte, im neuen Kontext, in den Ressourcen ein, abhängig von der Sprache
            insertResource($RessourceLinks, $fileContent, $langCode, $modx, $IDMap);

            // Ausgabe
        #    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Kontext, Kategorie, Chunks, Ressouren wurden erfolgreich dupliziert, erstellt und eingefügt.');
        }
    }
}

// Funktion zum Erstellen einer Kategorie (Hauptkategorie oder Unterkategorie)
function createLangCategory($langCode, $productCode = null, $modx){
    // Erstelle die Hauptkategorie
    $mainCategory = $modx->newObject('modCategory');
    $mainCategory->set('category', strtoupper($langCode));
    $mainCategory->set('parent', 0); // Keine Elternkategorie (Hauptkategorie)

    // Speichere die Hauptkategorie
    if ($mainCategory->save() === false) {
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Erstellen der Hauptkategorie: ' . print_r($mainCategory->error, true));
        return null;
    }

    // Erstelle die Unterkategorie, wenn $productCode angegeben ist
    if ($productCode !== null) {
        // Erstelle die Unterkategorie
        $subCategory = $modx->newObject('modCategory');
        $subCategory->set('category', $productCode);
        $subCategory->set('parent', $mainCategory->get('id')); // Setze die ID der Hauptkategorie als Elternwert

        // Speichere die Unterkategorie
        if ($subCategory->save() === false) {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Erstellen der Unterkategorie: ' . print_r($subCategory->error, true));
            return null;
        }
    }

    // Gebe die Hauptkategorie zurück
    return $mainCategory;
}

function duplicateContext($modx, $sourceContextKey, $langCode, $IDMap, $productCode) {
    
    // Lade den aktuellen Kontext samt Ressourcen
    $sourceContext = $modx->getObject('modContext', ['key' => $sourceContextKey]);
    if (!$sourceContext) {
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Quellkontext nicht gefunden für ' . $sourceContextKey);
        return false;
    }

    $sourceResources = $modx->getCollection('modResource', ['context_key' => $sourceContextKey, 'parent' => 0]);

    if (!$modx->getObject('modContext', ['key' => $langCode])) {
        // Dupliziere den Kontext samt Ressourcen
        $newContext = $modx->newObject('modContext');
        $newContext->fromArray($sourceContext->toArray());
        $newContext->set('key', $langCode); // Schlüssel für den neuen Kontext
        $newContext->set('name', strtoupper($langCode)); // Name für den Kontext
        $newContext->set('description', $productCode); // Beschreibung für den Kontext
        $newContext->save();

        // Speichere das Mapping für die alte ID und die neue ID
        $IDMap[$sourceContext->get('id')] = $newContext->get('id');

        // Benenne die Ressourcen des neuen Kontexts um (rekursiv)
        foreach ($sourceResources as $resource) {
            duplicateResource($modx, $resource->get('id'), $langCode, $IDMap);
        }
    }
    else {
        foreach ($sourceResources as $resource) {
            duplicateResource($modx, $resource->get('id'), $langCode, $IDMap);
        }
    }

    return $IDMap;
}

function duplicateResource($modx, $sourceResourceId, $langCode, &$IDMap, $parent = 0) {
    $sourceResource = $modx->getObject('modResource', $sourceResourceId);
    if (!$sourceResource) {
        return $IDMap;
    }
    if (!$newResource = $modx->getObject('modResource', ['context_key' => $langCode, 'pagetitle' => $sourceResource->get('pagetitle')])) {
        $newResource = $modx->newObject('modResource');
        $newResource->fromArray($sourceResource->toArray());
        $newResource->set('context_key', $langCode);
        $newResource->set('parent', $parent);
        if ($newResource->save()) {
            // Speichere das Mapping für die alte ID und die neue ID
            $IDMap['resource'][$sourceResource->get('id')] = $newResource->get('id');
            
            // Kopiere rekursiv die untergeordneten Ressourcen
            $sourceChildren = $modx->getCollection('modResource', ['parent' => $sourceResourceId]);
            foreach ($sourceChildren as $child) {
                duplicateResource($modx, $child->get('id'), $langCode, $IDMap, $newResource->get('id'));
            }
        } else {
            // Hier könntest du eine Fehlermeldung ausgeben, falls gewünscht
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Speichern der Ressource mit Titel: ' . $pagetitle);
        }
    }
    
    return $IDMap;    
}

// Funktion zum Einfügen von Inhalten in Ressourcen
function insertResource($RessourceLinks, $fileContent, $langCode, $modx, &$IDMap) {
    // Positionsdaten für den neuen Text erhalten
    $positionText = getPositionText($fileContent, $modx);

    // Durchlaufe alle Ressource-Links
    foreach ($RessourceLinks as $sourceResource) {
        // Überprüfe, ob es ein gemapptes Ziel für die aktuelle Ressource gibt
        if (isset($IDMap['resource'][$sourceResource['id']])) {
            $newResourceID = $IDMap['resource'][$sourceResource['id']];

            // Lade die Ziel-Ressource
            $newResource = $modx->getObject('modResource', $newResourceID);

            // Überprüfe, ob die Ziel-Ressource gefunden wurde
            if ($newResource) {
                // Suche die passende Position im Text
                $position = array_filter($positionText, function ($pos) use ($sourceResource) {
                    return $pos['file_line'] == $sourceResource['file_line'] && trim($pos['file_content']) == trim($sourceResource['file_content']);
                });

                // Falls Position gefunden wurde
                if (!empty($position)) {
                    $position = reset($position);
                    // Extrahiere den neuen Inhalt
                    $newContent = $position['file_content'];

                    // Füge den neuen Text in das HTML-Feld der Ziel-Ressource ein
                    $htmlContent = $newResource->get('content');
                    $newHtmlContent = str_replace($sourceResource['file_content'], $newContent, $htmlContent);

                    // Aktualisiere das HTML-Feld der Ziel-Ressource
                    $newResource->set('content', $newHtmlContent);
                    $newResource->save();
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Position im Text nicht gefunden für Quell-Ressource ID ' . $sourceResource['id']);
                }
            } else {
                $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ziel-Ressource nicht gefunden für ID ' . $newResourceID);
            }
        } else {
            // Keine gemappte ID für diese Quell-Ressource gefunden
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ziel-ID nicht gefunden für Quell-Ressource ID ' . $sourceResource['id']);
        }
    }
}




/*

// Funktion zum Einfügen von Inhalten in Ressourcen
function insertRessource($Ressourcelinks, $fileContent, $langCode, $modx, &$IDMap) {
    foreach ($RessourceLinks as $sourceResource) {
        // Überprüfe, ob es ein gemapptes Ziel für die aktuelle Ressource gibt
        if (isset($IDMap['resource'][$sourceResource['id']])) {
            $newResourceID = $IDMap['resource'][$sourceResource['id']];

            // Lade die Ressource
            $newResource = $modx->getObject('modResource', $newResourceID);

            // Überprüfe, ob die Ressource gefunden wurde
            if ($newResource) {
                // Suche die passende Position im Text
                $position = array_filter($positionText, function ($pos) use ($sourceResource) {
                    return $pos['file_line'] == $sourceResource['file_line'];
                });

                if (!empty($position)) {
                    $position = reset($position);
                    $newContent = $position['file_content'];

                    // Füge den neuen Text in das HTML-Feld der Ziel-Ressource ein
                    $htmlLine = $sourceResource['html_line'];
                    $newHtmlContent = str_replace($position['file_content'], $newContent, $htmlLine);
                    
                    // Aktualisiere das HTML-Feld der Ziel-Ressource
                    $newResource->set('content', $newHtmlContent);
                    $newResource->save();
                } else {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Position im Text nicht gefunden für Quell-Ressource ID ' . $sourceResource['id']);
                }
            } else {
                $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ziel-Ressource nicht gefunden für ID ' . $newResourceID);
            }
        } else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Ziel-ID nicht gefunden für Quell-Ressource ID ' . $sourceResource['id']);
        }
    }
}

*/

/*                           (In Development) Chunks in allgetTogheter                                          */

/*
    function allgetTogheter () {
          // Dupliziere die Chunks von der Kategorie DE in die neue Kategorie
            $duplicateIDChunk=duplicateChunks($Chunklinks,$langCategory, $modx);

            // Füge die Inhalte, in der neuen Kategorie, in den Chunks ein, abhängig von der Sprache
            $insertChu=insertChunks($Chunklinks, $fileContent, $langCategory, $modx);
    }

// Funktion zum Duplizieren von Chunks in neue Kategorie
function duplicateChunks($Chunklinks, $langCategory, $modx) {
    foreach ($Chunklinks as $links){
        // Dupliziere die Chunks, abhänging von der ID aus den $Chunklinks, in die neue Kategorie ein
        $chunk = $modx->getObject('modChunk', $links['id']);
        $newChunk = $chunk->duplicate($langCategory);
        $newChunk->save();
    }
}

function InsertChunks($Chunklinks, $fileContent, $langCategory, $modx) {
    foreach ($Chunklinks as $links){
        // Füge anhand der tv_line und file_line die Inhalte in die Chunks ein
        $chunk = $modx->getObject('modChunk', $links['id']);
        $htmlContent = $chunk->getContent();
        $newHtmlContent = insertContent($htmlContent, $fileContent, $links['html_line'], $links['file_line']);
        $chunk->setContent($newHtmlContent);
        $chunk->save();
    }
}
*/


/*                           (In Development) Template Variable                                            */
/*

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

// Funktion zur Ermittlung der Positionen der Template-Variablen
function getPositionTV($webResources, $modx) {
    $pos_TV = array();

    foreach ($webResources as $resource) {
        foreach ($resource['tv_variable'] as $tv) {
            // Ignoriere Tags der Template-Variablen
            $cleanedTVContent = strip_tags($tv['tv_value']);

            // Suche nach Positionen im Template-Variablen-Text
            $linesTV = explode("\n", $cleanedTVContent);
            foreach ($linesTV as $i => $line) {
                $pos_TV[] = [
                    'resource_id' => $resource['id'],
                    'tv_line' => $i + 1, // Startet mit 1
                    'tv_content' => $line,
                ];
            }
        }
    }

    // Hier hast du nun alle Positionen der Template-Variablen mit den Zeilennummern und den Inhalten der Zeilen
    return $pos_TV;
}

// Funktion zum Extrahieren des Textinhalts aus dem JSON-ähnlichen String
function filterTextFromTV($jsonString) {
    // Hier definieren wir ein einfaches Pattern, das "name:" oder "CaptionText:" und den Text danach erfasst
    $pattern = '/"name":"(.*?)"|"CaptionText":"(.*?)"/';

    // Hier führen wir eine preg_match durch, um die erste Übereinstimmung zu finden
    preg_match($pattern, $jsonString, $matches);

    // Hier geben wir den ersten Nicht-leeren Eintrag zurück oder null, wenn keiner vorhanden ist
    return isset($matches[1]) ? $matches[1] : (isset($matches[2]) ? $matches[2] : null);
}
*/
