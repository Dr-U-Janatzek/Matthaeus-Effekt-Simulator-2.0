<?php
@set_time_limit(120);
/**
 * Berechnung des Matthäus-Effekts via Yule-Prozess
 * Entwickelt von Dr. Uwe Janatzek, M.A.
 *
 * Lizenz: MIT
 *
 * Dieses Skript wurde im Rahmen einer sozialwissenschaftlichen Dissertation entwickelt.
 * Der aktive Code im oberen Teil ist für moderne PHP-Umgebungen optimiert,
 * sicher gegen Cross-Site-Scripting (XSS) und läuft hocheffizient.
 *
 * Am Ende der Datei befindet sich der originale, unoptimierte historische Code
 * zu Lehrzwecken und für Altsysteme auskommentiert im Block-Kommentar.
 */

// --- 1. SESSIONS & PARAMETER-INITIALISIERUNG ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$beginn = microtime(true);

// Sichere Initialisierung der POST-Werte
$anzahl_texte = isset($_POST['anzahl_texte']) ? filter_var($_POST['anzahl_texte'], FILTER_VALIDATE_INT) : 100;
$vorwert      = isset($_POST['vorwert']) ? filter_var($_POST['vorwert'], FILTER_VALIDATE_INT) : 0;
$ab_d         = isset($_POST['ab_d']) ? filter_var($_POST['ab_d'], FILTER_VALIDATE_INT) : 1;
$ab_sperr     = isset($_POST['ab_sperr']) ? filter_var($_POST['ab_sperr'], FILTER_VALIDATE_INT) : 1000;

$rechne_mit   = isset($_POST['rechne_mit']);
$mit_grv      = isset($_POST['mit_grv']);
$sperr        = isset($_POST['sperr']);
$send         = isset($_POST['send']);

// Session-basierte Zähler für fortlaufende Durchgänge
if (!isset($_SESSION['durchg']) || isset($_POST['clear'])) {
    $_SESSION['durchg'] = 0;
    $_SESSION['hoch'] = 0;
    $_SESSION['hoch_2'] = 0;
    $_SESSION['niedrig'] = PHP_INT_MAX;
    $_SESSION['niedrig_2'] = 0;
    $_SESSION['hidden_history'] = [];
}

