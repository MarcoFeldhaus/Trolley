# Microsoft Bookings – Adiel Munir Administratorrechte in allen erreichbaren Kalendern erteilen

**Mitarbeiter:** Adiel Munir  
**E-Mail-Adresse:** `a.munir@trolleymaker.com`  
**Ziel:** In allen erreichbaren Microsoft-Bookings-Kalendern, in denen Adiel Munir als Mitarbeiter vorhanden ist, die Rolle auf `administrator` setzen.

> Hinweis: Die Bookings-Mitarbeiter-ID ist kalenderbezogen. Deshalb wird Adiel Munir in jedem Kalender über seine E-Mail-Adresse gesucht und anschließend der jeweilige Mitarbeiter-Eintrag aktualisiert.

---

## 1. Microsoft Graph verbinden

In der verwendeten Umgebung trat mit Microsoft.Graph 2.36.1 ein Authentifizierungsproblem auf, bei dem `Connect-MgGraph` zwar erfolgreich wirkte, nachfolgende Graph-Aufrufe aber mit `DeviceCodeCredential authentication failed` scheiterten.

Funktioniert hat die Anmeldung mit einem prozessgebundenen Graph-Kontext:

```powershell
Connect-MgGraph `
    -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All" `
    -UseDeviceCode `
    -ContextScope Process
```

Anschließend mit einem berechtigten Administratorkonto anmelden.

---

## 2. Graph-Verbindung testen

```powershell
Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/me" |
    Select-Object displayName, userPrincipalName
```

Erwartet wird die Identität des aktuell angemeldeten Administratorkontos.

---

## 3. Erreichbare Bookings-Kalender auflisten

```powershell
Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses" |
    Select-Object -ExpandProperty value |
    Select-Object displayName, id |
    Format-Table -AutoSize
```

Dieser Befehl verändert nichts.

---

## 4. Prüfen, in welchen Bookings-Kalendern Adiel Munir vorhanden ist

```powershell
$MunirEmail = "a.munir@trolleymaker.com"

$MitarbeiterStatus = (Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses").value |
    ForEach-Object {

        $Business = $_
        $BusinessIdEncoded = [uri]::EscapeDataString($Business.id)

        try {
            $StaffResponse = Invoke-MgGraphRequest `
                -Method GET `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
                -ErrorAction Stop

            $Munir = $StaffResponse.value |
                Where-Object { $_.emailAddress -ieq $MunirEmail } |
                Select-Object -First 1

            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = $Munir.displayName
                EMailAdresse  = if ($Munir) { $Munir.emailAddress } else { $MunirEmail }
                MitarbeiterId = $Munir.id
                Status        = if ($Munir) { $Munir.membershipStatus } else { "Nicht im Kalender vorhanden" }
                Rolle         = $Munir.role
            }
        }
        catch {
            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = ""
                EMailAdresse  = $MunirEmail
                MitarbeiterId = ""
                Status        = "Fehler: $($_.Exception.Message)"
                Rolle         = ""
            }
        }
    }

$MitarbeiterStatus |
    Format-List Kalender, KalenderEmail, Anzeigename, EMailAdresse, MitarbeiterId, Status, Rolle
```

Im dokumentierten Lauf war Adiel Munir in den meisten erreichbaren Kalendern vorhanden und dort zunächst als `scheduler` eingetragen.

---

## 5. Einzeltest mit SchwabachCARD

Vor der Sammeländerung wurde die Rollenänderung zunächst nur in einem einzelnen Kalender getestet.

### 5.1 Rolle auf Administrator setzen

```powershell
$BusinessId = [uri]::EscapeDataString("SchwabachCard@trolleymaker.com")
$StaffId    = "c00f333a-fc74-4424-8bf9-c368c01e20d6"

$Body = @{
    "@odata.type" = "#microsoft.graph.bookingStaffMember"
    role          = "administrator"
} | ConvertTo-Json

Invoke-MgGraphRequest `
    -Method PATCH `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessId/staffMembers/$StaffId" `
    -Body $Body `
    -ContentType "application/json"
```

Wichtig: In dieser Umgebung war das Mitsenden von

```text
"@odata.type" = "#microsoft.graph.bookingStaffMember"
```

