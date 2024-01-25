<?php

// Laden Sie das MODX-System
require_once MODX_BASE_PATH . 'core/model/modx/modx.class.php';
require_once MODX_BASE_PATH . 'core/config/config.inc.php';
require_once MODX_BASE_PATH . 'config.core.php';

// Initialisieren Sie das MODX-Objekt
$modx = new modX();
$modx->initialize('web');

// Kontextnamen
$kontextNameA = 'web';

// Basis-Ordner-Überwachung
$txtDokumentA = MODX_BASE_PATH . 'assets/txt-original';
$txtDokumentB = MODX_BASE_PATH . 'assets/txt-languages';

// Überprüfe, ob die TXT-Ordner existieren
if (file_exists($txtDokumentA) && file_exists($txtDokumentB)) {
    // Vor dem Durchlauf der TXT-Dateien im ersten Ordner
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Starte den Durchlauf der TXT-Dateien im ersten Ordner.');

    // Durchlaufe alle TXT-Dateien im ersten Ordner
    $gefundeneDateienA = glob($txtDokumentA . '/*.txt');

    // Überprüfe, ob Dateien im ersten Ordner gefunden wurden
    if (empty($gefundeneDateienA)) {
        // Debug-Ausgabe, wenn keine Dateien im ersten Ordner gefunden wurden
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Keine Dateien im ersten Ordner gefunden.');
    }

    foreach ($gefundeneDateienA as $txtDateiA) {
        // Inhalte der TXT-Datei lesen
        $txtInhaltA = file_get_contents($txtDateiA);

    /*    // Funktion zur Überprüfung auf schädlichen Code
        if (hatSchadhaftenCode($txtInhaltA, $modx, $txtDateiA)) {
            // Wenn schädlicher Code gefunden wurde, blockiere die Anfrage oder ergreife geeignete Maßnahmen
            exit('Sicherheitsverletzung: Schädlicher Code erkannt in ' . $txtDateiA);
        }
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Nach dem Aufruf der Funktion hatSchadhaftenCode.');
        */
        // Durchlaufe alle TXT-Dateien im zweiten Ordner
        $gefundeneDateienB = glob($txtDokumentB . '/*.txt');

        if (empty($gefundeneDateienB)) {
            // Debug-Ausgabe, wenn keine Dateien im zweiten Ordner gefunden wurden
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Keine Dateien im zweiten Ordner gefunden.');
        }

        foreach ($gefundeneDateienB as $txtDateiB) {
            // Inhalte der TXT-Datei lesen
            $txtInhaltB = file_get_contents($txtDateiB);

      /*      // Funktion zur Überprüfung auf schädlichen Code
            if (hatSchadhaftenCode($txtInhaltB, $modx, $txtDateiB)) {
                // Wenn schädlicher Code gefunden wurde, blockiere die Anfrage oder ergreife geeignete Maßnahmen
                exit('Sicherheitsverletzung: Schädlicher Code erkannt in ' . $txtDateiB);
            }*/
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Vor dem Aufruf der Funktion vergleicheUndErsetze.'); 
            // Rufe die Funktion auf und übermittle die Variablen nur einmal pro Datei im ersten Ordner
            vergleicheUndErsetze($modx, $kontextNameA, $txtDateiA, $txtDokumentB);

            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Nach dem Aufruf der Funktion vergleicheUndErsetze.');
        }
    }
} else {
    // Debug-Ausgabe, wenn die TXT-Ordner nicht existieren
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: TXT-Ordner existieren nicht.');
    $modx->log(xPDO::LOG_LEVEL_ERROR, 'Keine *.txt Dokumente im den Ordner vorhanden.');
}

