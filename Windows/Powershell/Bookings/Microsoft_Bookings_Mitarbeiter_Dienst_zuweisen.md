# Microsoft Bookings – Hannah Mayer für „Unverbindliche Infos zur Teilnahme“ freischalten

**Mitarbeiterin:** Hannah Mayer  
**E-Mail-Adresse:** `h.mayer@trolleymaker.com`  
**Gewünschter Zeitraum:** 03.08.2026 bis 20.08.2026 einschließlich  
**Entfernung:** ab 21.08.2026  
**Zieldienst:** `Unverbindliche Infos zur Teilnahme | …`

> Microsoft Bookings speichert die Zuordnung je Bookings-Kalender mit einer jeweils eigenen Mitarbeiter-ID. Deshalb wird Hannah über ihre E-Mail-Adresse gesucht und anschließend mit der kalenderbezogenen ID dem passenden Dienst zugeordnet oder daraus entfernt.

---

## 1. Anmeldung an Microsoft Graph mit Device Code

```powershell
Connect-MgGraph `
    -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All" `
    -UseDeviceCode
```

Nach Ausgabe des Codes die angezeigte Anmeldeseite öffnen, den Code eingeben und mit dem berechtigten Administratorkonto anmelden.

---

## 2. Graph-Anmeldung kontrollieren

```powershell
Get-MgContext |
    Select-Object `
        Account,
        TenantId,
        AuthType,
        TokenCredentialType,
        Scopes |
    Format-List
```

Erwartet werden insbesondere:

- `AuthType : Delegated`
- `TokenCredentialType : DeviceCode`
- angemeldetes Administratorkonto unter `Account`
- `Bookings.ReadWrite.All`
- `Bookings.Manage.All`

---

## 3. Alle erreichbaren Bookings-Kalender auflisten

```powershell
Get-MgBookingBusiness |
    Select-Object DisplayName, Id |
    Format-Table -AutoSize
```

Die Spalte `Id` enthält in dieser Umgebung in der Regel die Bookings-Kalenderadresse, zum Beispiel `SchwabachCard@trolleymaker.com`.

---

## 4. Prüfen, in welchen Kalendern Hannah als Mitarbeiterin vorhanden ist

Dieser Befehl liest die Rohdaten direkt über Microsoft Graph aus. Nicht erreichbare Bookings-Einträge werden als Warnung ausgegeben.

```powershell
$HannahEmail = "h.mayer@trolleymaker.com"

$MitarbeiterStatus = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Hannah = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $HannahEmail } |
            Select-Object -First 1

        [PSCustomObject]@{
            Kalender      = $Business.DisplayName
            KalenderEmail = $Business.Id
            Anzeigename   = $Hannah.displayName
            EMailAdresse  = if ($Hannah) { $Hannah.emailAddress } else { $HannahEmail }
            MitarbeiterId = $Hannah.id
            Status        = if ($Hannah) { $Hannah.membershipStatus } else { "Nicht im Kalender vorhanden" }
            Rolle         = $Hannah.role
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender      = $Business.DisplayName
            KalenderEmail = $Business.Id
            Anzeigename   = "Hannah Mayer"
            EMailAdresse  = $HannahEmail
            MitarbeiterId = ""
            Status        = "Fehler: $($_.Exception.Message)"
            Rolle         = ""
        }
    }
}

$MitarbeiterStatus |
    Format-List Kalender, KalenderEmail, Anzeigename, EMailAdresse, MitarbeiterId, Status, Rolle
```

---

## 5. Den Zieldienst in allen Kalendern suchen

Dieser Befehl verändert nichts. Er sucht ausschließlich Dienste, deren Name mit `Unverbindliche Infos zur Teilnahme` beginnt.

```powershell
$Dienstname = "Unverbindliche Infos zur Teilnahme"

$GefundeneDienste = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $ServicesResponse.value |
            Where-Object { $_.displayName -like "$Dienstname*" } |
            ForEach-Object {
                [PSCustomObject]@{
                    Kalender       = $Business.DisplayName
                    KalenderEmail  = $Business.Id
                    Dienstleistung = $_.displayName
                    ServiceId      = $_.id
                }
            }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            KalenderEmail  = $Business.Id
            Dienstleistung = ""
            ServiceId      = ""
        }
    }
}

$GefundeneDienste |
    Format-List Kalender, KalenderEmail, Dienstleistung, ServiceId
```

---

## 6. Hannah dem Zieldienst in allen erreichbaren Kalendern zuweisen

Der Befehl:

- sucht Hannah anhand ihrer E-Mail-Adresse,
- verwendet je Kalender ihre dort gültige Mitarbeiter-ID,
- sucht den passenden Dienst,
- behält alle bereits zugeordneten Mitarbeiter,
- ergänzt Hannah nur, wenn sie noch nicht zugeordnet ist,
- gibt Anzeigename, E-Mail-Adresse und Mitarbeiter-ID aus.

```powershell
$HannahEmail = "h.mayer@trolleymaker.com"
$Dienstname  = "Unverbindliche Infos zur Teilnahme"

$ErgebnisZuweisung = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Hannah = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $HannahEmail } |
            Select-Object -First 1

        if (-not $Hannah) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = ""
                Anzeigename    = "Hannah Mayer"
                EMailAdresse   = $HannahEmail
                MitarbeiterId  = ""
                Ergebnis       = "Nicht im Kalender vorhanden"
            }
            return
        }

        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $Dienste = @(
            $ServicesResponse.value |
                Where-Object { $_.displayName -like "$Dienstname*" }
        )

        if ($Dienste.Count -eq 0) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = ""
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Ergebnis       = "Dienst nicht gefunden"
            }
            return
        }

        foreach ($Dienst in $Dienste) {
            $StaffMemberIds = @($Dienst.staffMemberIds)

            if ($StaffMemberIds -contains $Hannah.id) {
                $Status = "Bereits zugeordnet"
            }
            else {
                $StaffMemberIds += $Hannah.id

                $Body = @{
                    staffMemberIds = $StaffMemberIds
                } | ConvertTo-Json -Depth 5

                Invoke-MgGraphRequest `
                    -Method PATCH `
                    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services/$($Dienst.id)" `
                    -Body $Body `
                    -ContentType "application/json" `
                    -ErrorAction Stop |
                    Out-Null

                $Status = "Erfolgreich zugeordnet"
            }

            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = $Dienst.displayName
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Ergebnis       = $Status
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            KalenderEmail  = $Business.Id
            Dienstleistung = ""
            Anzeigename    = "Hannah Mayer"
            EMailAdresse   = $HannahEmail
            MitarbeiterId  = ""
            Ergebnis       = "Fehler: $($_.Exception.Message)"
        }
    }
}

$ErgebnisZuweisung |
    Format-List Kalender, KalenderEmail, Dienstleistung, Anzeigename, EMailAdresse, MitarbeiterId, Ergebnis
```

### Stand des ersten ausgeführten Laufs

- 31-mal erfolgreich zugeordnet
- 4-mal nicht als Mitarbeiterin im Kalender vorhanden
- 3-mal Zieldienst nicht gefunden
- 5 technisch nicht erreichbare bzw. fehlerhafte Bookings-Einträge

---

## 7. Aktuelle Zuweisung vollständig kontrollieren

Dieser Prüfbefehl liest jeden erreichbaren Kalender erneut aus und zeigt, ob Hannah tatsächlich dem Zieldienst zugeordnet ist.

```powershell
$HannahEmail = "h.mayer@trolleymaker.com"
$Dienstname  = "Unverbindliche Infos zur Teilnahme"

$KontrolleZuweisung = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Hannah = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $HannahEmail } |
            Select-Object -First 1

        if (-not $Hannah) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                Dienstleistung = ""
                Anzeigename    = "Hannah Mayer"
                EMailAdresse   = $HannahEmail
                MitarbeiterId  = ""
                Zugeordnet     = $false
                Ergebnis       = "Nicht im Kalender vorhanden"
            }
            return
        }

        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $Dienste = @(
            $ServicesResponse.value |
                Where-Object { $_.displayName -like "$Dienstname*" }
        )

        if ($Dienste.Count -eq 0) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                Dienstleistung = ""
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Zugeordnet     = $false
                Ergebnis       = "Dienst nicht gefunden"
            }
            return
        }

        foreach ($Dienst in $Dienste) {
            $IstZugeordnet = @($Dienst.staffMemberIds) -contains $Hannah.id

            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                Dienstleistung = $Dienst.displayName
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Zugeordnet     = $IstZugeordnet
                Ergebnis       = if ($IstZugeordnet) { "Zugeordnet" } else { "Nicht zugeordnet" }
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            Dienstleistung = ""
            Anzeigename    = "Hannah Mayer"
            EMailAdresse   = $HannahEmail
            MitarbeiterId  = ""
            Zugeordnet     = $false
            Ergebnis       = "Fehler: $($_.Exception.Message)"
        }
    }
}

$KontrolleZuweisung |
    Format-List Kalender, Dienstleistung, Anzeigename, EMailAdresse, MitarbeiterId, Zugeordnet, Ergebnis
