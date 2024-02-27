<?php
// Beispielaufruf
$sourceContextKey = 'web';
$newContextKey = 'da'; // Beispiel: Neue Kontextschlüssel für Russisch

if (duplicateContext($modx, $sourceContextKey, $newContextKey)) {
    // Erfolgreich dupliziert
    echo 'Kontext erfolgreich dupliziert.';
} else {
    // Fehler bei der Kontextduplizierung
    echo 'Fehler bei der Kontextduplizierung.';
}

function duplicateContext($modx, $sourceContextKey, $newContextKey) {
    // 1. Lade den aktuellen Kontext samt Ressourcen
    $sourceContext = $modx->getObject('modContext', ['key' => $sourceContextKey]);
    if (!$sourceContext) {
        // Fehlerbehandlung: Kontext nicht gefunden
        return false;
    }

    // 2. Dupliziere den Kontext samt Ressourcen
    $newContext = $modx->newObject('modContext');
    $newContext->fromArray($sourceContext->toArray());
    $newContext->set('key', $newContextKey);
    $newContext->set('description', 'Neuer Kontext'); // Optional: Ändern Sie die Beschreibung nach Bedarf
    $newContext->save();

    // 3. Benenne die Ressourcen des neuen Kontexts um (rekursiv)
    $sourceResources = $modx->getCollection('modResource', ['context_key' => $sourceContextKey, 'parent' => 0]);
    foreach ($sourceResources as $resource) {
        duplicateResource($modx, $resource->get('id'), $newContextKey);
    }

    return true;
}

function duplicateResource($modx, $sourceResourceId, $newContextKey, $parent = 0) {
    $sourceResource = $modx->getObject('modResource', $sourceResourceId);
    if (!$sourceResource) {
        return;
    }

    // Kopiere die Ressource in den neuen Kontext
    $newResource = $modx->newObject('modResource');
    $newResource->fromArray($sourceResource->toArray());
    $newResource->set('context_key', $newContextKey);
    $newResource->set('parent', $parent);
    $newResource->save();

    // Kopiere rekursiv die untergeordneten Ressourcen
    $sourceChildren = $modx->getCollection('modResource', ['parent' => $sourceResourceId]);
    foreach ($sourceChildren as $child) {
        duplicateResource($modx, $child->get('id'), $newContextKey, $newResource->get('id'));
    }
}