function vergleicheUndErsetze($modx, $kontextNameA, $txtDokumentA, $txtDokumentB) {
    // Lade Ressourcen aus dem ModX-Kontext $kontextNameA
    $modXInhalteA = ladeModXInhalte($modx, $kontextNameA);
     // Überprüfe, ob der Schlüssel "ressourcenIDs" im Array vorhanden ist
    if (isset($modXInhalteA['ressourcenIDs'])) {
        $ressourcenIDsA = $modXInhalteA['ressourcenIDs'];  // Beachten Sie die Änderung hier
        $ressourcenInhalteA = $modXInhalteA['ressourcen'];
    }
    else {
        // Falls der Schlüssel nicht vorhanden ist, geben Sie eine Fehlermeldung aus oder ergreifen Sie geeignete Maßnahmen
        $modx->log(xPDO::LOG_LEVEL_ERROR, 'Fehler: Schlüssel "ressourcenIDs" nicht im Array vorhanden.');
        return;
    }

    // Lade Ressourcen aus dem ModX-Kontext $kontextNameA und dupliziere sie
    $zuordnungDerIds = dupliziereKontextARessourcen($modx, $kontextNameA);

    // Führe den Vergleich durch und speichere die Ergebnisse
    $ergebnisse = vergleicheTextArraysModX($ressourcenInhalteA, $txtDokumentA);

    // Durchlaufe die Ergebnisse und verarbeite sie
    if (is_array($ergebnisse)) {
        foreach ($ergebnisse as $ergebnis) {
            $idDerUrsprünglichenRessource = $ergebnis['ressourceId'];
            $zeilennummerRessourceA = $ergebnis['zeilennummerRessource'];
            $zeilennummerTXT = $ergebnis['zeilennummerTXT'];

            // Lade die Platzhalter-Ressource in Kontext A
            $verknuepfteIds = $zuordnungDerIds[$idDerUrsprünglichenRessource];
            $platzhalterRessourceA = $modx->getObject('modResource', $verknuepfteIds['kontextA']);

            // Lade die Original-Ressource in Kontext A
            $originalRessourceA = $modx->getObject('modResource', $idDerUrsprünglichenRessource);

            // Hier kannst du die Zeilennummern und Inhalte der Ressourcen abrufen
            $inhaltRessourceA = getRessourcenInhaltInZeile($originalRessourceA, $zeilennummerRessourceA);
            $inhaltTXT = getTXTInhaltInZeile($txtDokumentB, $zeilennummerTXT);

            // Füge den Inhalt aus XML-Dokument B in die Platzhalter-Ressource in Kontext A ein
            $platzhalterRessourceA->setContent($inhaltTXT);
            $platzhalterRessourceA->save();
        }
    }
    else {
        // Fügen Sie hier ggf. Debugging-Ausgaben hinzu, um den Fluss zu überprüfen
        error_log('Keine Ergebnisse gefunden.');
    }
}

function ladeModXInhalte($modx, $ressourcenKontext) {
    $ressourcen = $modx->getCollection('modResource', ['context_key' => $ressourcenKontext]);

    $ressourcenInhalte = [];
    $ressourcenIDs = [];
    $ressourcenParent = [];
    $ressourcenAlias = []; 
    foreach ($ressourcen as $ressource) {
        $ressourcenIDs[] = $ressource->get('id'); 
        $ressourcenParent[] = $ressource->get('parent');
        $ressourcenAlias[] = $ressource->get('alias');
        $ressourcenInhalte[] = $ressource->get('content');
    }

    return [
        'ressourcen' => $ressourcenInhalte,
        'ressourcenIDs' => $ressourcenIDs, 
        'ressourcenParent' => $ressourcenParent,
        'ressourcenAlias' => $ressourcenAlias,
    ];
}

function dupliziereKontextARessourcen($modx, $kontextNameA) {
    // Lade Ressourcen aus dem vorhandenen Kontext A
    $modXInhalteA = ladeModXInhalte($modx, $kontextNameA);
    $ressourcenIDsA = $modXInhalteA['ressourcenIDs'];

    // Hier initialisieren wir die Zuordnung der IDs
    $zuordnungDerIds = [];

    // Durchlaufe alle Ressourcen aus dem vorhandenen Kontext A und dupliziere sie
    foreach ($ressourcenIDsA as $ressourceIDs) {
        // Hier implementierst du die Logik zum Duplizieren der Ressource
        $neuePlatzhalterRessourceId = duplizierePlatzhalterRessource($modx, $ressourceIDs, $kontextNameA);

        // Füge die Zuordnung hinzu
        $zuordnungDerIds[$ressourceIDs] = [
            'kontextA' => $ressourceIDs,
            'kontextB' => $neuePlatzhalterRessourceId->get('id'),
        ];
    }

    // Rückgabe der Zuordnung der IDs
    return $zuordnungDerIds;
}

