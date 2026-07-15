# Simulation des Matthäus-Effekts (Yule-Prozess)

Dieses Programm zur Simulation des Matthäus-Effekts wurde ursprünglich im Rahmen einer sozialwissenschaftlichen Dissertation entwickelt, um akkumulative Prozesse in bibliometrischen Datensätzen methodisch zu prüfen.

Die Notwendigkeit einer Eigenentwicklung ergab sich aus dem Umstand, dass für die spezifische Fragestellung – die mathematische Überprüfung, ob eine Häufung von Nennungen eines einzelnen Autors in einer Literaturliste durch Zufallsmechanismen oder den Matthäus-Effekt erklärbar ist – nach aktuellem Kenntnisstand keine direkt verfügbare, zeitgemäße Softwarelösung identifiziert werden konnte. Es ist hierbei nicht auszuschließen, dass in der Vergangenheit vergleichbare Implementierungen (etwa in älteren Programmiersprachen wie C oder Visual Basic) entwickelt wurden, die jedoch außerhalb des unmittelbaren Forschungsumfelds nicht öffentlich dokumentiert oder zugänglich geblieben sind.

Ziel dieser Publikation ist es, eine moderne und transparent nachvollziehbare Implementierung des Yule-Prozesses zur Verfügung zu stellen. Die Software dient primär der Erforschung schiefer Verteilungen, bei denen soziale Komponenten eine Rolle spielen, und bietet Wissenschaftlern die Möglichkeit, entsprechende Modelle methodisch fundiert zu testen, für die noch keine analytische Grenzverteilung abgeleitet werden konnte.

Durch die Bereitstellung des originalen Archiv-Codes der Dissertations-Version neben der modernisierten Implementierung wird zudem die algorithmische Kontinuität gewahrt, was die wissenschaftliche Nachvollziehbarkeit und Validierung der Ergebnisse über die Zeit hinweg sicherstellen soll.

---

## Funktionsweise und theoretische Grundlage

Die Simulation basiert auf einem auf Potenzgesetzen aufbauenden Verfahren, dem **Yule-Prozess**. Das Modell bildet die Akkumulation von Beiträgen ab, bei der sich durch eine selbstverstärkende Rückkopplung der Matthäus-Effekt („Wer hat, dem wird gegeben“) vollzieht.

Der Algorithmus nutzt ein gewichtetes Losverfahren: Zu Beginn existiert ein Autor mit einer Publikation. In jedem Schritt werden der Stichprobe zwei Einheiten hinzugefügt – eine für einen neuen Autor, eine für einen bereits vorhandenen, wobei letztere basierend auf der bisherigen Publikationsanzahl verlost wird. Ein Autor mit zehn Beiträgen hat somit eine zehnfach höhere Wahrscheinlichkeit für eine weitere Nennung als ein Autor mit nur einem Beitrag.

### Bedeutung der Zufallsvariablen und Prozessstabilität
Ein wesentliches Charakteristikum der Simulation ist die integrierte Zufallsvariable. Da jeder Durchlauf als ein voneinander unabhängiges Ereignis betrachtet werden muss, weist das Modell eine systemimmanente Varianz auf:

*   **Pfadabhängigkeit:** Eine starke Ungleichverteilung, die sich bereits zu Beginn des Prozesses durch Zufall einstellt, kann den weiteren Verlauf massiv verzerren. Dies führt dazu, dass die Dominanz eines Autors nicht linear-proportional zur absoluten Textanzahl wächst, sondern in Einzelfällen zu Sprüngen in der Beitragsdichte führen kann.
*   **Stabilität vs. Skalierung:** Die Unabhängigkeit der Durchläufe impliziert, dass die Schwankungsbreite der Ergebnisse bei einer sehr hohen Anzahl von Durchläufen zwar theoretisch gegen einen Erwartungswert konvergiert, in der Praxis jedoch die „Ausreißer“ – bedingt durch die frühe Zufallsverteilung – die interpretativen Grenzen des Wachstums aufzeigen.
*   **Forschungspraktische Limitierung:** Während das Modell mathematisch auf eine unendliche Anzahl von Durchläufen ausgelegt ist, hat sich für die wissenschaftliche Praxis eine Obergrenze von ca. 2.000 Durchläufen als sinnvoll erwiesen. Diese Menge erlaubt eine belastbare statistische Einordnung der Ergebnisse, ohne die systemische Verzerrung durch die Zufallsvariable bei extrem hohen Durchlaufzahlen zu unterschätzen.

Diese methodische Transparenz ist notwendig, um zu verstehen, warum hohe Beitragszahlen in realen Literaturlisten nicht zwingend als rein statistisches Ergebnis des Matthäus-Effekts zu werten sind, sondern – bei Abweichung von der simulierten Schwankungsbreite – auf externe Einflussfaktoren hindeuten können.