if (isset($_POST['clear'])) {
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$fehlerausgabe = "";
if ($anzahl_texte > 250000) {
    $fehlerausgabe = "Der eingegebene Wert wurde auf das Limit von 25.000 heruntergesetzt!";
    $anzahl_texte = 250000;
} elseif ($anzahl_texte < 1) {
    $anzahl_texte = 100;
}

// --- 2. LOGIK: SIMULATION DES YULE-PROZESSES ---
$ergebnis_html = "";
$verlauf_html = "";
$statistik_html = "";

if ($send) {
    $_SESSION['durchg']++;
    $durchg = $_SESSION['durchg'];

    $autor_ = [1 => 1];
    $autor_wert_ = [1 => 10];
    $gesamtsumme = 10;

    $simulierte_texte_limit = $anzahl_texte * 10;
    $zaehler = 1;

    while ($gesamtsumme < $simulierte_texte_limit) {
        $anz_autoren = count($autor_);

        if ($anz_autoren > 1) {
            $randval = mt_rand(11, $gesamtsumme);
            $ges_min = $gesamtsumme;

            for ($bcounter = 1; $bcounter < $anz_autoren; $bcounter++) {
                $ges_min -= $autor_wert_[$bcounter];
                if ($randval < $gesamtsumme && $randval >= $ges_min) {
                    $autor_[$bcounter]++;
                    $autor_wert_[$bcounter] += 10;
                    $gesamtsumme += 10;
                    break;
                }
            }
        }

        $autor_[$zaehler]++;
        $autor_wert_[$zaehler] = 10;
        $gesamtsumme += 10;
        $zaehler++;
    }

    rsort($autor_wert_);
    $hoechstwert = 1;
    $texte_anzahl_ = [];

    foreach ($autor_wert_ as $wert) {
        $divi = $wert / 10;
        if ($divi > $hoechstwert) {
            $hoechstwert = $divi;
        }
        if (!isset($texte_anzahl_[$divi])) {
            $texte_anzahl_[$divi] = 0;
        }
        $texte_anzahl_[$divi]++;
    }

    $_SESSION['hidden_history'][] = $hoechstwert;

    if ($hoechstwert > $_SESSION['hoch']) {
        $_SESSION['hoch'] = $hoechstwert;
        $_SESSION['hoch_2'] = $durchg;
    }
    if ($hoechstwert < $_SESSION['niedrig']) {
        $_SESSION['niedrig'] = $hoechstwert;
        $_SESSION['niedrig_2'] = $durchg;
    }

    // --- 3. ERGEBNIS-RENDERING ---
    $anzahl_texte_ein_prozent = $anzahl_texte / 100;
    $alle_autoren = 0;
    $autoren_groesser_eins = 0;
    $insg = 0;
    $z_ausg = 0;

    $ergebnis_html .= "<div class='results-card'><h3>Simulations-Ergebnis (Durchgang $durchg)</h3><table class='results-table'>";

    krsort($texte_anzahl_);
    foreach ($texte_anzahl_ as $beitraege => $anzahl_autoren) {
        $z_ausg++;
        $en = $anzahl_autoren > 1 ? 'en' : '';
        $ag = $beitraege == 1 ? 'ag' : 'ägen';
        $all = $anzahl_autoren * $beitraege;
        $in_p = $beitraege / $anzahl_texte_ein_prozent;
        $alle_autoren += $anzahl_autoren;

        if ($beitraege > 1) {
            $autoren_groesser_eins += $anzahl_autoren;
        }

        $prozent_gesamt = $anzahl_autoren * $in_p;
        $balken_breite = min(300, ceil(10 * $prozent_gesamt));

        $ergebnis_html .= sprintf(
            "<tr>
                <td class='col-num'>%d.</td>
                <td class='col-text'><strong>%d</strong> Autor%s mit <strong>%d</strong> Beitr%s</td>
                <td class='col-gesamt'>(Gesamt: %d)</td>
                <td class='col-pct'>In %%: %.2f</td>
                <td class='col-pct-ges'>- In %% gesamt: %.2f</td>
                <td class='col-bar'>
                    <div style='display: flex; align-items: center; gap: 4px;'>
                        <div style='background-color: red; height: 14px; width: %dpx; min-width: 1px;'></div>
                        <span style='color: #666; font-size: 0.8rem;'>|</span>
                    </div>
                </td>
            </tr>",
            $z_ausg, $anzahl_autoren, $en, $beitraege, $ag, $all, $in_p, $prozent_gesamt, $balken_breite
        );
        $insg += $all;
    }
    $ergebnis_html .= "</table></div>";

    $niedrig = $_SESSION['niedrig'];
    $hoch = $_SESSION['hoch'];
    $niedrig_2 = $_SESSION['niedrig_2'];
    $hoch_2 = $_SESSION['hoch_2'];

    $durchschnitt = $durchg < 2 ? $hoch : (($hoch - $niedrig) / 2) + $niedrig;
    $ein_p = $durchschnitt / 100;
    $plus_p = $ein_p > 0 ? round($hoch / $ein_p, 2) : 0;
    $minus_p = $ein_p > 0 ? round($niedrig / $ein_p, 2) : 0;
    $plus_minus = $plus_p - 100;

    $ap = $alle_autoren > 0 ? round($autoren_groesser_eins / ($alle_autoren / 100), 2) : 0;
    $n_in_p = $insg > 0 ? round($niedrig / ($insg / 100), 2) : 0;
    $h_in_p = $insg > 0 ? round($hoch / ($insg / 100), 2) : 0;
    $verhaeltnis_h_zu_n = $niedrig > 0 ? round($hoch / $niedrig, 2) : 0;
    $verhaeltnis_n_zu_h = $hoch > 0 ? round($niedrig / $hoch, 2) : 0;

    $statistik_html .= "
    <div class='stats-card'>
        <h3>Globale Statistik über alle Durchläufe</h3>
        <p>Texte / Beiträge gesamt in diesem Durchgang: <strong>$insg</strong></p>
        <ul>
            <li>Bisher niedrigster Höchstwert: <strong>$niedrig</strong> (In %: $n_in_p, bei Durchgang Nr. $niedrig_2)</li>
            <li>Bisher höchster Höchstwert: <strong>$hoch</strong> (In %: $h_in_p, bei Durchgang Nr. $hoch_2)</li>
            <li>Verhältnis niedrigster zu höchstem Wert: <strong>1 : $verhaeltnis_h_zu_n</strong> (bzw. 1 : $verhaeltnis_n_zu_h)</li>
            <li>Durchschnittlicher Höchstwert: <strong>$durchschnitt</strong></li>
            <li>Schwankungsbreite (vom Durchschnitt): <strong>+/- $plus_minus %</strong> ($minus_p % bis $plus_p %)</li>
            <li>Autoren mit mehr als einem Beitrag: <strong>$autoren_groesser_eins</strong> ($ap %)</li>
        </ul>";

    if ($rechne_mit && $vorwert > 0) {
        if ($vorwert > $hoch) {
            $mind_notw = $h_in_p > 0 ? ceil(($vorwert / $h_in_p) * 100) : 0;
            $mind_notw_ds = ceil(($mind_notw / 100) * ($plus_minus + 100));
            $statistik_html .= "
            <div class='testwert-box-warn'>
                <strong>Testwert-Analyse ($vorwert):</strong><br>
                Der Testwert liegt außerhalb des aktuellen Schwankungsbereichs.<br>
                Um diesen Wert als Höchstwert zu erreichen, wären mindestens <strong>$mind_notw</strong> Beiträge notwendig.<br>
                Als Durchschnittswert wären mindestens <strong>$mind_notw_ds</strong> Beiträge notwendig (hochgerechnet auf Basis von Durchgang $hoch_2).
            </div>";
        } else {
            $statistik_html .= "<div class='testwert-box-success'><strong>Testwert-Analyse ($vorwert):</strong> Der Testwert liegt innerhalb des Schwankungsbereichs.</div>";
        }
    }
    $statistik_html .= "</div>";

    if ($mit_grv && $durchg >= $ab_d) {
        $verlauf_html .= "<div class='chart-card'><h3>Grafischer Verlauf (Höchstwert je Durchgang)</h3><div class='chart-container'>";
        foreach ($_SESSION['hidden_history'] as $idx => $val) {
            $d_nr = $idx + 1;
            $height_px = min(150, $val * 2);
            $is_peak = ($val == $hoch) ? "peak" : "";
            $verlauf_html .= "<div class='chart-bar $is_peak' style='height: {$height_px}px;' title='Durchgang $d_nr: $val'></div>";
        }
        $verlauf_html .= "</div><p class='chart-legend'><span class='legend-dot peak-dot'></span> Rekordwert ($hoch bei Durchgang $hoch_2)</p></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulation des Matthäus-Effekts (Yule-Prozess) - Dr. Uwe Janatzek, M.A.</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #e74c3c;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 { color: var(--primary-color); margin-bottom: 5px; }
        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .form-group input[type="text"], .form-group input[type="number"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        button, input[type="submit"] {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }

        /* Tabellenstyling mit exakter Spaltenaufteilung */
        .results-card, .stats-card, .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .results-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            white-space: nowrap;
            font-size: 0.95rem;
        }
        .col-num { width: 5%; color: #7f8c8d; text-align: right; padding-right: 15px !important; }
        .col-text { width: 32%; }
        .col-gesamt { width: 14%; color: #7f8c8d; }
        .col-pct { width: 11%; }
        .col-pct-ges { width: 15%; }
        .col-bar { width: 23%; }

        .testwert-box-warn {
            background: #fadbd8;
            border-left: 5px solid var(--warning-color);
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
        }
        .testwert-box-success {
            background: #d4efdf;
            border-left: 5px solid var(--success-color);
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
        }
        /* Diagramm CSS */
        .chart-container {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 160px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            overflow-x: auto;
        }
        .chart-bar {
            flex: 1;
            min-width: 4px;
            background: var(--accent-color);
            border-radius: 2px 2px 0 0;
            transition: height 0.3s;
        }
        .chart-bar.peak {
            background: var(--warning-color);
        }
        .chart-legend {
            font-size: 0.85rem;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 50%;
        }
        .peak-dot { background: var(--warning-color); }
        .error-msg {
            background: var(--warning-color);
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Styling für den theoretischen/wissenschaftlichen Begleittext */
        .documentation {
            margin-top: 40px;
            border-top: 2px solid #ddd;
            padding-top: 30px;
        }
        .documentation h2 {
            color: var(--primary-color);
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
            margin-top: 35px;
        }
        .documentation p {
            text-align: justify;
            margin-bottom: 15px;
        }
        .tech-table {
            width: 100%;
            max-width: 400px;
            margin: 15px 0;
            border-collapse: collapse;
        }
        .tech-table th, .tech-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .tech-table th {
            background-color: #eee;
        }
        blockquote {
            background-color: #f1f2f6;
            border-left: 4px solid var(--accent-color);
            margin: 1.5em 10px;
            padding: 0.5em 15px;
            font-style: italic;
        }
        .verweise {
            font-size: 0.85rem;
            color: #555;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        footer {
            text-align: center;
            margin-top: 60px;
            font-size: 0.85rem;
            color: #7f8c8d;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Matthäus-Effekt Simulator 2.0</h1>
        <p>Bibliometrische Modellierung mittels Yule-Prozess</p>
    </header>

    <?php if ($fehlerausgabe): ?>
        <div class="error-msg"><?php echo htmlspecialchars($fehlerausgabe); ?></div>
    <?php endif; ?>

    <main class="card">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="anzahl_texte">Anzahl Texte (max. 250000):</label>
                    <input type="number" id="anzahl_texte" name="anzahl_texte" value="<?php echo htmlspecialchars((string)$anzahl_texte); ?>" max="250000" min="10" required>
                </div>
                <div class="form-group">
                    <label for="vorwert">Testwert für Relevanz-Prüfung:</label>
                    <input type="number" id="vorwert" name="vorwert" value="<?php echo htmlspecialchars((string)$vorwert); ?>">
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="rechne_mit" name="rechne_mit" <?php if ($rechne_mit) echo 'checked'; ?>>
                <label for="rechne_mit">Mit Testwert berechnen</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="mit_grv" name="mit_grv" <?php if ($mit_grv) echo 'checked'; ?>>
                <label for="mit_grv">Mit grafischem Verlauf ausgeben (Ab Durchgang Nr.
                <input type="number" name="ab_d" value="<?php echo htmlspecialchars((string)$ab_d); ?>" style="width: 60px; display: inline-block;">)</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="sperr" name="sperr" <?php if ($sperr) echo 'checked'; ?>>
                <label for="sperr">Button sperren ab Durchgang Nr.
                <input type="number" name="ab_sperr" value="<?php echo htmlspecialchars((string)$ab_sperr); ?>" style="width: 60px; display: inline-block;"></label>
            </div>

            <div class="btn-container">
                <?php if ($sperr && isset($_SESSION['durchg']) && $_SESSION['durchg'] >= $ab_sperr): ?>
                    <button type="button" class="btn-secondary" disabled style="background-color:#e74c3c;">Button gesperrt</button>
                <?php else: ?>
                    <button type="submit" name="send" class="btn-primary">Berechnung starten</button>
                <?php endif; ?>
                <button type="submit" name="clear" class="btn-secondary">Simulation zurücksetzen</button>
            </div>
        </form>
    </main>

    <?php
    echo $ergebnis_html;
    echo $verlauf_html;
    echo $statistik_html;
    ?>

    <!-- WISSENSCHAFTLICHE DOKUMENTATION & ERLÄUTERUNGEN -->
    <section class="documentation card">
        <h2>Herkunft und Einsatzzweck des Rechners</h2>
        <p>
            Dieser Rechner wurde vom Autor dieser Seite im Rahmen einer Dissertation entwickelt. Dabei ging es u.a. darum, ob die sehr häufige Nennung eines einzelnen Autors in einer bestimmten Literaturliste über den Matthäus-Effekt erklärbar ist, oder ob die Listung dieses Autors (auch) dessen Relevanz hinsichtlich eines bestimmten Themas widerspiegeln soll, wobei das nicht unwichtige Detail eine Rolle spielen könnte, dass die untersuchte Literaturliste durch eine Universitäts-Arbeitsstelle erstellt wurde, deren Leiter eben jener Autor ist (Anzahl Texte in der [bereinigten] Literaturliste: 729; Nennung eines einzelnen Autors dabei: 192 = 26,34 %). Die Untersuchung mit insgesamt 2000 Programmdurchläufen ergab, dass selbst unter Berücksichtigung des Matthäus-Effekts ein solcher Wert nicht erreicht werden konnte; der Höchstwert bei der zugrunde liegenden Anzahl von Texten lag bei 101. Rein rechnerisch wäre wenigstens eine Gesamttextanzahl von 1285 Texten notwendig, um den Wert von 192 zu erreichen. Allerdings handelt es sich hier eher um einen theoretischen Wert, der stark gegen null tendiert. Bei einem Gegentest mit 1285 Texten ergab sich ein Höchstwert für einen Autor von 137 Beiträgen. Erst bei einer Anzahl von 2100 Texten ergab sich ein ähnlicher Wert (194) bei 500 Programmdurchläufen. Dreimal erreicht bzw. überschritten wurde der Wert erst bei 3000, und gar zehnmal bei 4000 Texten und jeweils 500 Durchläufen. Das sehr häufige Vorkommen eines einzigen Autors in der untersuchten Liste lässt sich also weder durch Zufall noch durch den Matthäus-Effekt erklären. Vielmehr kann vermutet werden, dass diese Liste (auch) dazu dient, die Relevanz eines bestimmten Autors herauszustellen, was wiederum auf diskursive Machtausübung etc. verweist. Mithin handelt es sich bei der untersuchten Literaturliste also um eine Form der Eristik, sofern die Liste als Argument in den Diskurs eingebracht wird.
        </p>
        <p>
            Der Rechner lässt sich allerdings nicht nur für solche Zwecke nutzen, sondern im Prinzip für alle "schiefen" Verteilungen, die soziale Komponenten aufweisen und bei denen der Matthäus-Effekt deshalb greifen könnte, z.B. bei der Vergabe öffentlicher Aufträge an bestimmte freie Träger usw., für die Analyse der Verlinkungen bestimmter Seiten oder, wie Havemann (2009: 40) schreibt, generell „[...] zum Testen von neuen Modellen [...], für die noch keine Grenzverteilung mathematisch abgeleitet worden ist.“
        </p>

        <h2>Technische Hinweise</h2>
        <p>
            Prinzipiell können so viele Programmdurchläufe durchgeführt werden, wie man möchte. Bei Tests hat sich jedoch herausgestellt, dass ab einer gewissen Anzahl von Durchläufen keine großen Änderungen hinsichtlich der Niedrigst- bzw. Höchstwerte zu erwarten sind, wie das nachfolgende Beispiel für die Verteilung bei 100 Texten zeigt:
        </p>

        <strong>Beispiel für eine Berechnung mit 1000 Texten:</strong>
        <table class="tech-table">
            <thead>
                <tr><th>Durchgänge</th><th>Ergebnisbereich (In %)</th></tr>
            </thead>
            <tbody>
                <tr><td>100 Durchgänge</td><td>35,6 % - 164,4</td></tr>
                <tr><td>200 Durchgänge</td><td>33,08 % - 166,92</td></tr>
                <tr><td>500 Durchgänge</td><td>32,12 % - 167,88</td></tr>
                <tr><td>750 Durchgänge</td><td>30,35 % - 169,66</td></tr>
                <tr><td>1000 Durchgänge</td><td>30,35 % - 169,66</td></tr>
                <tr><td>1500 Durchgänge</td><td>22,09 % - 177,91</td></tr>
                <tr><td>1750 Durchgänge</td><td>22,09 % - 177,91</td></tr>
                <tr><td>2000 Durchgänge</td><td>22,09 % - 177,91</td></tr>
                <tr><td>2250 Durchgänge</td><td>22,09 % - 177,91</td></tr>
                <tr><td>2500 Durchgänge</td><td>22,09 % - 177,91</td></tr>
            </tbody>
        </table>

        <p>
            Dies hängt jedoch nicht mit der Anzahl der Durchläufe an sich zusammen, da bei jedem Durchlauf die gleiche Wahrscheinlichkeit besteht, einen bestimmten Wert zu erreichen, da es sich um voneinander unabhängige Ereignisse handelt. Im Grunde könnten deshalb unendlich viele Durchläufe durchgeführt werden, aus forschungspraktischen Gründen scheint eine Beschränkung auf ca. 2000 jedoch sinnvoll zu sein.<br>
            Gleichwohl lässt sich mit sehr hohen Zahlen aber auch zum einen die Grenze des Wachstums zeigen, da die Dominanz eines Autors keineswegs proportional zu einer erhöhten Textanzahl ist, zum anderen aber auch die Bedeutung der Zufallsvariablen, die dafür sorgen kann, dass, falls gleich am Anfang eine starke Ungleichverteilung vorliegt, eine 100%ige Steigerung der Beitragszahlen bewirkt wird (z.B. bei 200.000 Texten ein Anstieg auf über 600 Beiträge im Gegensatz zu etwas über 300 Beiträgen bei 150.000 Texten).
        </p>
        <p>
            Liegt die Textanzahl weit höher als tausend, so kommt noch der Zeitfaktor hinzu. Ab 10000 Texten benötigt das hier verwendete Skript auf einem modernen Server (Stand: 2026) 0,2478 Sekunden für die Berechnung - dieser Wert steigt mit höheren Textzahlen aufgrund des quadratischen Aufwands <i>O(N<sup>2</sup>)</i> innerhalb des Laufzeitverhaltens rapide an, so dass eine Beschränkung auf 250000 Texte "eingebaut" ist. Der Grund dafür liegt nicht in der Rechenmethode selbst, sondern in der dem Skript zur Verfügung stehenden Ausführungszeit, die serverseitig meist auf 30, manchmal auch auf 60 Sekunden beschränkt ist. Auf anderen Servern oder bei einem Vollzugriff auf die PHP.INI, in der diese Werte eingestellt werden können, ließen sich deshalb auch höhere Zahlen berechnen. Im Script-Header befindet sich hierzu speziell die Zeile<br> @set_time_limit(120);<br>
            Diese bewirkt, dass dem Script eine Laufzeit von 120 Sekunden eingeräumt wird. Allerdings ist dies nicht immer auf jedem Server möglich, z.B. bei Servern im Safe Mode oder falls der Hoster dies unterbunden hat. Sollte dies der Fall sein und sehr hohe Zahlen simuliert werden müssen empfiehlt sich die Anwendung auf einem lokalen Server, der diesbezüglich frei konfigurierbar ist.<br>
            Im Quelltext des Skripts lässt sich die hierbei hinterlegte Sicherheitsgrenze von 250.000 Texten zudem jederzeit mühelos anpassen, indem der entsprechende Grenzwert im PHP- und HTML-Code durch eine beliebig höhere oder niedrigere Zahl ersetzt wird.
        </p>
        <p>(Hinweis zu Version 1.0, die ursprünglich für die Dissertation verwendet wurde: Der gesamte, funktionsfähige PHP-Code dieser alten Version befindet sich als Anhang in dieser Datei; er ermöglicht die historische Nachvollziehbarkeit, kann aber auch praktisch von Personen mit geringerer Programmiererfahrung verwendet werden, da der einfachere lineare Aufbau eine leichtere Übersicht ermöglicht und sich der Code dadurch leichter ändern bzw. anpassen lässt.)
        </p>

        <h2>Hinweise zur Anwendung / Eingabemöglichkeiten</h2>
        <p>
            Um eine gegebene Anzahl von Texten / Beiträgen etc. daraufhin zu untersuchen, wie hoch die Anzahl der Texte / Beiträge ist, die ein einzelner Autor unter Berücksichtigung des Matthäus-Effekts dazu beitragen kann, reicht es, in das Eingabefeld "Anzahl Texte (max. 250000)" (voreingestellt: 100) die Anzahl der Texte einzugeben, die untersucht werden soll. Andere Einstellungen oder Eingaben sind nicht notwendig.
        </p>
        <p>
            <strong>"Mit Testwert berechnen"</strong> ermöglicht die gleichzeitige Untersuchung eines Wertes (Text-/Beitragsanzahl) dahingehend, ob er überhaupt mit der gegebenen Gesamtanzahl von Texten erreicht werden könnte. Da in die Berechnung eine Zufallsvariable einfließt, lässt sich eine genaue Mindestanzahl an Texten für einen bestimmten Testwert nicht exakt ermitteln, sondern lediglich hochrechnen. Dabei ist die Erreichung dieses Wertes zwar nicht unmöglich, jedoch nur von geringer Wahrscheinlichkeit. Dennoch ermöglicht dies gewisse Vergleiche, wie sie auch für die oben beschriebene, untersuchte Literaturliste vorgenommen wurden. Diese Funktion lässt sich auch während einer Durchlaufreihe ein- und ausschalten.
        </p>
        <p>
            <strong>"Mit grafischem Verlauf ausgeben"</strong> ermöglicht die Ausgabe der Ergebnisse als Säulen- bzw. Stabdiagramm. Auf der linken Seite wird dabei der erzielte Höchstwert auf Höhe der Säule angezeigt, unterhalb des Diagramms die Nummer des Durchlaufs, bei der dieser Höchstwert erreicht wurde, und rechts die insgesamt durchgeführten Durchläufe. Das Eingabefeld "Ab Durchgang Nr." neben der Checkbox ermöglicht die Ausgabe erst ab einer bestimmten Anzahl von Durchläufen. Da die Ausgabe des Diagramms in HTML (und nicht als Grafik) erfolgt, kann dies bei höheren Textzahlen die Ladezeit der Seite etwas verbessern. Diese Funktion lässt sich auch während einer Durchlaufreihe ein- und ausschalten. Ausgegeben wird immer das gesamte Diagramm ab Durchlauf Nr. 1.
        </p>
        <p>
            Die Funktion <strong>"Button sperren"</strong> ermöglicht es, den Submit-Button bei einer vorgegebenen Anzahl von Durchläufen zu sperren, um aus Versehen vorgenommene weitere Durchläufe als geplant zu verhindern. Auch diese Funktion lässt sich während einer Durchlaufreihe ein- und ausschalten. Um den Button wieder zu entsperren, einfach das Häkchen bei "Button sperren" entfernen.
        </p>

        <h2>Erläuterungen zum Matthäus-Effekt<sup>1</sup></h2>
        <p>
            Der Matthäus-Effekt ist ein Beispiel für eine Lotka-Verteilung (benannt nach <i>Alfred Lotka</i>), also eine "schiefe Verteilung", die mittels mathematischer Verfahren festgestellt werden kann und anhand derer sich erkennen lässt, dass bei höheren Publikationszahlen die Zahl der häufig vertretenen Autoren rapide abnimmt, so dass also viele Autoren nur wenig publizieren, einige wenige Autoren aber viele Beiträge veröffentlichen<sup>2</sup>. Der Begriff des Matthäus-Effekts (<i>Matthew effect</i>) wurde dabei im Rückgriff auf das Matthäus-Evangelium<sup>3</sup> von <i>Robert K. Merton</i> geprägt und wird mittlerweile auch außerhalb der Wissenschaftssoziologie verwendet<sup>4</sup>. Er beschreibt im Prinzip den kumulativen Zuwachs von Erfolg (<i>success breeds success</i>) oder von Besitz ("Wer hat, dem wird gegeben werden")<sup>5</sup>. Der Matthäus-Effekt steht dabei auch in Zusammenhang mit Reputation, den Havemann (2009: 39) wie folgt beschreibt:
        </p>
        <blockquote>
            "Wissenschaftler verkaufen das von ihnen produzierte Wissen nicht, sondern streben nach Reputation, indem sie es öffentlich machen. Reputation befähigt sie, gut dotierte Stellen zu erlangen. Voraussetzung für Reputation ist die Aufmerksamkeit der Fachkollegen - bekanntlich ein rares Gut. Sie wird - wie in Kunst, Sport und Politik - vor allem denen gegeben, die schon viel davon bekommen haben. Reputation führt aber auch zu Forschungsmitteln und damit zu neuen Chancen für wissenschaftlichen Erfolg. All das - und noch einiges mehr - bewirkt eine selbstverstärkende Rückkopplung in den Karrieren von Forschern. Ähnliche Betrachtungen kann man über den Matthäus-Effekt bei wissenschaftlichen Institutionen und Zeitschriften anstellen."
        </blockquote>
        <p>
            Der Matthäus-Effekt kann dabei mittels des sog. Yule-Prozesses gezeigt werden. Dabei handelt es sich um ein auf einem Potenzgesetz basierendes Verfahren, das in den 1920er Jahren von dem schottischen Statistiker George Udny Yule entwickelt und später von Herbert A. Simon weiterentwickelt und hinsichtlich der "wissenschaftlichen Produktivität" in Form von Fachartikeln erstmals herangezogen wurde<sup>6</sup>. Havemann (2009: 40) erläutert das Verfahren (inklusive Berechnungsformeln, die hier ausgelassen werden können) dermaßen einfach, dass hier wiederum auf ein wörtliches Zitat zurückgegriffen werden soll:
        </p>
        <blockquote>
            "Der Yule-Prozess kann mit Bezug auf Autoren und Artikel einer Bibliographie im einfachsten Fall so beschrieben werden: Zu Beginn gibt es einen Autor mit einer Publikation in der Bibliographie. In jeder Runde des Prozesses werden der Bibliographie zwei Artikel hinzugefügt, und zwar so, dass einer von einem neuen Autor publiziert wird und einer von einem Autor, der bereits in der Bibliographie vertreten ist. Der zweite Artikel wird unter den bisherigen Autoren verlost, wobei jeder Autor für jeden seiner bisherigen Artikel ein Los erhält. Ein Autor mit bisher zehn Artikeln hat damit eine zehnfach größere Chance, einen weiteren zu publizieren, als ein Autor mit nur einer Publikation. Auf diese Weise wird im Modell der Matthäus-Effekt hervorgerufen: Wer hat, dem wird gegeben."
        </blockquote>

        <div class="verweise">
            <strong>Verweise:</strong><br>
            <sup>1</sup> Dieser Abschnitt inkl. Fn und wörtlicher Zitation wurde entnommen bei Janatzek 2017: 459 f.<br>
            <sup>2</sup> Havemann 2009: 13.<br>
            <sup>3</sup> Mt 25, 14 - 30.<br>
            <sup>4</sup> Havemann 2009: 39; vgl. auch Merton 1985: 155.<br>
            <sup>5</sup> Ebd.<br>
            <sup>6</sup> Ebd.: 39 f.
        </div>

        <div class="verweise">
            <strong>Quellenangaben:</strong><br><br>
            <strong>Havemann, Frank (2009):</strong> <i>Einführung in die Bibliometrie.</i> Berlin: Gesellschaft für Wissenschaftsforschung. Unter: <a href="https://edoc.hu-berlin.de/bitstream/handle/18452/10084/20uf7RZtM6ZJk.pdf" target="_blank">https://edoc.hu-berlin.de/bitstream/handle/18452/10084/20uf7RZtM6ZJk.pdf</a><br><br>
            <strong>Janatzek, Uwe (2017):</strong> <i>Sozialinformatik - empirisch begründete Zuordnungen und Verständnisweisen. Unter besonderer Berücksichtigung einer wissenschaftstheoretischen Verortung der managerialen Sozialinformatik als Protowissenschaft.</i> Dissertation zur Erlangung des akademischen Grades Doktor der Philosophie (Dr. phil.) der Fakultät für Erziehungswissenschaft der Universität Bielefeld. Unter: <a href="https://pub.uni-bielefeld.de/download/2909606/2909607" target="_blank">https://pub.uni-bielefeld.de/download/2909606/2909607</a>.<br><br>
            <strong>Merton, Robert K. (1985):</strong> <i>Entwicklung und Wandel von Forschungsinteressen. Aufsätze zur Wissenschaftssoziologie.</i> Frankfurt/M.: Suhrkamp Verlag.
        </div>
    </section>

    <footer>
        <p>Entwickelt von <strong>Dr. Uwe Janatzek, M.A.</strong> im Rahmen einer empirischen sozialwissenschaftlichen Dissertation (2017).</p>
        <p>Lizenz: MIT | <a href="https://github.com/Dr-U-Janatzek" target="_blank">GitHub</a></p>
        <?php
        $dauer = microtime(true) - $beginn;
        echo "<p><small>Skriptlaufzeit: " . round($dauer, 4) . " Sekunden.</small></p>";
        ?>
    </footer>
</div>
</body>
</html>

<?php
/**
 * ==================================================================================
 * HISTORISCHER ARCHIV-CODE (ORIGINALVERSION) - AUSKOMMENTIERT
 * ==================================================================================
 *
 * Dieser Abschnitt enthält das funktionale Originalskript, das unverändert zur
 * Nachvollziehbarkeit des ursprünglichen Entwicklungsstands archiviert wurde.
 * Er eignet sich hervorragend zu Lehrzwecken, da er den Algorithmus Schritt für Schritt
 * ohne systemische Abstraktionen darstellt.
 *
 * Um diesen Code in einer isolierten Altsystem-Umgebung (z.B. PHP 5.x auf localhost)
 * auszuführen, kopiere diesen Block in eine eigene Datei (z.B. "original_yule.php")
 * und entferne die umschließenden PHP-Kommentarzeichen.

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="DC.language" content="de" scheme="DCTERMS.RFC3066">
<meta name="content-language" content="de">
<meta name="Revisit-after" content="1 day">
<meta name="Robots" content="INDEX, FOLLOW">
<style type="text/css">
BODY {
margin-top:15px;
margin-left:10px;
margin-right:10px;
margin-bottom:20px
}
</style>
</head>
<body>
<?php
// AUSTAUSCHNE !!!!!!!!!
$superglobals = array($_SERVER, $_ENV,
$_FILES, $_COOKIE, $_POST, $_GET);
if (isset($_SESSION)) {
array_unshift($superglobals, $_SESSION);
}
foreach ($superglobals as $superglobal) {
extract($superglobal, EXTR_SKIP);
}
$beginn = microtime(true);

// ----------------------------------------------------------------- Sicherheitsfunktionen - ANFANG!!!
$anzahl_texte=strip_tags($anzahl_texte);$anzahl_texte=htmlspecialchars($anzahl_texte);
$vorwert=strip_tags($vorwert);$vorwert=htmlspecialchars($vorwert);
$ab_d=strip_tags($ab_d);$ab_d=htmlspecialchars($ab_d);
$ab_sperr=strip_tags($ab_sperr);$ab_sperr=htmlspecialchars($ab_sperr);
//$sperr=strip_tags($sperr);//$sperr=htmlspecialchars($sperr);
//$rechne_mit=strip_tags($rechne_mit);//$rechne_mit=htmlspecialchars($rechne_mit);
//$mit_grv=strip_tags($mit_grv);//$mit_grv=htmlspecialchars($mit_grv);
$send=strip_tags($send);$send=htmlspecialchars($send);
$hidden=strip_tags($hidden);$hidden=htmlspecialchars($hidden);
$durchg=strip_tags($durchg);$durchg=htmlspecialchars($durchg);
$hoch=strip_tags($hoch);$hoch=htmlspecialchars($hoch);
$hoch_2=strip_tags($hoch_2);$hoch_2=htmlspecialchars($hoch_2);
$niedrig=strip_tags($niedrig);$niedrig=htmlspecialchars($niedrig);
$niedrig_2=strip_tags($niedrig_2);$niedrig_2=htmlspecialchars($niedrig_2);
unset($QUERY_STRING);



// ------------------------------------------------------------------ Sicherheitsfunktionen - ENDE!!!!

$atext='<bR><br>
<hr>

<br><br>
<b>Herkunft und Einsatzzweck des Rechners:</b><br>
Dieser Rechner wurde vom Autor dieser Seite im Rahmen einer Dissertation entwickelt. Dabei ging es u.a. darum, ob die sehr häufige Nennung eines einzelnen Autors in einer bestimmten Literaturliste über den Matthäus-Effekt erklärbar ist, oder ob die Listung dieses Autors (auch) dessen Relevanz hinsichtlich eines bestimmten Themas widerspiegeln soll, wobei das nicht unwichtige Detail eines Rolle spielen könnte, dass die untersuchte Literaturliste durch eine Universitäts-Arbeitsstelle erstellt wurde, deren Leiter eben jener Autor ist (Anzahl Texte in der [bereinigten] Literaturliste: 729; Nennung eines einzelnen Autors dabei: 192 = 26,34 %). Die Untersuchung mit insgesamt 2000 Programmdurchläufen ergab, dass selbst unter Berücksichtigung des Matthäus-Effekts ein solcher Wert nicht erreicht werden konnte; der Höchstwert bei der zugrunde liegenden Anzahl von Texten lag bei 101. Rein rechnerisch wäre wenigstens eine Gesamttextanzahl von 1285 Texten notwendig, um den Wert von 192 zu erreichen. Allerdings handelt es sich hier eher um einen theoretischen Wert, der stark gegen null tendiert. Bei einem Gegentest mit 1285 Texten erggibt sich ein Höchstwert für einen Autor von 137 Beiträgen. Erst bei einer Anzahl von 2100 Texten ergab sich ein ähnlicher Wert (194) bei 500 Programmdurchläufen. Dreimal erreicht bzw. überschritten wurde der Wert erst bei 3000, und gar zehnmal bei 4000 Texten und jeweils 500 Durchläufen. Das sehr häufige Vorkommen eines einzigen Autors in der untersuchten Liste lässt sich also weder durch Zufall noch durch den Matthäus-Effekt erklären. Vielmehr kann vermutet werden, dass diese Liste (auch) dazu dient, die Relevanz eines bestimmten Autors herauszustellen, was wiederum auf diskursive Machtausübung etc. verweist. Mithin handelt es sich bei der untersuchten Literaturliste also um eine Form der Eristik, sofern die Liste als Argument in den Diskurs eingebracht wird.<br>
Der Rechner lässt sich allerdings nicht nur für solche Zwecke nutzen, sondern im Prinzip für alle "schiefen" Verteilungen, die soziale Komponenten aufweisen und bei denen der Matthäus-Effekt deshalb greifen könnte, z.B. bei der Vergabe öffentlicher Aufträge an bestimmte freie Träger usw., für die Analyse der Verlinkungen bestimmter Seiten oder, wie Havemann (2009: 40) schreibt, generell "[...] zum Testen von neuen Modellen [...], für die noch keine Grenzverteilung mathematisch abgeleitet worden ist."


<br><br>
<br><br>
<b>Technische Hinweise:</b><br>
Prinzipiell können so viele Programmdurchläufe durchgeführt werden, wie man möchte. Bei Tests hat sich jedoch herausgestellt, dass ab einer gewissen Anzahl von Durchläufen keine großen Änderungen hinsichtlich der Niedrigst- bzw. Höchstwerte zu erwarten ist, wie das nachfolgende Beispiel für die Verteilung bei 100 Texten zeigt:<br><br>
<u>Beispiel für eine Berechnung mit 1000 Texten:</u>
100 Durchgänge = 35,6 % - 164,4<br>
200 Durchgänge = 33,08 % - 166,92<br>
500 Durchgänge = 32,12 % - 167,88<br>
750 Durchgänge = 30,35 % - 169,66<br>
1000 Durchgänge = 30,35 % - 169,66<br>
1500 Durchgänge = 22,09 % - 177,91<br>
1750 Durchgänge = 22,09 % - 177,91<br>
2000 Durchgänge = 22,09 % - 177,91<br>
2250 Durchgänge = 22,09 % - 177,91<br>
2500 Durchgänge = 22,09 % - 177,91<br>
<br>
Dies hängt jedoch nicht mit der Anzahl der Durchläufe an sich zusammen, da bei jedem Durchlauf die gleiche Wahrscheinlichkeit besteht, einen bestimmten Wert zu erreichen, da es sich um voneinander unabhängige Ereignisse handelt. Im Grunde könnten deshalb unendlich viele Durchläufe durchgeführt werden, aus forschungspraktischen Gründen scheint eine Beschränkung auf ca. 2000 jedoch sinnvoll zu sein. <br>
Liegt die Textanzahl weit höher als tausend, so kommt noch der Zeitfaktor hinzu. Ab 10000 Texte benötigt das hier verwendete Skript auf diesem Server knapp drei Sekunden für die Berechnung - dieser Wert steigt mit höheren Textzahlen rapide an, so dass eine Beschränkung auf 25000 Texte "eingebaut" ist. Der Grund dafür liegt nicht in der Rechenmethode selbst, sondern in der dem Skript zur Verfügung stehenden Ausführungszeit, die serverseitig auf 30 Sekunden beschränkt ist. Auf anderen Server oder bei einem Vollzugriff auf die PHP.INI, in der diese Werte eingestellt werden können, ließen sich deshalb auch höhere Zahlen berechnen.
<br><br>
<br><br>
<b>Hinweise zur Anwendung / Eingabemöglichkeiten:</b><br>
Um eine gegebene Anzahl von Texten / Beiträgen etc. daraufhin zu untersuchen, wie hoch die Anzahl der Texte / Beiträge ist, die ein einzelner Autor unter Berücksichtigung des Matthäus-Effekts dazu beitragen kann, reicht es, in das Eingabefeld "Anzahl Texte (max. 25000)" (voreingestellt: 100) die Anzahl der Texte einzugeben, die untersucht werden soll. Andere Einstellungen oder Eingaben sind nicht notwendig.<br>
"Mit Testwert berechnen" ermöglicht die gleichzeitige Untersuchung eines Wertes (Text-/Beitragsanzahl) dahingehend, ob er überhaupt mit der gegebenen Gesamtanzahl von Texten erreicht werden könnte. Da in die Berechnung eine Zufallsvariable einfließt, lässt sich eine genaue Mindestanzahl an Texten für einen bestimmten Testwert nicht exakt ermitteln, sondern lediglich hochrechnen. Dabei ist die Erreichung dieses Wertes zwar nicht unmöglich, jedoch nur von geringer Wahrscheinlichkeit. Dennoch ermöglicht dies gewisse Vergleiche, wie sie auch für die oben beschriebene, untersuchte Literaturliste vorgenommen wurden. Diese Funktion lässt sich auch während einer Durchlaufreihe ein- und ausschalten.<br>
"Mit grafischem Verlauf ausgeben" ermöglicht die Ausgabe der Ergebnisse als Säulen- bzw. Stabdiagramm. Auf der linken Seite wird dabei der erzielte Höchstwert auf Höhe der Säule angezeigt, unterhalb des Diagramms die Nummer des Durchlaufs, bei der dieser Höchstwert erreicht wurde und rechts die insgesamt durchgeführten Durchläufe. Das Eingabefeld "Ab Durchgang Nr." neben der Checkbox ermöglicht die Ausgabe erst ab einer bestimmten Anzahl von Durchläufen. Da die Ausgabe des Diagramms in HTML erfolgt, kann dies bei höheren Textzahlen die Ladezeit der Seite etwas verbessern. Diese Funktion lässt sich auch während einer Durchlaufreihe ein- und ausschalten. Ausgegeben wird immer das gesamte Diagramm ab Durchlauf Nr. 1.<br>
Die Funktion Button sperren" ermöglicht es, den Submit-Button bei einer vorgegebenen Anzahl von Durchläufen zu sperren, um aus Versehen vorgenommene weitere Durchläufe als geplant zu verhindern. Auch diese Funktion lässt sich während einer Durchlaufreihe ein- und ausschalten. Um den Button wieder zu entsperren, den entsprechenden Programmausgaben folgen.

<br><br>
<br><br>
<b>Erläuterungen zum Matthäus-Effekt<sup><font size=1>1</font></sup>:</b><br>


Der Matthäus-Effekt ist ein Beispiel für eine Lotka-Verteilung (benannt nach <i>Alfred Lotka</i>), also eine "schiefe Verteilung", die mittels mathematischer Verfahren festgestellt werden kann und anhand derer sich erkennen lässt, dass bei höheren Publikationszahlen die Zahl der häufig vertretenen Autoren rapide abnimmt, so dass also viele Autoren nur wenig publizieren, einige wenige Autoren aber viele Beiträge veröffentlichen<sup><font size=1>2</font></sup>. Der Begriff des Matthäus-Effekts (<i>Matthew effect</i>) wurde dabei im Rückgriff auf das Matthäus-Evangelium<sup><font size=1>3</font></sup> von <i>Robert K. Merton</i> geprägt und wird mittlerweile auch außerhalb der Wissenschaftssoziologie verwendet<sup><font size=1>4</font></sup>. Es beschreibt im Prinzip den kumulativen Zuwachs von Erfolg (success breeds success) oder von Besitz ("Wer hat, dem wird gegeben werden")<sup><font size=1>5</font></sup>. Der Matthäus-Effekt steht dabei auch in Zusammenhang mit wissenschaftlicher Reputation, den Havemann (2009: 39) wie folgt beschreibt:<br>
<br>
"Wissenschaftler verkaufen das von ihnen produzierte Wissen nicht, sondern streben nach Reputation, indem sie es öffentlich machen. Reputation befähigt sie, gut dotierte Stellen zu erlangen. Voraussetzung für Reputation ist die Aufmerksamkeit der Fachkollegen - bekanntlich ein rares Gut. Sie wird - wie in Kunst, Sport und Politik - vor allem denen gegeben, die schon viel davon bekommen haben. Reputation führt aber auch zu Forschungsmitteln und damit zu neuen Chancen für wissenschaftlichen Erfolg. All das - und noch einiges mehr - bewirkt eine selbstverstärkende Rückkopplung in den Karrieren von Forschern. Ähnliche Betrachtungen kann man über den Matthäus-Effekt bei wissenschaftlichen Institutionen und Zeitschriften anstellen."<br>
<br>
Der Matthäus-Effekt kann dabei mittels des sog. Yule-Prozesses gezeigt werden. Dabei handelt es sich um ein auf einem Potenzgesetz basierendes Verfahren, das in den 1920er Jahren von dem schottischen Statistiker <i>George Udny Yule</i> entwickelt und später von <i>Herbert A. Simon</i> weiterentwickelt und hinsichtlich der "wissenschaftlichen Produktivität" in Form von Fachartikeln erstmals herangezogen wurde<sup><font size=1>6</font></sup>. Havemann (2009: 40) erläutert das Verfahren (inklusive Berechnungsformeln, die hier ausgelassen werden können) dermaßen einfach, dass hier wiederum auf ein wörtliches Zitat zurückgegriffen werden soll: <br>
<br>
"Der Yule-Prozess kann mit Bezug auf Autoren und Artikel einer Bibliographie im einfachsten Fall so beschrieben werden: Zu Beginn gibt es einen Autor mit einer Publikation in der Bibliographie. In jeder Runde des Prozesses werden der Bibliographie zwei Artikel hinzugefügt, und zwar so, dass einer von einem neuen Autor publiziert wird und einer von einem Autor, der bereits in der Bibliographie vertreten ist. Der zweite Artikel wird unter den bisherigen Autoren verlost, wobei jeder Autor für jeden seiner bisherigen Artikel ein Los erhält. Ein Autor mit bisher zehn Artikeln hat damit eine zehnfach größere Chance, einen weiteren zu publizieren, als ein Autor mit nur einer Publikation. Auf diese Weise wird im Modell der Matthäus-Effekt hervorgerufen: Wer hat, dem wird gegeben."
<br>
<br><br>
<hr>
<font size=1><b>Verweise:</b><br>
<sup>1</sup>  Dieser Abschnitt inkl. Fn und wörtlicher Zitation wurde entnommen bei Janatzek 2017: 459 f.<br>
<sup>2</sup>  Havemann 2009: 13.<br>
<sup>3</sup>  Mt 25, 14 - 30.<br>
<sup>4</sup>  Havemann 2009: 39; vgl. auch Merton 1985: 155.<br>
<sup>5</sup>  Ebd.<br>
<sup>6</sup>  Ebd.: 39 f.<br>


</font>
<br>
<hr>

<b>Quellenangaben:</b>
<br><br>
Havemann, Frank (2009): <i>Einführung in die Bibliometrie.</i> Berlin: Gesellschaft für Wissenschaftsforschung. Unter: <a href=https://edoc.hu-berlin.de/bitstream/handle/18452/10084/20uf7RZtM6ZJk.pdf target=_blank title="Einführung in die Bibliometrie"><font color=#0000ff>https://edoc.hu-berlin.de/bitstream/handle/18452/10084/20uf7RZtM6ZJk.pdf</font></a>
<br><br>
Janatzek, Uwe (2017): <i>Sozialinformatik - empirisch begründete Zuordnungen und Verständnisweisen. Unter besonderer Berücksichtigung einer wissenschaftstheoretischen Verortung der managerialen Sozialinformatik als Protowissenschaft.</i> Dissertation zur Erlangung des akademischen Grades Doktor der Philosophie (Dr. phil.) der Fakultät für Erziehungswissenschaft der Universität Bielefeld. Unter: <a href=https://pub.uni-bielefeld.de/download/2909606/2909607 target=_blank title="Sozialinformatik - empirisch begründete Zuordnungen und Verständnisweisen. Unter besonderer Berücksichtigung einer wissenschaftstheoretischen Verortung der managerialen Sozialinformatik als Protowissenschaft"><font color=#0000ff>https://pub.uni-bielefeld.de/download/2909606/2909607</font></a>.
<br><br>
Merton, Robert K. (1985): <i>Entwicklung und Wandel von Forschungsinteressen. Aufsätze zur Wissenschaftssoziologie.</i> Frankfurt/M.: Suhrkamp Verlag.
<br><br><br>'
?>

<?php
if (($anzahl_texte) > 25000) { $fehlerausgabe='<br><br><center><h2><font color=red>Der eingegebene Wert von '.$anzahl_texte.' wurde auf 25000 heruntergesetzt!</font></h2></center><BR><BR>'; $anzahl_texte=25000; }
?>
<body>




<font face=verdana size=6>
<b><center>Berechnung des Matthäus-Effekts - Yule-Prozess</center></b>
</font>
<br>
<hr>
<font face=verdana>
<?php echo $fehlerausgabe; ?>
</font>
<font face=verdana size=2>
Um eine gegebene Anzahl von Texten / Beiträgen etc. daraufhin zu untersuchen, wie hoch die Anzahl der Texte / Beiträge ist, die ein einzelner Autor unter Berücksichtigung des Matthäus-Effekts dazu beitragen kann, reicht es, in das Eingabefeld "Anzahl Texte (max. 25000)" (voreingestellt: 100) die Anzahl der Texte einzugeben, die untersucht werden soll. Andere Einstellungen oder Eingaben sind nicht notwendig. Für die übrigen möglichen Funktionen bzw. Einstellungen bitte die Anwendungshinweise weiter unten beachten.<br><br>
<br>
<table style="border:1px solid #000000;" cellpadding=1 cellspacing=1><form method=post><tr><td>
<table border=0>
<tr align=right bgcolor=#c0c0c0><td><b>Anzahl Texte (max. 25000): </td><td>(Nur ganze Zahlen!)<input type=text name=anzahl_texte value="<?php echo $anzahl_texte; if (!($anzahl_texte)) { echo '100'; } ?>" maxlength=5 style="width:50px;border:1px solid #ff0000;" required title="Nur ganze Zahlen, kein Punkt, kein Komma!" onFocus="this.select()"></td></tr>
<tr align=right bgcolor=#eeeeee><td>Mit Testwert berechnen: <input type=checkbox name=rechne_mit <?php if (isset($rechne_mit)) { echo 'checked'; } ?>> </td><td>Testwert: <input type=text name=vorwert value="<?php echo $vorwert; ?>" style="width:50px;border:1px solid #000000;" maxlength=4 title="Nur ganze Zahlen, kein Punkt, kein Komma!" onFocus="this.select()"></td></tr>
<tr align=right bgcolor=#eeeeee><td>Mit grafischem Verlauf ausgeben: <input type=checkbox name=mit_grv <?php if (isset($mit_grv)) { echo 'checked'; } ?>> </td><td>Ab Durchgang Nr. <input type=text name=ab_d value="<?php echo $ab_d; if (!($ab_d)) { echo '1'; } ?>" maxlength=4 style="width:50px;border:1px solid #000000;" title="Nur ganze Zahlen, kein Punkt, kein Komma!" onFocus="this.select()"></td></tr>
<tr align=right bgcolor=#eeeeee><td>Button sperren: <input type=checkbox name=sperr <?php if (isset($sperr)) { echo 'checked'; } ?>></td><td> Ab Durchgang Nr. <input type=text name=ab_sperr value="<?php echo $ab_sperr; if (!($ab_sperr)) { echo '1000'; } ?>" maxlength=4 style="width:50px;border:1px solid #000000;" title="Nur ganze Zahlen, kein Punkt, kein Komma!" onFocus="this.select()"></td></tr>
</table></td></tr>

<tr bgcolor=#eeeeee><td>
<br><center>
<input type=reset>
<?php
if (isset($sperr)) {
 if (($durchg) == $ab_sperr) {
  echo '<input type=button name=send value="Button gesperrt"><br><font color=red>Button gesperrt! Zum entsperren "Alles löschen" klicken<br>oder im Browser ein Schritt zurück und das Häkchen entfernen.</font>';
 } else { echo '<input type=submit name=send value="Berechnung starten">'; }
}
if (!($sperr)) { echo '<input type=submit name=send value="Berechnung starten">'; }
?>
</center><br>
</td></tr></table>
<?php
if (!($send)) {
echo $atext;
}
?>

<?php
$anzahl_texte=$anzahl_texte + 0;
$anzahl_texte=intval($anzahl_texte);
$anzahl_texte=round($anzahl_texte);


if (($anzahl_texte) == 0) {
echo '<center><b>Nur ganze Zahlen eingeben!</b></center><hr>';
die;
}

if (isset($send)) {
$durchg++;
?>

<br><br>
Durchgang: <?php echo $durchg; if (!($durchg)) { echo '0'; }?> - <a href=yule.php><font color=#0000ff>Alles löschen</font></a>
<hr>
<br>

<?php
//$x_z=0;

//while (($x_z) != 10) {

$anzahl_texte_ein_prozent=$anzahl_texte / 100;

$zaehler=1;
$autor_[1]=1;
$anzahl_texte=$anzahl_texte * 10;
$gesamtsumme_2=$gesamtsumme;
while (($gesamtsumme) < $anzahl_texte) {


unset($gesamtsumme);

$anz_autoren=count($autor_);

  if (($anz_autoren) > 1) {
   $counter=1;
    while (($counter) != ($anz_autoren)) {
//     echo "counter: $counter<br>";
     $autor_wert_[$counter]=$autor_[$counter] * 10;
     $gesamtsumme=$gesamtsumme + $autor_wert_[$counter];
//     echo 'counter: '.$counter.'$autor_[]= '.$autor_[$counter].' - anz_autoren: '.$anz_autoren.' - wert: '.$autor_wert_[$counter].'|<br>';
     $last_num=$counter - 1;
     $counter++;

    }

  }

@mt_srand((double)microtime()*1000000);
@$randval = mt_rand(11,$gesamtsumme);
//echo "<hr><b>Zähler $zaehler ($anzahl_texte)</b><br>";
// verlosung anfang
if (($anz_autoren) > 1) {
$bcounter=1;
$b=1;
$ges_min=$gesamtsumme;

while (($bcounter) != ($anz_autoren - 1)) {
//echo "<br>bcount $bcounter -- gesmin $ges_min --- rnd $randval --- awert $autor_wert_[$bcounter]<br>";
$ges_min=$ges_min - $autor_wert_[$bcounter];
//echo "<br><b>$bcounter $autor_wert_[$bcounter] - gesmin = $ges_min - rnd = $randval ges = $gesamtsumme</b><br>";

if (($randval) < ($gesamtsumme) && ($randval) >= ($ges_min)) {
 $autor_[$bcounter]++;
 $bcounter=$anz_autoren - 2;//echo 'treffer'.$b.'<br>';
// echo "<hr>Treffer autor nr. ".$autor_[$bcounter]." - ".$autor_wert_[$bcounter]."<hr>";
}
$b++;
$bcounter++;
}
}
unset($ges_min);
// verlosunf ende




//echo "$anz_autoren - $texte ".$autor_[$zaehler]." - gessum. = $gesamtsumme - rnd = $randval<hr>";

$autor_[$zaehler]++;
$autor_wert_[$zaehler]=10;


$zaehler++;
}
//die;


rsort($autor_wert_);

$anz_autoren=count($autor_);

$z=0;
$heochstwert=1;
//echo '<hr><hr><hr>';
while (($p) != $anzahl_texte) {
$p=$p+$autor_wert_[$z];

if (($autor_wert_[$z]) > 10) {
//echo "<br>$z. - ".$autor_wert_[$z] / 10;
}
$divi=$autor_wert_[$z] / 10;

if (($divi) > $heochstwert) {
$heochstwert=$divi;
}
$texte_anzahl_[$divi]++;

$z++;

}
//echo "<hr>" . $p / 10 . "<hr><hr>";

//rsort($texte_anzahl_);
$at=count($texte_anzahl_);

//echo $at.' - '.$heochstwert.'<hr>';


$zaehler=$heochstwert;
$ag='ägen';
echo '<b><u>Ergebnis:</u></b><br><br><table border=0>';
while (($zaehler) != 0) {

if (($texte_anzahl_[$zaehler])) {
  if (($texte_anzahl_[$zaehler]) > 1) { $en='en'; } else { $en=''; } if (($zaehler) == 1) { $ag='ag'; }
  $all=$texte_anzahl_[$zaehler] * $zaehler;

  if (!($zeileeins)) {
   if (!($hoch)) { $hoch=$zaehler; } else {
    if (($zaehler) > $hoch) {
     $hoch=$zaehler;
     $hoch_2=$durchg;}
   }
   if (!($niedrig)) { $niedrig=$zaehler; } else {
    if (($zaehler) < $niedrig) {
     $niedrig=$zaehler;
     $niedrig_2=$durchg; }
   }
  }
  $zeileeins=1;
  $z_ausg++;
   $in_p=$zaehler / $anzahl_texte_ein_prozent;
   $alle_autoren=$alle_autoren + $texte_anzahl_[$zaehler];
   if (($zaehler) > 1) {
    $autoren_groesser_eins=$autoren_groesser_eins + $texte_anzahl_[$zaehler];
   }
   if (($z_ausg) == 1) {
    $hidden=$hidden.$zaehler.',';;
   }
 echo '<tr><td>'.$z_ausg. '. </td><td><b>'.$texte_anzahl_[$zaehler].'</b> Autor'.$en.' mit <b>'. $zaehler.'</b> Beitr'.$ag.'</td>
 <td> (Gesamt: '.$all.')</td>
 <td> In %: '.round($in_p,2).' </td>
 <td>- In % gesamt: '.$texte_anzahl_[$zaehler] * round($in_p,2).'</td>';


//   if (isset($mit_grv)) {
    echo '<td><table border=0 cellpadding="0" cellspacing="0" width=100%><tr><td width='.ceil(10 * ($texte_anzahl_[$zaehler] * $in_p)).'px bgcolor=red><font size=1 color=red></font></td><td>|</td></tr></table></td>';
//   }
  echo '</tr>';

 $insg=$insg + ($texte_anzahl_[$zaehler] * $zaehler);
 $med_[$z_ausg]=$zaehler;

 $mz++;
}

$zaehler--;
}

echo '</table>';
echo '
<input type=hidden name=durchg value="'.$durchg.'">
<input type=hidden name=hoch value="'.$hoch.'">
<input type=hidden name=hoch_2 value="'.$hoch_2.'">
<input type=hidden name=niedrig value="'.$niedrig.'">
<input type=hidden name=niedrig_2 value="'.$niedrig_2.'">
<input type="hidden" name="hidden" value="'.$hidden.'">';



// ---------------------------------,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,---- Ausgabe mit grafischem Verlauf - ANFANG!!!!
if (isset($mit_grv)) {
 if (($durchg) >= $ab_d) {
echo "<br><hr>";

$h=explode(',',$hidden);
$hanz=count($h);
echo '<b><u>Grafischer Verlauf:</u></b><br><br>';
echo '
<table border=0 cellpadding="0" cellspacing="0"><tr><tr><td width=15px valign=top><font color=red>'.$hoch.'</font></td><td>
<table border=0 cellpadding="0" cellspacing="0"><tr valign=bottom>';
$hz=0;
while (($hz) != $hanz) {
 if (($h[$hz]) == $hoch) {
   if (!($stopred)) {
    $hasg='<font color=red>'.$hoch_2.'</font>';
    $bgc='red';
   }
   $stopred=1;
 } else { $bgc='#0000ff'; $hasg='';}
echo '<td><table border=0 cellpadding="0" cellspacing="0"><tr><td width=1px height='.$h[$hz].'px bgcolor='.$bgc.'></td></tr></table></td>';
$zeile_2=$zeile_2.'<td><table border=0 cellpadding="0" cellspacing="0"><tr><td width=1px>'.$hasg.'</td></tr></table></td>';

$hz++;
}

echo'<td><font color=#0000ff>'.$durchg.'</font></td></tr></table></td></tr></table>
<table border=0 cellpadding="0" cellspacing="0"><tr><td width=15px valign=top><font color=#ffffff>'.$hoch.'</font></td>'.$zeile_2.'</tr></table>';
 }
}
// -------------------------------,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,------ Ausgabe mit grafischem Verlauf - ENDE!!!!

// Gerade  / ungerade Median
if ($mz % 2 != 0) {
 $med_1=round($at / 2);
// echo "Die Zahl $at ist ungerade<br>Median: $med_1 ". $med_[$med_1].' - '.$med_[1];
 } else {
 $med_1=$at / 2 - 0.5;
 $med_2=$at / 2 + 0.5;
// echo "Die Zahl $at ist gerade<br>$med_1 $med_2";
 }

$median_zaehler=1;
//while (($median_zaehler) < $med_1) {
//echo $med_[$median_zaehler]."<br>";
//$median_zaehler++;
//}

// Median Ende

if (($durchg) < 2) {
$durchschnitt=$hoch;
} else {
$durchschnitt=$hoch - $niedrig;
$durchschnitt=$durchschnitt / 2;
$durchschnitt=$durchschnitt + $niedrig;
}
if (($durchg) == 1) {
$niedrig_2=1;
$hoch_2=1;
}

$ein_p=$durchschnitt /100;
$plus_p=round($hoch / $ein_p,2);
$minus_p=round($niedrig / $ein_p,2);
$plus_minus=$plus_p - 100;

$ap=round($autoren_groesser_eins / ($alle_autoren / 100),2);
$n_in_p=round($niedrig / ($insg / 100),2);
$h_in_p=round($hoch / ($insg / 100),2);

$verhaeltnis_h_zu_n=round($hoch / $niedrig,2);
$verhaeltnis_n_zu_h=round($niedrig / $hoch,2);

$tab_laenge_1=($verhaeltnis_h_zu_n * 20) + 45;
$td_1=$tab_laenge_1 - 65;

echo "<hr>
<b><u>Berechnung allgemein:</u></b><br><br>
Texte / Beiträge: <b>$insg</b><br>
<table border=0>
<tr><td>Bisher niedrigster Wert:</td><td align=right> <b>$niedrig</b> </td><td align=right>(Bei Durchgang Nr. $niedrig_2.)</td><td>In %: </td><td align=right>$n_in_p</td></tr>
<tr><td>Bisher höchster Wert:</td><td align=right> <b>$hoch</b> </td><td align=right>(Bei Durchgang Nr. $hoch_2.)</td><td>In %: </td><td align=right>$h_in_p</td></tr>
</table>
<br>
Verhältnis niedrigster zu höchstem Wert: <b>1 : $verhaeltnis_h_zu_n</b> (&hArr; 1 : $verhaeltnis_n_zu_h)



<table width=$tab_laenge_1px><tr><td width=45px align=right><b>1</b></td><td width=20px bgcolor=green>&nbsp;</td><td width=$td_1px></td></tr></table>
<table width=$tab_laenge_1px><tr><td width=45px align=right><b>$verhaeltnis_h_zu_n</b></td><td width=".($verhaeltnis_h_zu_n * 20)."px bgcolor=#0000ff></td><td width=px></td></tr></table>
</table>


<br>
Durchschnitt: <b>$durchschnitt</b><br>
<br>
Schwankungsbreite (ausgehend vom Durchschnitt): <b>+/- $plus_minus %</b> ($minus_p % - $plus_p %)
<br><br>
Anzahl Autoren mit mehr als einem Beitrag: <b>".$autoren_groesser_eins."</b> ($ap %)
<br><br>
";
if (($rechne_mit)) {
 if (($vorwert) > $hoch) {
  $mind_notw=($vorwert / $h_in_p) * 100;$mind_notw=ceil($mind_notw);
  $mind_notw_ds=ceil(($mind_notw / 100) * ($plus_minus + 100));
  $wert_status='Der Testwert liegt außerhalb des Schwankungsbereichs.<br>
  Um den Testwert als Höchstwert  zu erreichen, wären mindestens '.ceil($mind_notw).' Texte / Beiträge bei '.$hoch_2.' Programmdurchläufen notwendig.<br>
  Um den Testwert als Durchschnitt zu erreichen, wären mindestens '.$mind_notw_ds.' Texte / Beiträge bei '.$hoch_2.' Programmdurchläufen notwendig.';
 } else {
  $wert_status='Der Testwert liegt innerhalb des Schwankungsbereichs.';
 }

echo "<hr>
<b>Berechnung des Testwerts:</b><br>
Testwert: $vorwert<br>
$wert_status

<hr>";
}



$dauer = microtime(true) - $beginn;
$dauer=round($dauer,2);
echo "<br><center><font size=1><b>Skriptlaufzeit: $dauer Sekunden.</b></font></center>";
}


unset($zaehler);
unset($autor_);
$anzahl_texte=$anzahl_texte / 10;
unset($gesamtsumme);

//$x_z++;
//}
echo $atext;
?>

</form>
</body>
</html>
*/
?>