function duplizierePlatzhalterRessource($modx, $idDerUrsprünglichenRessource, $kontextNameA) {
    // Hole die Daten der ursprünglichen Ressource
    $urspruenglicheRessource = $modx->getObject('modResource', $idDerUrsprünglichenRessource);

    // Extrahiere Produkt- und Sprachnamen aus dem Dateinamen
    $dateiName = $urspruenglicheRessource->get('pagetitle');
    $ergebnis = extrahiereProduktUndSprache($dateiName);
    $produktName = $ergebnis['produktName'];
    $sprachKontext = $ergebnis['sprachKontext'];

    // Setze den neuen Kontextnamen für die Duplikat-Ressource
    $neuerKontextName = $produktName . '_' . $sprachKontext;

    // Prüfe, ob der Kontext bereits existiert
    $neuerKontext = $modx->getObject('modContext', ['key' => $neuerKontextName]);
    if (!$neuerKontext) {
        // Erstelle den neuen Kontext, falls er nicht existiert
        $neuerKontext = $modx->newObject('modContext');
        $neuerKontext->set('key', $neuerKontextName);
        $neuerKontext->save();
    }

    // Dupliziere die Ressource im neuen Kontext
    $neuePlatzhalterRessource = $urspruenglicheRessource->duplicate([
        'newContext' => $neuerKontext->get('key'),
        'newParent' => 0, // Falls die Ressource die oberste Ebene sein soll
        'duplicateChildren' => true,
    ]);

    // Rückgabe der ID der neuen Platzhalter-Ressource
    return $neuePlatzhalterRessource->get('id');
}

function getRessourcenInhaltInZeile($modx, $ressourcenId, $zeilennummer) {
    // Lade die Ressource
    $ressource = $modx->getObject('modResource', $ressourcenId);

    // Überprüfe, ob die Ressource gefunden wurde
    if ($ressource) {
        // Teile den Inhalt der Ressource in Zeilen auf
        $ressourcenInhaltZeilen = explode(PHP_EOL, $ressource->get('content'));

        // Überprüfe, ob die Zeilennummer existiert
        if (isset($ressourcenInhaltZeilen[$zeilennummer - 1])) {
            // Gib den Inhalt der angegebenen Zeile zurück
            return $ressourcenInhaltZeilen[$zeilennummer - 1];
        } else {
            // Gib eine Fehlermeldung zurück, wenn die Zeilennummer nicht existiert
            return 'Fehler: Die angegebene Zeile existiert nicht in der Ressource mit ID ' . $ressourcenId;
        }
    } else {
        // Gib eine Fehlermeldung zurück, wenn die Ressource nicht gefunden wurde
        return 'Fehler: Ressource mit ID ' . $ressourcenId . ' wurde nicht gefunden.';
    }
}

function getTXTInhaltInZeile($txtDatei, $zeilennummer) {
    // Lese den Inhalt der XML-Datei
    $txtInhalt = file_get_contents($txtDatei);

    // Zerlege den Inhalt in ein Array von Zeilen
    $txtZeilen = explode("\n", $txtInhalt);

    // Überprüfe, ob die angegebene Zeilennummer existiert
    if (isset($txtZeilen[$zeilennummer - 1])) {
        // Extrahiere den Inhalt der angegebenen Zeile
        $zeilenInhalt = $txtZeilen[$zeilennummer - 1];

        // Rückgabe des Inhalts der Zeile
        return $zeilenInhalt;
    } else {
        // Rückgabe einer Fehlermeldung, falls die Zeilennummer nicht existiert
        return 'Fehler: Die angegebene Zeilennummer existiert nicht in der XML-Datei.';
    }
}

function extrahiereProduktUndSprache($dateiname) {
    // Extrahiere den Produktname und die Kontext-Sprache aus dem Dateinamen
    $muster = '/^(.*?)\-(.*?)\.txt$/';
    preg_match($muster, $dateiname, $treffer);

    // Überprüfe, ob die Musterübereinstimmung erfolgreich war
    if (count($treffer) === 3) {
        // Extrahiere den Produktname und die Kontext-Sprache
        $produktname = $treffer[1];
        $kontextSprache = $treffer[2];

        // Rückgabe der extrahierten Daten als Assoziatives Array
        return [
            'produktname' => $produktname,
            'kontextSprache' => $kontextSprache,
        ];
    } else {
        // Rückgabe einer Fehlermeldung, falls das Muster nicht übereingestimmt hat
        return 'Fehler: Der Dateiname entspricht nicht dem erwarteten Muster.';
    }
}