entscheidend. Ein PATCH nur mit `role = "administrator"` lief zwar ohne Fehlermeldung durch, änderte die Rolle aber nicht.

### 5.2 Einzeltest kontrollieren

```powershell
Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessId/staffMembers/$StaffId" |
    Select-Object displayName, emailAddress, membershipStatus, role |
    Format-List
```

Erwartet:

```text
displayName      : Adiel Munir
emailAddress     : a.munir@trolleymaker.com
membershipStatus : active
role             : administrator
```

---

## 6. Administratorrolle in allen erreichbaren Kalendern setzen

Dieser Befehl:

- liest alle erreichbaren Bookings-Kalender,
- sucht Adiel Munir anhand seiner E-Mail-Adresse,
- verwendet die jeweils kalenderbezogene Mitarbeiter-ID,
- überspringt Kalender, in denen er nicht vorhanden ist,
- überspringt Einträge, die bereits `administrator` sind,
- setzt alle übrigen vorhandenen Einträge auf `administrator`,
- protokolliert Fehler und Sonderfälle.

```powershell
$MunirEmail = "a.munir@trolleymaker.com"

$ErgebnisAdmin = (Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses").value |
    ForEach-Object {

        $Business = $_
        $BusinessIdEncoded = [uri]::EscapeDataString($Business.id)

        try {
            $StaffResponse = Invoke-MgGraphRequest `
                -Method GET `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
                -ErrorAction Stop

            $Munir = $StaffResponse.value |
                Where-Object { $_.emailAddress -ieq $MunirEmail } |
                Select-Object -First 1

            if (-not $Munir) {
                [PSCustomObject]@{
                    Kalender      = $Business.displayName
                    KalenderEmail = $Business.id
                    Anzeigename   = ""
                    EMailAdresse  = $MunirEmail
                    MitarbeiterId = ""
                    AlteRolle     = ""
                    NeueRolle     = ""
                    Ergebnis      = "Nicht im Kalender vorhanden"
                }
                return
            }

            if ($Munir.role -eq "administrator") {
                [PSCustomObject]@{
                    Kalender      = $Business.displayName
                    KalenderEmail = $Business.id
                    Anzeigename   = $Munir.displayName
                    EMailAdresse  = $Munir.emailAddress
                    MitarbeiterId = $Munir.id
                    AlteRolle     = $Munir.role
                    NeueRolle     = $Munir.role
                    Ergebnis      = "Bereits Administrator"
                }
                return
            }

            $Body = @{
                "@odata.type" = "#microsoft.graph.bookingStaffMember"
                role          = "administrator"
            } | ConvertTo-Json

            Invoke-MgGraphRequest `
                -Method PATCH `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers/$($Munir.id)" `
                -Body $Body `
                -ContentType "application/json" `
                -ErrorAction Stop |
                Out-Null

            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = $Munir.displayName
                EMailAdresse  = $Munir.emailAddress
                MitarbeiterId = $Munir.id
                AlteRolle     = $Munir.role
                NeueRolle     = "administrator"
                Ergebnis      = "Erfolgreich geändert"
            }
        }
        catch {
            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = ""
                EMailAdresse  = $MunirEmail
                MitarbeiterId = ""
                AlteRolle     = ""
                NeueRolle     = ""
                Ergebnis      = "Fehler: $($_.Exception.Message)"
            }
        }
    }

$ErgebnisAdmin |
    Format-List Kalender, KalenderEmail, Anzeigename, EMailAdresse, MitarbeiterId, AlteRolle, NeueRolle, Ergebnis
```

---

## 7. Ergebnis vollständig kontrollieren

Dieser Befehl verändert nichts.

```powershell
$MunirEmail = "a.munir@trolleymaker.com"

