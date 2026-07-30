# Microsoft Bookings: Mitarbeiter in allen Kalendern und Diensten suchen und vollständig entfernen

## Zweck

Diese Dokumentation beschreibt, wie ein bestimmter Mitarbeiter in Microsoft Bookings:

1. per Microsoft Graph PowerShell gesucht wird,
2. in allen erreichbaren Bookings-Kalendern gefunden wird,
3. aus allen zugeordneten Dienstleistungen entfernt wird,
4. anschließend vollständig aus den Bookings-Kalendern entfernt wird,
5. abschließend kontrolliert wird.

Die Befehle wurden mit folgendem Beispiel getestet:

- Anzeigename: `Michael Seidel`
- E-Mail-Adresse: `m.seidel@trolleymaker.com`

Für einen anderen Mitarbeiter muss in den Skripten grundsätzlich nur diese Variable angepasst werden:

```powershell
$MitarbeiterEmail = "andere.person@trolleymaker.com"
```

---

# 1. Anmeldung an Microsoft Graph per Device Code

## Beschreibung

Die Anmeldung erfolgt delegiert über den Device-Code-Flow.

```powershell
Connect-MgGraph `
    -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All" `
    -UseDeviceCode
```

Danach:

1. die angezeigte Microsoft-Anmeldeseite öffnen,
2. den Device Code eingeben,
3. mit einem berechtigten Microsoft-365-Konto anmelden.

---

# 2. Graph-Anmeldung kontrollieren

## Beschreibung

Dieser Befehl zeigt den aktuellen Microsoft-Graph-Kontext an.

```powershell
Get-MgContext
```

Wichtige Werte:

```text
AuthType            : Delegated
TokenCredentialType : DeviceCode
Account             : eigenes Administratorkonto
Scopes              : Bookings.ReadWrite.All, Bookings.Manage.All
```

---

# 3. Alle Bookings-Kalender anzeigen

## Beschreibung

Dieser Befehl listet alle Bookings-Kalender auf, die über Microsoft Graph gefunden werden.

```powershell
Get-MgBookingBusiness |
    Format-Table DisplayName, Id -AutoSize
```

Dabei entspricht:

- `DisplayName` dem sichtbaren Kalendernamen,
- `Id` normalerweise der Bookings-Kalenderadresse.

---

# 4. Mitarbeiter in allen Kalendern und Diensten suchen

## Beschreibung

Dieser Befehl:

- durchsucht alle erreichbaren Bookings-Kalender,
- sucht den Mitarbeiter anhand der E-Mail-Adresse,
- zeigt Anzeigename, E-Mail-Adresse und Mitarbeiter-ID,
- zeigt alle Dienste, denen der Mitarbeiter zugeordnet ist,
- gibt technisch nicht erreichbare Kalender als Fehler aus.

Vor dem Ausführen die E-Mail-Adresse anpassen:

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"
```

## Befehl

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"

$Suchergebnis = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Mitarbeiter = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $MitarbeiterEmail } |
            Select-Object -First 1

        if (-not $Mitarbeiter) {
            return
        }

        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $ZugeordneteDienste = @(
            $ServicesResponse.value |
                Where-Object {
                    @($_.staffMemberIds) -contains $Mitarbeiter.id
                }
        )

        if ($ZugeordneteDienste.Count -eq 0) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Anzeigename    = $Mitarbeiter.displayName
                EMailAdresse   = $Mitarbeiter.emailAddress
                MitarbeiterId  = $Mitarbeiter.id
                Rolle          = $Mitarbeiter.role
                Status         = $Mitarbeiter.membershipStatus
                Dienstleistung = "Keine Dienstzuordnung"
                ServiceId      = ""
            }
        }
        else {
            foreach ($Dienst in $ZugeordneteDienste) {
                [PSCustomObject]@{
                    Kalender       = $Business.DisplayName
                    KalenderEmail  = $Business.Id
                    Anzeigename    = $Mitarbeiter.displayName
                    EMailAdresse   = $Mitarbeiter.emailAddress
                    MitarbeiterId  = $Mitarbeiter.id
                    Rolle          = $Mitarbeiter.role
                    Status         = $Mitarbeiter.membershipStatus
                    Dienstleistung = $Dienst.displayName
                    ServiceId      = $Dienst.id
                }
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            KalenderEmail  = $Business.Id
            Anzeigename    = ""
            EMailAdresse   = $MitarbeiterEmail
            MitarbeiterId  = ""
            Rolle          = ""
            Status         = "Fehler"
            Dienstleistung = $_.Exception.Message
            ServiceId      = ""
        }
    }
}