```

---

## 8. Hannah ab 21.08.2026 aus dem Zieldienst aller erreichbaren Kalender entfernen

> Dieser Befehl entfernt Hannah sofort beim Ausführen. Er sollte am 21.08.2026 ausgeführt werden. Für einen Testlauf kann er vorher ausgeführt und anschließend mit Abschnitt 6 wieder rückgängig gemacht werden.

Der Befehl entfernt ausschließlich Hannahs kalenderbezogene Mitarbeiter-ID. Alle anderen dem Dienst zugeordneten Mitarbeiter bleiben erhalten.

```powershell
$HannahEmail = "h.mayer@trolleymaker.com"
$Dienstname  = "Unverbindliche Infos zur Teilnahme"

$ErgebnisEntfernung = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Hannah = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $HannahEmail } |
            Select-Object -First 1

        if (-not $Hannah) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = ""
                Anzeigename    = "Hannah Mayer"
                EMailAdresse   = $HannahEmail
                MitarbeiterId  = ""
                Ergebnis       = "Nicht im Kalender vorhanden"
            }
            return
        }

        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $Dienste = @(
            $ServicesResponse.value |
                Where-Object { $_.displayName -like "$Dienstname*" }
        )

        if ($Dienste.Count -eq 0) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = ""
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Ergebnis       = "Dienst nicht gefunden"
            }
            return
        }

        foreach ($Dienst in $Dienste) {
            $BisherigeStaffMemberIds = @($Dienst.staffMemberIds)

            if ($BisherigeStaffMemberIds -notcontains $Hannah.id) {
                $Status = "Bereits nicht mehr zugeordnet"
            }
            else {
                $VerbleibendeStaffMemberIds = @(
                    $BisherigeStaffMemberIds |
                        Where-Object { $_ -ne $Hannah.id }
                )

                $Body = @{
                    staffMemberIds = $VerbleibendeStaffMemberIds
                } | ConvertTo-Json -Depth 5

                Invoke-MgGraphRequest `
                    -Method PATCH `
                    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services/$($Dienst.id)" `
                    -Body $Body `
                    -ContentType "application/json" `
                    -ErrorAction Stop |
                    Out-Null

                $Status = "Erfolgreich entfernt"
            }

            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Dienstleistung = $Dienst.displayName
                Anzeigename    = $Hannah.displayName
                EMailAdresse   = $Hannah.emailAddress
                MitarbeiterId  = $Hannah.id
                Ergebnis       = $Status
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            KalenderEmail  = $Business.Id
            Dienstleistung = ""
            Anzeigename    = "Hannah Mayer"
            EMailAdresse   = $HannahEmail
            MitarbeiterId  = ""
            Ergebnis       = "Fehler: $($_.Exception.Message)"
        }
    }
}

$ErgebnisEntfernung |
    Format-List Kalender, KalenderEmail, Dienstleistung, Anzeigename, EMailAdresse, MitarbeiterId, Ergebnis
```

---

## 9. Entfernung kontrollieren

Nach dem Entfernen Abschnitt 7 erneut ausführen. Bei allen erreichbaren Zieldiensten muss dann stehen:

```text
Zugeordnet : False
Ergebnis   : Nicht zugeordnet
```

Die Ausgabe enthält gleichzeitig:

- Bookings-Kalender
- Dienstleistung
- Anzeigename
- E-Mail-Adresse
- kalenderbezogene Mitarbeiter-ID

---

## 10. Nach einem Test wieder hinzufügen

Wurde Abschnitt 8 nur zu Testzwecken ausgeführt, anschließend Abschnitt 6 erneut ausführen. Bereits vorhandene Zuordnungen werden dabei nicht doppelt angelegt.

---

## 11. Graph-Sitzung beenden

```powershell
Disconnect-MgGraph
```

---

## Hinweise zu den aktuell auffälligen Kalendern

Beim dokumentierten Lauf ergaben sich folgende Sonderfälle:

### Mitarbeiterin nicht im Kalender vorhanden

- KenzingenCARD
- Stadtgutschein Troisdorf
- AbensbergCARD
- CalwCARD

### Zieldienst nicht gefunden

- Simpli City | Card. App. Portal.
- Simpli Citycard
- Partnerwaltungskalender

### Bookings-Eintrag über Graph nicht abrufbar

- trolleymaker System-Einweisung
- SmartCityCARD & APP DEMO
- LahrCARD | WorkShops
- Smart Country Convention – Simpli-Citycard
- Simpli-Citycard – Adiel Ahmed Munir

Diese Einträge werden durch die Sammelskripte protokolliert, aber nicht verändert.