$KontrolleAdmin = (Invoke-MgGraphRequest `
    -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses").value |
    ForEach-Object {

        $Business = $_
        $BusinessIdEncoded = [uri]::EscapeDataString($Business.id)

        try {
            $StaffResponse = Invoke-MgGraphRequest `
                -Method GET `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
                -ErrorAction Stop

            $Munir = $StaffResponse.value |
                Where-Object { $_.emailAddress -ieq $MunirEmail } |
                Select-Object -First 1

            if (-not $Munir) {
                [PSCustomObject]@{
                    Kalender      = $Business.displayName
                    KalenderEmail = $Business.id
                    Anzeigename   = ""
                    EMailAdresse  = $MunirEmail
                    MitarbeiterId = ""
                    Status        = "Nicht im Kalender vorhanden"
                    Rolle         = ""
                    Kontrolle     = "Nicht prüfbar"
                }
                return
            }

            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = $Munir.displayName
                EMailAdresse  = $Munir.emailAddress
                MitarbeiterId = $Munir.id
                Status        = $Munir.membershipStatus
                Rolle         = $Munir.role
                Kontrolle     = if ($Munir.role -eq "administrator") {
                    "OK - Administrator"
                }
                else {
                    "ACHTUNG - Rolle ist $($Munir.role)"
                }
            }
        }
        catch {
            [PSCustomObject]@{
                Kalender      = $Business.displayName
                KalenderEmail = $Business.id
                Anzeigename   = ""
                EMailAdresse  = $MunirEmail
                MitarbeiterId = ""
                Status        = "Fehler"
                Rolle         = ""
                Kontrolle     = "Fehler: $($_.Exception.Message)"
            }
        }
    }

$KontrolleAdmin |
    Format-List Kalender, KalenderEmail, Anzeigename, EMailAdresse, MitarbeiterId, Status, Rolle, Kontrolle
```

Bei allen erfolgreich erreichbaren Kalendern, in denen Adiel Munir vorhanden ist, muss stehen:

```text
Rolle     : administrator
Kontrolle : OK - Administrator
```

---

## 8. Stand des erfolgreichen Kontrolllaufs vom 20.08.2026

Die Rollenänderung wurde erfolgreich durchgeführt und anschließend vollständig kontrolliert.

Bei allen erreichbaren Kalendern, in denen Adiel Munir vorhanden ist, wurde die Rolle `administrator` bestätigt.

Beispiele:

- SchwabachCARD
- WürmtalCARD
- LahrCARD
- Herbolzheim Karte
- EttenheimCARD
- LE CARD
- LandshutCARD
- WunCARD
- KenzingenCARD
- Stadtgutschein Troisdorf
- BalingenCARD
- ViertälerCARD
- AbensbergCARD
- NEURIEDCARD & APP
- CalwCARD
- RatingenCARD
- HaslachCARD
- Erlebnisregion Europa-Park CARD
- SR CARD
- ECHT FREIBURG CARD
- PfulbenCARD
- Baden-Baden CARD
- DETTINGEN ERMSCARD
- Bad Waldsee CityCARD
- 0711CARD
- BesigheimCARD
- HÜCARD
- MannheimCARD
- NeubulachCARD
- EmmendingenCARD
- TeckCARD
- Offenburg+ CARD
- KlettgauCARD
- FleinCARD

Auch bei folgenden Einträgen wurde die Rolle `administrator` gesetzt, obwohl der Mitgliedsstatus noch `pendingAcceptance` lautet:

- Henstedt-Ulzburg SmartCARD
- FördeCARD

---

## 9. Bekannte Sonderfälle

### Bookings-Eintrag über Graph nicht abrufbar

Folgende Einträge lieferten beim Abruf der Mitarbeiter weiterhin `NotFound`:

- trolleymaker System-Einweisung
- SmartCityCARD & APP DEMO
- LahrCARD | WorkShops
- Smart Country Convention – Simpli-Citycard
- Simpli-Citycard - Adiel Ahmed Munir

Diese Einträge wurden nicht verändert.

### Adiel Munir nicht im Kalender vorhanden

Bei folgenden Kalendern war `a.munir@trolleymaker.com` nicht als Mitarbeiter vorhanden:

- Simpli City | Card. App. Portal.
- Simpli Citycard
- Partnerwaltungskalender

Diese Kalender wurden ebenfalls nicht verändert.

---

## 10. Graph-Sitzung beenden

```powershell
Disconnect-MgGraph
```

---

## Ergebnis

Adiel Munir (`a.munir@trolleymaker.com`) besitzt nun in allen erreichbaren Microsoft-Bookings-Kalendern, in denen er als Mitarbeiter vorhanden ist, die Rolle:

```text
administrator
```

Damit verfügt er dort über die Bookings-Administratorrolle und kann die entsprechenden administrativen Bookings-Funktionen verwenden.