$Suchergebnis |
    Format-List `
        Kalender,
        KalenderEmail,
        Anzeigename,
        EMailAdresse,
        MitarbeiterId,
        Rolle,
        Status,
        Dienstleistung,
        ServiceId
```

---

# 5. Worauf beim Suchergebnis achten?

## Mitarbeiter

Die gesuchte Person wird über diese Variable festgelegt:

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"
```

## Kalender

Der Bookings-Kalender steht in:

```powershell
$Eintrag.KalenderEmail
```

Beispiel:

```text
RatingenCARD@trolleymaker.com
MannheimCARD@trolleymaker.com
```

## Dienst

Der konkrete Dienst wird über seine Service-ID angesprochen:

```powershell
$Eintrag.ServiceId
```

Der sichtbare Name steht in:

```powershell
$Eintrag.Dienstleistung
```

## Mitarbeiter-ID

Die Zuordnung innerhalb eines Dienstes erfolgt technisch über die jeweilige Mitarbeiter-ID:

```powershell
$Eintrag.MitarbeiterId
```

Wichtig: Die Mitarbeiter-ID kann je Bookings-Kalender unterschiedlich sein.

---

# 6. Mitarbeiter aus allen gefundenen Dienstleistungen entfernen

## Beschreibung

Dieser Befehl:

- verwendet ausschließlich die vorher in `$Suchergebnis` gefundenen Zuordnungen,
- entfernt nur die jeweilige Mitarbeiter-ID,
- erhält alle anderen zugeordneten Mitarbeiter,
- entfernt den Mitarbeiter noch nicht aus dem Kalender selbst.

## Befehl

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"

$DienstEntfernung = $Suchergebnis |
    Where-Object {
        $_.Status -ne "Fehler" -and
        $_.ServiceId -and
        $_.MitarbeiterId
    } |
    ForEach-Object {

        $Eintrag           = $_
        $BusinessIdEncoded = [uri]::EscapeDataString($Eintrag.KalenderEmail)

        try {
            $Service = Invoke-MgGraphRequest `
                -Method GET `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services/$($Eintrag.ServiceId)" `
                -ErrorAction Stop

            $BisherigeIds = @($Service.staffMemberIds)

            if ($BisherigeIds -notcontains $Eintrag.MitarbeiterId) {
                $Status = "Bereits nicht mehr zugeordnet"
            }
            else {
                $VerbleibendeIds = @(
                    $BisherigeIds |
                    Where-Object { $_ -ne $Eintrag.MitarbeiterId }
                )

                $Body = @{
                    staffMemberIds = $VerbleibendeIds
                } | ConvertTo-Json -Depth 5

                Invoke-MgGraphRequest `
                    -Method PATCH `
                    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services/$($Eintrag.ServiceId)" `
                    -Body $Body `
                    -ContentType "application/json" `
                    -ErrorAction Stop |
                    Out-Null

                $Status = "Aus Dienst entfernt"
            }

            [PSCustomObject]@{
                Kalender       = $Eintrag.Kalender
                Dienstleistung = $Eintrag.Dienstleistung
                Anzeigename    = $Eintrag.Anzeigename
                EMailAdresse   = $Eintrag.EMailAdresse
                MitarbeiterId  = $Eintrag.MitarbeiterId
                Ergebnis       = $Status
            }
        }
        catch {
            [PSCustomObject]@{
                Kalender       = $Eintrag.Kalender
                Dienstleistung = $Eintrag.Dienstleistung
                Anzeigename    = $Eintrag.Anzeigename
                EMailAdresse   = $MitarbeiterEmail
                MitarbeiterId  = $Eintrag.MitarbeiterId
                Ergebnis       = "Fehler: $($_.Exception.Message)"
            }
        }
    }

$DienstEntfernung |
    Format-List `
        Kalender,
        Dienstleistung,
        Anzeigename,
        EMailAdresse,
        MitarbeiterId,
        Ergebnis
```

Erfolgreiche Einträge zeigen:

```text
Ergebnis : Aus Dienst entfernt
```

---

# 7. Entfernung aus den Diensten kontrollieren

## Beschreibung

Dieser Befehl prüft erneut alle erreichbaren Kalender.

Der Mitarbeiter bleibt zu diesem Zeitpunkt noch als Mitarbeiter im Bookings-Kalender vorhanden, darf aber keinem Dienst mehr zugeordnet sein.

