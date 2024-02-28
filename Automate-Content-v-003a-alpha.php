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
    $RessourceLinks = compareContentWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold);
    
    #$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($RessourceLinks, true));

    // Lade Inhalte von Chunks
    $ChunkLinks = compareChunksWithTxt($txt_Doc_A, $ignoreTags, $modx, $threshold);

    #$modx->log(xPDO::LOG_LEVEL_ERROR, print_r($ChunkLinks, true));
    
    // Füge die Inhalte in die neuen Kontext, Kategorieren und Chunks ein
    allgetTogether($txt_Doc_B, $RessourceLinks, $ChunkLinks, $modx);
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
function allgetTogether ($txt_Doc_B, $Contentlinks, $Chunklinks, $modx){

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

            // Überprüfe ob Kontext bereits existiert, wenn nicht erstelle Sie
            if (!$langContext = $modx->getContext($langCode)) {
                $langContext = createLangContext($langCode, $productCode,$modx);
            }
            // Überprüfe ob Kategorie bereits existiert, wenn nicht erstelle Sie
            if (!$langCategory = $modx->getObject('modCategory', array('category' => $langCode))) {
                $langCategory = createLangCategory($langCode, $productCode, $modx);
            }

            // Dupliziere die von 'Web' Ressourcen in den neuen Kontext
            #$duplicateIDRessource=duplicateResources($Ressourcelinks, $langContext, $modx, $langContext);
            
        /*    // Dupliziere die Chunks von der Kategorie DE in die neue Kategorie
            $duplicateIDChunk=duplicateChunks($Chunklinks,$langCategory, $modx);

            // Füge die Inhalte, im neuen Kontext, in den Ressourcen ein, abhängig von der Sprache
            $insertCon=insertRessource($Ressourcelinks, $fileContent, $langContext, $modx);

            // Füge die Inhalte, in der neuen Kategorie, in den Chunks ein, abhängig von der Sprache
            $insertChu=insertChunks($Chunklinks, $fileContent, $langCategory, $modx);


            // Ausgabe
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Kontext, Kategorie, Chunks, Ressouren wurden erfolgreich dupliziert, erstellt und eingefügt.'); */
        }
    }
}

// Funktion zum Erstellen eines Kontexts (falls nicht vorhanden)
function createLangContext($langCode, $productCode, $modx) {
    // Überprüfe, ob der Kontext bereits existiert
    $existingContext = $modx->getObject('modContext', array('key' => $langCode));

    if ($existingContext) {
        // Debug: Ausgabe der Eigenschaften des vorhandenen Kontexts
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Kontext bereits vorhanden: ' . print_r($existingContext->toArray(), true));

        // Kontext bereits vorhanden, gib das vorhandene Kontextobjekt zurück
        return $existingContext;
    } else {
        // Kontext erstellen, wenn nicht vorhanden
        $newContext = $modx->newObject('modContext');

        // Eigneschaften Einstellung für den neuen Kontext
        $newContext->set('key', $langCode);
        $newContext->set('name', strtoupper($langCode));
        $newContext->set('description', strtoupper($productCode));
        $newContext->set('rank', 0);
        $newContext->set('menuindex', 0);
        $newContext->set('default', 0);
        $newContext->save();

        // Füge die base_url als Kontexteinstellung hinzu
        $baseURLSetting = $modx->newObject('modContextSetting');
        $baseURLSetting->set('key', 'base_url');
        $baseURLSetting->set('xtype', 'textfield');
        $baseURLSetting->set('value', '/' . $langCode . '/');
        $baseURLSetting->set('namespace', 'core');
        $baseURLSetting->set('area', 'web');
        $baseURLSetting->set('context_key', $langCode); 
        $baseURLSetting->save();

        // Füge cultureKey als Kontexteinstellung hinzu
        $cultureKeySetting = $modx->newObject('modContextSetting');
        $cultureKeySetting->set('key', 'cultureKey');
        $cultureKeySetting->set('xtype', 'textfield');
        $cultureKeySetting->set('value', $langCode);
        $cultureKeySetting->set('namespace', 'core');
        $cultureKeySetting->set('area', 'web');
        $cultureKeySetting->set('context_key', $langCode); // Verknüpfe die Einstellung mit dem Kontext
        $cultureKeySetting->save();

        // Füge error_page als Kontexteinstellung hinzu
        $errorPageSetting = $modx->newObject('modContextSetting');
        $errorPageSetting->set('key', 'error_page');
        $errorPageSetting->set('xtype', 'numberfield');
        $errorPageSetting->set('value', 1); // Hier sollte die ID deiner Fehlerseite stehen
        $errorPageSetting->set('namespace', 'core');
        $errorPageSetting->set('area', 'web');
        $errorPageSetting->set('context_key', $langCode);
        $errorPageSetting->save();


        // Speichere den neuen Kontext
        if ($newContext->save() === false) {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler beim Erstellen des Kontexts: ' . print_r($newContext->error, true));
            return null;
        }
        // Debug: Ausgabe der Eigenschaften des neuen Kontexts nach dem Speichern
        # $modx->log(xPDO::LOG_LEVEL_ERROR, 'Eigenschaften des neuen Kontexts nach dem Speichern: ' . print_r($newContext->toArray(), true));


    // Gebe das erstellte Kontextobjekt zurück
    return $newContext;
    }
}

//Funktion zum Erstellen einer Kategorie
// Funktion zum Erstellen einer Kategorie (Hauptkategorie oder Unterkategorie)
function createLangCategory($langCode, $productCode = null, $modx){
    // Erstelle die Hauptkategorie
    $mainCategory = $modx->newObject('modCategory');
    $mainCategory->set('category', $langCode);
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

// Funktion zum Duplizieren von Ressourcen in neuen Kontext
function duplicateResources($Ressourcelinks, $langContext, $modx) {
    foreach ($Ressourcelinks as $links){
        // Dupliziere die Ressourcen, abhänging von der ID aus den $Ressourcelinks, in die neue Kontext ein
        $resource = $modx->getObject('modResource', $links['id']);
        $newResource = $resource->duplicate($langContext);
        $newResource->save();
    }
}

// Funktion zum Einfügen von Inhalten in Ressourcen
function insertRessource($Ressourcelinks, $fileContent, $langContext, $modx) {
    foreach ($Ressourcelinks as $links){
        // Füge anhand der html_line und file_line die Inhalte in die Ressourcen ein
        $resource = $modx->getObject('modResource', $links['id']);
        $htmlContent = $resource->getContent();
        $newHtmlContent = insertContent($htmlContent, $fileContent, $links['html_line'], $links['file_line']);
        $resource->setContent($newHtmlContent);
        $resource->save();
    }
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

// Funktion zum Einfügen von Inhalten
function insertContent($htmlContent, $fileContent, $htmlLine, $fileLine) {
    $htmlLines = explode("\n", $htmlContent);
    $fileLines = explode("\n", $fileContent);

    $htmlLines[$htmlLine - 1] = $fileLines[$fileLine - 1];

    return implode("\n", $htmlLines);
}


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