function vergleicheTextArraysModX($ressourcenInhalte, $txtInhalt) {
    // Initialisiere die Ergebnisse
    $ergebnisse = [];

    // Durchlaufe die extrahierten Ressourceninhalte
    foreach ($ressourcenInhalte as $ressourceIDs => $ressourceInhalt) {
        foreach ($txtInhalt as $zeilennummer => $txtZeile) {
            $extractedXml = ignoreTags($txtZeile);
            $extractedRessourcen = array_map('ignoreTags', $ressourceInhalt);

            // Vergleiche mit dem extrahierten XML-Inhalt
            similar_text($ressourceInhalt, $extractedXml, $prozent);

            // Falls Ähnlichkeit über einem bestimmten Schwellenwert liegt (hier 90%)
            if (isset($prozent) && $prozent >= 90) {
                // Speichere die Ergebnisse
                $ergebnisse[] = [
                    'ressourceIDs' => $ressourceIDs,
                    'zeilennummerRessource' => $zeilennummer,
                    'zeilennummerXml' => $zeilennummer,
                    'textRessource' => $ressourceInhalt,
                ];
            }
        }
    }
    // Hier kannst du die $ergebnisse weiterverarbeiten oder ausgeben
    return $ergebnisse;
}

// Hier wird die Funktion ignoreTags verwendet, um den reinen Text zwischen Tags zu extrahieren
function ignoreTags($text) {
    // Muster für den reinen Inhalt zwischen Tags finden und extrahieren
    $pattern = '/<[^>]*>(.*?)<\/[^>]*>/s';
    preg_match_all($pattern, $text, $matches);
    return implode('', $matches[1]);
}

/*
function hatSchadhaftenCode($txtInhalt, $modx, $txtDatei) {
    // Definiere Muster, nach denen im XML-Inhalt gesucht werden soll
    $keywords_pattern = array(
        'SQL' => '/\b(?:SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER)\b/i',                // Versuche für SQL-Injection
        'JavaScript/HTML' => '/<script\b[^>]*>.*<\/script>/i',                              // JavaScript/HTML-Injection
        'PHP' => '/<\?php.*\?>/i',                                                          // PHP-Code
        'PHP Object Injection' => '/\b(?:unserialize|__wakeup|Serializable)\b/i',
        'Dateieinbindung' => '/<\?include.*\?>/i',                                          // Versuche für Dateieinbindung
        'Systembefehle' => '/\b(?:system|exec|shell_exec|passthru)\b/i',                    // Systembefehle
        'XSS' => '/\b(?:alert|prompt|confirm|document\.write)\b/i',                         // XSS-Versuche
        'XSS' => '/<(script|img|svg|iframe|input|a|form|embed|object|style|meta).*?>/i', 
        'Base64' => '/base64_decode\s*\(\s*["\']?[a-zA-Z0-9+\/=]+["\']?\s*\)/i',            // Base64-kodierte Inhalte
        'Kommentierte PHP-Tags' => '/<!--.*<\?php.*-->.*-->/s',                             // Kommentierte PHP-Tags
        'Path Traversal' => '/\.\.(?:\/|\\|%2F|%5C)/i',                                     // Strukturaufruf-Versuche
        'Remote Code Execution' => '/\b(?:eval|assert|system|exec|shell_exec|passthru)\b/i',// Remote-Versuche
        'CSRF' => '/<input.*\s+name=[\'"]?token[\'"]?.*?>/i',                               // Token-Versuche
    );

    $txtInhalt = file_get_contents($txtDatei);

    // Überprüfe auf CDATA-Sektionen
    if (strpos($txtInhalt, '<![CDATA[') !== false) {
        // Warnung oder Blockierung
        $error = 'Schädlicher Code gefunden: CDATA-Sektionen entdeckt in ' . $txtDatei;
        debug($error, $modx);
        return true;
    }

    // Führe die Sicherheitsüberprüfung durch
    $found_keywords_pattern = array();
    foreach ($keywords_pattern as $keyword_pattern => $pattern) {
        if (preg_match($pattern, $txtInhalt, $hit)) {
            $found_keywords_pattern[] = $keyword_pattern;
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Debug: Gefundenes Muster - ' . $keyword_pattern);
        }
    }

    // Wenn schädlicher Code gefunden wurde
    if (!empty($found_keywords_pattern)) {
        $error = 'Schädlicher Code gefunden: ' . implode('; ', $found_keywords_pattern);
        debug($error, $modx);
        return true;
    }

    // Wenn kein schädlicher Code gefunden wurde
    $debugmessage = 'Kein schädlicher Code gefunden in ' . $txtDatei;
    $modx->log(xPDO::LOG_LEVEL_INFO, $debugmessage );
    return false;
} */