## Befehl

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"

$Pruefergebnis = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Mitarbeiter = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $MitarbeiterEmail } |
            Select-Object -First 1

        if (-not $Mitarbeiter) {
            return
        }

        $ServicesResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/services" `
            -ErrorAction Stop

        $ZugeordneteDienste = @(
            $ServicesResponse.value |
                Where-Object {
                    @($_.staffMemberIds) -contains $Mitarbeiter.id
                }
        )

        if ($ZugeordneteDienste.Count -eq 0) {
            [PSCustomObject]@{
                Kalender       = $Business.DisplayName
                KalenderEmail  = $Business.Id
                Anzeigename    = $Mitarbeiter.displayName
                EMailAdresse   = $Mitarbeiter.emailAddress
                MitarbeiterId  = $Mitarbeiter.id
                Dienstleistung = "Keine Dienstzuordnung"
                Ergebnis       = "OK"
            }
        }
        else {
            foreach ($Dienst in $ZugeordneteDienste) {
                [PSCustomObject]@{
                    Kalender       = $Business.DisplayName
                    KalenderEmail  = $Business.Id
                    Anzeigename    = $Mitarbeiter.displayName
                    EMailAdresse   = $Mitarbeiter.emailAddress
                    MitarbeiterId  = $Mitarbeiter.id
                    Dienstleistung = $Dienst.displayName
                    Ergebnis       = "Noch zugeordnet"
                }
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender       = $Business.DisplayName
            KalenderEmail  = $Business.Id
            Anzeigename    = ""
            EMailAdresse   = $MitarbeiterEmail
            MitarbeiterId  = ""
            Dienstleistung = ""
            Ergebnis       = "Fehler: $($_.Exception.Message)"
        }
    }
}

$Pruefergebnis |
    Format-List `
        Kalender,
        KalenderEmail,
        Anzeigename,
        EMailAdresse,
        MitarbeiterId,
        Dienstleistung,
        Ergebnis
```

Erwartete erfolgreiche Ausgabe:

```text
Dienstleistung : Keine Dienstzuordnung
Ergebnis       : OK
```

---

# 8. Mitarbeiter vollständig aus allen gefundenen Bookings-Kalendern entfernen

## Beschreibung

Dieser Befehl entfernt den Mitarbeiter vollständig aus den Kalendern, in denen die Dienstprüfung zuvor `OK` ergeben hat.

Der Befehl verwendet die Ergebnisse aus:

```powershell
$Pruefergebnis
```

## Befehl

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"

$KalenderEntfernung = $Pruefergebnis |
    Where-Object {
        $_.Ergebnis -eq "OK" -and
        $_.MitarbeiterId -and
        $_.KalenderEmail
    } |
    ForEach-Object {

        $Eintrag           = $_
        $BusinessIdEncoded = [uri]::EscapeDataString($Eintrag.KalenderEmail)

        try {
            Invoke-MgGraphRequest `
                -Method DELETE `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers/$($Eintrag.MitarbeiterId)" `
                -ErrorAction Stop |
                Out-Null

            [PSCustomObject]@{
                Kalender      = $Eintrag.Kalender
                KalenderEmail = $Eintrag.KalenderEmail
                Anzeigename   = $Eintrag.Anzeigename
                EMailAdresse  = $Eintrag.EMailAdresse
                MitarbeiterId = $Eintrag.MitarbeiterId
                Ergebnis      = "Aus Kalender entfernt"
            }
        }
        catch {
            [PSCustomObject]@{
                Kalender      = $Eintrag.Kalender
                KalenderEmail = $Eintrag.KalenderEmail
                Anzeigename   = $Eintrag.Anzeigename
                EMailAdresse  = $Eintrag.EMailAdresse
                MitarbeiterId = $Eintrag.MitarbeiterId
                Ergebnis      = "Fehler: $($_.Exception.Message)"
            }
        }
    }

$KalenderEntfernung |
    Format-List `
        Kalender,
        KalenderEmail,
        Anzeigename,
        EMailAdresse,
        MitarbeiterId,
        Ergebnis
```

Erfolgreiche Einträge zeigen:

```text
Ergebnis : Aus Kalender entfernt
```

---

# 9. Vollständige Entfernung abschließend prüfen

## Beschreibung

Dieser Befehl sucht den Mitarbeiter erneut in allen erreichbaren Bookings-Kalendern.

Wenn der Mitarbeiter vollständig entfernt wurde, erscheinen für die zuvor betroffenen Kalender keine Treffer mehr.