---

## Visualisierung der Ergebnisse

Das Tool bietet eine integrierte grafische Auswertung der Simulationsläufe. Statt einer rein tabellarischen Datenhaltung ermöglicht die visuelle Darstellung eine intuitive Erfassung der stochastischen Dynamik:

*   **Verlaufsanalyse:** Die Grafik stellt den Höchstwert (die maximale Beitragszahl eines einzelnen Autors) pro Durchgang dar.
*   **Identifikation von Extremwerten:** Ein dediziert markierter „Rekordwert“ (hervorgehoben durch eine abweichende Farbgebung) verdeutlicht sofort die Varianz der Simulation. Dies erlaubt es dem Nutzer, den Einfluss der Zufallsvariablen auf den Matthäus-Effekt unmittelbar zu validieren.
*   **Strukturelle Aussagekraft:** In Kombination mit der tabellarischen Übersicht und der globalen Statistik liefert die Visualisierung das notwendige Instrumentarium, um zwischen einem regulären Yule-Prozess und anomalen Akkumulationsmustern in empirischen Daten zu unterscheiden.

---

## Technische Implementierung & Systemvoraussetzungen

Die Anwendung wurde als modulares PHP-Skript implementiert und ist auf eine hohe Portabilität und einfache Ausführbarkeit optimiert.

### Voraussetzungen
*   **Webserver-Umgebung:** Ein lokaler Webserver (z. B. Apache, Nginx) mit installierter PHP-Umgebung.
*   **PHP-Version:** Kompatibel mit aktuellen PHP-Versionen (empfohlen ab PHP 7.4 oder höher).
*   **Konfiguration:** Da die Rechenlast bei sehr hohen Textzahlen quadratisch ansteigen kann ($O(N^2)$), ist das Skript auf eine Laufzeitbegrenzung von 120 Sekunden ausgelegt (`@set_time_limit(120);`). Sollten Berechnungen mit extrem hohen Textzahlen (z. B. > 250.000) durchgeführt werden, muss gegebenenfalls die serverseitige `max_execution_time` in der `php.ini` angepasst werden.

### Installation & Inbetriebnahme

1.  **Download:** Laden Sie das Repository als ZIP-Datei herunter oder klonen Sie es via Git:
    ```bash
    git clone [URL-DEINES-REPOS]
    ```
2.  **Verzeichnis:** Verschieben Sie die Dateien in das Verzeichnis Ihres Webservers (z. B. `/var/www/html/` oder den `htdocs`-Ordner Ihres lokalen Webservers).
3.  **Start:** Rufen Sie die Datei `yule2.php` über Ihren Webbrowser auf, z. B. über `http://localhost/yule2.php`.

#### Lokale Ausführung ohne Webserver (Alternative):
Falls PHP in Ihrer Konsole installiert ist, können Sie auch den integrierten PHP-Server nutzen: 
php -S localhost:8000

Anschließend ist das Tool unter http://localhost:8000/yule2.php oder auch http://127.0.0.1/yule2.php erreichbar.

### Archiv & Historische Dokumentation

Dieses Repository umfasst neben der modernisierten Implementierung auch den originalen Programmcode der ursprünglichen Dissertations-Version. Die Beibehaltung dieses Archiv-Codes dient primär der algorithmischen Validierung: Forscher haben dadurch die Möglichkeit, die methodische Kontinuität der Berechnungen über die Zeit hinweg nachzuvollziehen und sicherzustellen, dass die mathematische Logik der Simulation in der neuen Umgebung unverändert fortbesteht.

### Zitierweise

Um die methodische Nachvollziehbarkeit in wissenschaftlichen Publikationen zu gewährleisten, wird bei der Verwendung dieses Tools um eine entsprechende Zitation gebeten.

   * **DOI:** [Zenodo-DOI einfügen sobald verknüpft]
   * **Referenz:** Janatzek, Uwe. 2026. *Matthäus-Effekt Simulator 2.0*. https://github.com/Dr-U-Janatzek/Matthaeus-Effekt-Simulator-2.0.

### Lizenz

Dieses Projekt steht unter der MIT-Lizenz zur Verfügung. Dies erlaubt die freie Nutzung, Modifikation und Weitergabe des Codes, sofern der Urheberrechtsvermerk und dieser Lizenztext in allen Kopien oder wesentlichen Teilen der Software enthalten sind. Das Ziel dieser Lizenzwahl ist es, die wissenschaftliche Nachnutzbarkeit zu fördern und gleichzeitig die Integrität des ursprünglichen Modells zu wahren.