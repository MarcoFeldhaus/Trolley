# PowerShell-Hilfsskript: HTML-Entities decodieren

## Zweck

Dieses Skript liest eine HTML-Datei ein, decodiert HTML-Entities und speichert das Ergebnis als neue UTF-8-Datei.

Beispiele für HTML-Entities:

```text
&amp;  wird zu &
&auml; wird zu ä
&quot; wird zu "
```

---

# 1. System.Web-Assembly laden

```powershell
Add-Type -AssemblyName System.Web
```

---

# 2. Eingabe- und Ausgabedatei festlegen

```powershell
$InputFile  = "C:\Users\m.feldhaus\Downloads\agbs.html"
$OutputFile = "C:\Users\m.feldhaus\Downloads\agbs_decoded.html"
```

---

# 3. HTML-Datei einlesen und decodieren

```powershell
$Content = Get-Content `
    -Path $InputFile `
    -Raw `
    -Encoding UTF8

$Decoded = [System.Web.HttpUtility]::HtmlDecode($Content)
```

---

# 4. Decodiertes Ergebnis speichern

```powershell
Set-Content `
    -Path $OutputFile `
    -Value $Decoded `
    -Encoding UTF8
```

---

# 5. Vollständiger Befehl

```powershell
Add-Type -AssemblyName System.Web

$InputFile  = "C:\Users\m.feldhaus\Downloads\agbs.html"
$OutputFile = "C:\Users\m.feldhaus\Downloads\agbs_decoded.html"

$Content = Get-Content `
    -Path $InputFile `
    -Raw `
    -Encoding UTF8

$Decoded = [System.Web.HttpUtility]::HtmlDecode($Content)

Set-Content `
    -Path $OutputFile `
    -Value $Decoded `
    -Encoding UTF8

Write-Host "Datei erstellt: $OutputFile" -ForegroundColor Green
```

---

# 6. Ergebnis kontrollieren

```powershell
Get-Item $OutputFile |
    Format-List `
        FullName,
        Length,
        LastWriteTime
```

Optional kann der Anfang der neuen Datei angezeigt werden:

```powershell
Get-Content `
    -Path $OutputFile `
    -TotalCount 20
```
