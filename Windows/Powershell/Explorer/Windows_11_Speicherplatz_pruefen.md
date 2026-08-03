# Windows 11 – Speicherplatz prüfen und große Dateien finden

## 1. Freien Speicherplatz auf C: und D: prüfen

PowerShell öffnen und ausführen:

```powershell
Get-PSDrive C, D
```

Wichtig sind die Spalten:

- `Used` = belegter Speicher
- `Free` = freier Speicher

---

## 2. Größe der wichtigsten Hauptordner auf C: prüfen

PowerShell **als Administrator** öffnen und den kompletten Block ausführen:

```powershell
$Pfade = @(
    'C:\Users',
    'C:\Windows',
    'C:\Program Files',
    'C:\Program Files (x86)',
    'C:\ProgramData'
)

$Ergebnis = foreach ($Pfad in $Pfade) {
    Write-Host "Prüfe $Pfad ..."

    $Summe = (
        Get-ChildItem -LiteralPath $Pfad -File -Recurse -Force `
            -ErrorAction SilentlyContinue |
        Measure-Object -Property Length -Sum
    ).Sum

    [PSCustomObject]@{
        Ordner    = $Pfad
        GroesseGB = [math]::Round($Summe / 1GB, 2)
    }
}

$Ergebnis |
    Sort-Object GroesseGB -Descending |
    Format-Table -AutoSize
```

Der Vorgang kann einige Minuten dauern. Es wird nichts gelöscht.

---

## 3. Größte Ordner im eigenen Benutzerprofil ermitteln

PowerShell öffnen und ausführen:

```powershell
$Profil = $env:USERPROFILE

Get-ChildItem -LiteralPath $Profil -Directory -Force |
ForEach-Object {
    Write-Host "Prüfe $($_.FullName) ..."

    $Summe = (
        Get-ChildItem -LiteralPath $_.FullName -File -Recurse -Force `
            -ErrorAction SilentlyContinue |
        Measure-Object -Property Length -Sum
    ).Sum

    [PSCustomObject]@{
        Ordner    = $_.Name
        GroesseGB = [math]::Round($Summe / 1GB, 2)
    }
} |
Sort-Object GroesseGB -Descending |
Select-Object -First 20 |
Format-Table -AutoSize
```

Besonders prüfen:

- `AppData`
- `Downloads`
- `Trolleymaker_Gitlab`
- `DEVELOPMENT`
- `Git-Sicherungen`
- `source`
- `go`

---

## 4. Größte Einzeldateien auf C: suchen

PowerShell **als Administrator** öffnen und ausführen:

```powershell
Get-ChildItem C:\ -File -Recurse -Force -ErrorAction SilentlyContinue |
Sort-Object Length -Descending |
Select-Object -First 30 `
    @{Name='GroesseGB';Expression={[math]::Round($_.Length / 1GB, 2)}},
    FullName |
Format-Table -AutoSize
```

Der Vorgang kann länger dauern.

Typische große Dateien:

- SQL-Backups: `*.bak`
- SQL-Datenbanken: `*.mdf`, `*.ldf`
- Archive: `*.zip`, `*.7z`
- Installationsdateien: `*.iso`, `*.exe`, `*.msi`
- Videos: `*.mp4`
- alte Sicherungen und Exportdateien

---

## 5. Optional: Ruhezustandsdatei entfernen

Auf dem Rechner belegt `hiberfil.sys` etwa 13 GB.

Nur ausführen, wenn der Windows-Ruhezustand nicht benötigt wird.

PowerShell **als Administrator**:

```powershell
powercfg /hibernate off
```

Dadurch werden Ruhezustand und meist auch Windows-Schnellstart deaktiviert.

Wieder aktivieren:

```powershell
powercfg /hibernate on
```

---

## Nicht manuell löschen

Diese Dateien gehören zu Windows:

- `pagefile.sys`
- `hiberfil.sys`
- `swapfile.sys`
- `NTUSER.DAT`
- `ntuser.dat.LOG1`
- `ntuser.dat.LOG2`

`pagefile.sys` nicht löschen oder deaktivieren, bevor geprüft wurde, welche anderen Dateien und Ordner den Speicher belegen.