## Befehl

```powershell
$MitarbeiterEmail = "m.seidel@trolleymaker.com"

$Abschlusspruefung = Get-MgBookingBusiness | ForEach-Object {
    $Business          = $_
    $BusinessIdEncoded = [uri]::EscapeDataString($Business.Id)

    try {
        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$BusinessIdEncoded/staffMembers" `
            -ErrorAction Stop

        $Mitarbeiter = $StaffResponse.value |
            Where-Object { $_.emailAddress -ieq $MitarbeiterEmail } |
            Select-Object -First 1

        if ($Mitarbeiter) {
            [PSCustomObject]@{
                Kalender      = $Business.DisplayName
                KalenderEmail = $Business.Id
                Anzeigename   = $Mitarbeiter.displayName
                EMailAdresse  = $Mitarbeiter.emailAddress
                MitarbeiterId = $Mitarbeiter.id
                Ergebnis      = "Noch im Kalender vorhanden"
            }
        }
    }
    catch {
        [PSCustomObject]@{
            Kalender      = $Business.DisplayName
            KalenderEmail = $Business.Id
            Anzeigename   = ""
            EMailAdresse  = $MitarbeiterEmail
            MitarbeiterId = ""
            Ergebnis      = "Nicht prüfbar: $($_.Exception.Message)"
        }
    }
}

$Abschlusspruefung |
    Format-List `
        Kalender,
        KalenderEmail,
        Anzeigename,
        EMailAdresse,
        MitarbeiterId,
        Ergebnis
```

---

# 10. Auswertung der Abschlussprüfung

## Erfolgreich entfernt

Für vollständig entfernte und technisch erreichbare Kalender erscheint kein Eintrag mehr.

## Noch vorhanden

Falls der Mitarbeiter noch in einem Kalender vorhanden ist:

```text
Ergebnis : Noch im Kalender vorhanden
```

## Nicht prüfbar

Einige Bookings-Einträge können über Microsoft Graph mit `404 Not Found` antworten.

Beispiel:

```text
Ergebnis : Nicht prüfbar: Response status code does not indicate success: NotFound (Not Found).
```

Diese Einträge wurden technisch nicht geprüft. Ein `404` beweist nicht, dass der Mitarbeiter dort vorhanden oder nicht vorhanden ist.

In der getesteten Umgebung betraf dies:

- trolleymaker System-Einweisung
- SmartCityCARD & APP DEMO
- LahrCARD | WorkShops
- Smart Country Convention – Simpli-Citycard
- Simpli-Citycard - Adiel Ahmed Munir

---

# 11. Vorgehen bei einem anderen Mitarbeiter

## Schritt 1

PowerShell neu öffnen oder bestehende Graph-Sitzung verwenden.

## Schritt 2

Falls nötig erneut anmelden:

```powershell
Connect-MgGraph `
    -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All" `
    -UseDeviceCode
```

## Schritt 3

In jedem Skript nur die E-Mail-Adresse ändern:

```powershell
$MitarbeiterEmail = "neuer.mitarbeiter@trolleymaker.com"
```

## Schritt 4

Die Schritte in dieser Reihenfolge ausführen:

1. Mitarbeiter suchen.
2. Suchergebnis kontrollieren.
3. Aus allen gefundenen Diensten entfernen.
4. Dienstentfernung kontrollieren.
5. Aus den gefundenen Kalendern entfernen.
6. Abschlussprüfung durchführen.

---

# 12. Wichtige Sicherheitshinweise

- Vor dem Entfernen immer zuerst den Suchlauf ausführen.
- Das Entfernungsskript verwendet die Variablen `$Suchergebnis` und `$Pruefergebnis`.
- Diese Variablen müssen zur aktuell bearbeiteten Person gehören.
- Nicht gleichzeitig mehrere Personen in derselben PowerShell-Sitzung bearbeiten, ohne die Such- und Prüfvariablen neu zu erzeugen.
- Die Mitarbeiter-ID kann je Kalender unterschiedlich sein.
- Dienste werden über `ServiceId` angesprochen.
- Kalender werden über `KalenderEmail` beziehungsweise die Bookings-Business-ID angesprochen.
- Beim Entfernen aus einem Dienst werden die übrigen Mitarbeiter-IDs beibehalten.
- Das Entfernen aus dem Kalender erfolgt erst nach erfolgreicher Prüfung, dass keine Dienstzuordnung mehr besteht.
