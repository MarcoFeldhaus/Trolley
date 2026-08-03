$inputFile = "C:\Users\m.feldhaus\Downloads\agbs_decoded.html"
$outputFile = "C:\Users\m.feldhaus\Downloads\agb-allgemein_formatiert.html"

$content = Get-Content -Path $inputFile -Raw -Encoding UTF8

Add-Type -AssemblyName System.Web
$content = [System.Web.HttpUtility]::HtmlDecode($content)

# Geschützte Leerzeichen normalisieren
$content = $content -replace [char]0x00A0, ' '

# Leere Absätze entfernen
$content = $content -replace '(?is)<p>\s*</p>', ''
$content = $content -replace '(?is)<p>\s*<strong>\s*</strong>\s*</p>', ''

# Falls die Datei noch keinen Header hat: Teil A oben ergänzen
if ($content -notmatch '(?is)<h1>\s*AGB trolleymaker Akzeptanz-Partner\s*</h1>') {
    $content = "<h1>AGB trolleymaker Akzeptanz-Partner</h1>`r`n<br />`r`n<h2>Teil A: AGB [card_name]</h2>`r`n" + $content
}

# Top-Level-OL-Überschriften in alte Struktur umwandeln:
# <ol><li><strong>Vertragsgegenstand</strong></li></ol>
# => <strong>1. Vertragsgegenstand</strong><br /><br />
$content = $content -replace '(?is)<ol>\s*<li>\s*<strong>\s*(.*?)\s*</strong>\s*</li>\s*</ol>', '<strong>1. $1</strong><br /><br />'

# <ol start="2"><li><strong>Programmablauf</strong></li></ol>
# => <strong>2. Programmablauf</strong><br /><br />
$content = $content -replace '(?is)<ol\s+start="(\d+)">\s*<li>\s*<strong>\s*(.*?)\s*</strong>\s*</li>\s*</ol>', '<strong>$1. $2</strong><br /><br />'

# Fälle mit verschachtelter UL direkt im LI:
# <ol start="3"><li><strong>Leistungen...</strong><ul>...</ul></li></ol>
# => <strong>3. Leistungen...</strong><br /><br /><ul>...</ul>
$content = $content -replace '(?is)<ol\s+start="(\d+)">\s*<li>\s*<strong>\s*(.*?)\s*</strong>\s*(<ul>.*?</ul>)\s*</li>\s*</ol>', '<strong>$1. $2</strong><br /><br />$3'
$content = $content -replace '(?is)<ol>\s*<li>\s*<strong>\s*(.*?)\s*</strong>\s*(<ul>.*?</ul>)\s*</li>\s*</ol>', '<strong>1. $1</strong><br /><br />$2'

# Teil B erkennen:
# Nach Teil A Schlussbestimmungen kommt nochmal "1. Vertragsgegenstand".
# Vor die zweite 1. Vertragsgegenstand-Überschrift Teil-B-Header setzen.
$needle = '<strong>1. Vertragsgegenstand</strong><br /><br />'
$firstIndex = $content.IndexOf($needle)
if ($firstIndex -ge 0) {
    $secondIndex = $content.IndexOf($needle, $firstIndex + $needle.Length)

    if ($secondIndex -ge 0 -and $content -notmatch '(?is)<h1>\s*AGB trolleymaker Partner MitarbeiterCARD\s*</h1>') {
        $teilBHeader = "`r`n<br />`r`n<h1>AGB trolleymaker Partner MitarbeiterCARD</h1>`r`n<br />`r`n<h2>Teil B: AGB [region_personenbezeichnung] MitarbeiterCARD</h2>`r`n"
        $content = $content.Substring(0, $secondIndex) + $teilBHeader + $content.Substring($secondIndex)
    }
}

# Absätze in alte PartnerAGB-Optik umwandeln:
# <p>Text</p> => Text<br /><br />
$content = $content -replace '(?is)<p>\s*(.*?)\s*</p>', '$1<br /><br />'

# UL/LI etwas lesbarer machen
$content = $content -replace '(?is)<ul>\s*', ''
$content = $content -replace '(?is)\s*</ul>', '<br />'
$content = $content -replace '(?is)<li>\s*(.*?)\s*</li>', '$1<br /><br />'

# Doppelte / übermäßige Breaks reduzieren
$content = $content -replace '(<br\s*/?>\s*){4,}', '<br /><br />'

# Whitespace bereinigen
$content = $content -replace "(\r?\n){3,}", "`r`n`r`n"

Set-Content -Path $outputFile -Value $content -Encoding UTF8

Write-Host "Fertig: $outputFile"