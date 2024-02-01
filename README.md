# ModX Script Automate

Dieses Skript ermöglicht die Automatisierung von Sprachen in vorhandenem Kontext, einschließlich Ressourcen wie Template-Variablen und Chunks.

## Installation

1.  Erstellen Sie einen neuen Kontextnamen und eine Ressource. Verlinken Sie die Ressource mit einem Snippet.

  Beispiel Snippetaufruf:
```html
[[!snipptenamen]]
```
  **Beachten Sie die http_url und base_url erstellt haben**

2.  Erstellen Sie ein Snippet mit folgendem Code:
   
  ```php
  <?php
  // Die URL zur externen PHP-Datei auf GitHub beachten Sie auf die Versionsnummer
  $url = 'https://raw.githubusercontent.com/nhitho/ModX-Script-Automate/main/Automate-Content-v-01-alpha.php';

  // Datei von der URL abrufen
  $content = file_get_contents($url);

  // Inhalt der externen PHP-Datei ausführen
  eval('?>' . $content);

  ```

3.  Erstellen Sie im Media-Browser zwei Ordner im Assets.

    a. Einmal im `assets/txt-originale`.

    b. Einmal im `assets/txt-languages`.

4.  Laden Sie Ihre Originaltextdatei in den Ordner `txt-originale` hoch (im Originalkontext samt Ressourcen). In `txt-languages` sollten die Übersetzungen in anderen Sprachen gespeichert werden.

   **Achten Sie darauf, dass die Zeilen in den Übersetzungsdateien identisch mit denen in der Originaldatei sind.**

## HTML Ausgabe für den Kontext oder Friendly URL

  Es gehen zwei Schritte um dies zu realisieren.

### HTML-Ausgabe für den Kontext:

  Navigieren Sie im MODX-Manager zu "System" -> "Kontexte".
  Wählen Sie den Kontext mit dem Namen "script" aus.
  Unter dem Tab "Einstellungen" finden Sie den Abschnitt "Optionen".
  Hier können Sie die Optionen base_url und http_host verwenden, um die Basis-URL und den Host für den Kontext festzulegen.
  Beispiel:

  ```plaintext
  base_url = "/script/"
  http_host = "example.com"
  ```
### Friendly URLs konfigurieren:

1.  Öffnen Sie den MODX-Manager:

    Melden Sie sich im MODX-Manager an.
2.  Navigieren Sie zu den Systemeinstellungen:

    Klicken Sie auf den Tab "Extras" im Hauptmenü.
    Wählen Sie "Einstellungen" und dann "Weiterleitungen und Verlinkungen".
3.  Ändern Sie die base_url für Friendly URLs:

    Suchen Sie nach einer Einstellung mit dem Namen base_url oder etwas Ähnlichem.

    Ändern Sie den Wert auf "/script/":

    ```html
    base_url = "/script/"
    ```

    Speichern Sie die Änderungen.
  
4.  Leeren Sie den Cache:

    Nachdem Sie die Einstellungen geändert haben, leeren Sie den MODX-Cache, um sicherzustellen, dass die Änderungen wirksam werden.
    Gehen Sie dazu zum Tab "Extras" und wählen Sie "Cache leeren".
