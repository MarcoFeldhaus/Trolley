# Microsoft Bookings: Creator-Policy, Mailboxrechte und Administratorrollen

## Zweck

Diese Dokumentation fasst die bereitgestellten Bookings-Skripte zusammen.

Dabei sind drei getrennte Berechtigungsebenen zu unterscheiden:

1. Exchange-Mailboxrecht, zum Beispiel `FullAccess`
2. OWA-Mailbox-Policy zur Erstellung neuer Bookings-Kalender
3. Bookings-Mitarbeiterrolle, zum Beispiel `administrator`

---

# Teil A: Bookings-Creator-Policy über Exchange Online

## 1. An Exchange Online anmelden

```powershell
$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com"

Connect-ExchangeOnline `
    -UserPrincipalName $AdminUserPrincipalName `
    -Device
```

---

## 2. OWA-Mailbox-Policy erstellen oder aktualisieren

## Beschreibung

Dieses Skript:

- erstellt bei Bedarf die Policy `BookingsCreators`,
- aktiviert `BookingsMailboxCreationEnabled`,
- weist die Policy dem Zielbenutzer zu,
- kontrolliert Benutzer und Policy.

```powershell
param(
    [string]$TargetUser = "s.schwab@trolleymaker.com",
    [string]$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com",
    [string]$PolicyName = "BookingsCreators"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Module -ListAvailable -Name ExchangeOnlineManagement)) {
    Install-Module ExchangeOnlineManagement -Scope CurrentUser -Force
}

Import-Module ExchangeOnlineManagement

$Connected = $false

try {
    $Connected = (
        Get-ConnectionInformation -ErrorAction SilentlyContinue |
        Where-Object {
            $_.State -eq "Connected" -and
            $_.UserPrincipalName -eq $AdminUserPrincipalName
        }
    ) -ne $null
}
catch {
    $Connected = $false
}

if (-not $Connected) {
    Connect-ExchangeOnline `
        -UserPrincipalName $AdminUserPrincipalName `
        -Device
}

$Policy = Get-OwaMailboxPolicy `
    -Identity $PolicyName `
    -ErrorAction SilentlyContinue

if (-not $Policy) {
    Write-Host "Erstelle OWA-Mailbox-Policy: $PolicyName" -ForegroundColor Cyan
    New-OwaMailboxPolicy -Name $PolicyName | Out-Null
}
else {
    Write-Host "OWA-Mailbox-Policy existiert bereits: $PolicyName" -ForegroundColor Yellow
}

Set-OwaMailboxPolicy `
    -Identity $PolicyName `
    -BookingsMailboxCreationEnabled:$true

Set-CASMailbox `
    -Identity $TargetUser `
    -OwaMailboxPolicy $PolicyName

Write-Host "`nPrüfung Benutzer:" -ForegroundColor Cyan

Get-CASMailbox -Identity $TargetUser |
    Select-Object `
        DisplayName,
        PrimarySmtpAddress,
        OwaMailboxPolicy |
    Format-Table -AutoSize

Write-Host "`nPrüfung Policy:" -ForegroundColor Cyan

Get-OwaMailboxPolicy -Identity $PolicyName |
    Format-List `
        Name,
        BookingsMailboxCreationEnabled
```

---

# Teil B: Mitarbeiterrolle in bestehenden Bookings-Kalendern

## 3. An Microsoft Graph per Device Code anmelden

Exchange Online und Microsoft Graph sind getrennte Verbindungen.

```powershell
Disconnect-MgGraph -ErrorAction SilentlyContinue

Connect-MgGraph `
    -Scopes `
        "Bookings.ReadWrite.All",
        "Bookings.Manage.All",
        "User.Read" `
    -UseDeviceCode
```

---

## 4. Graph-Anmeldung kontrollieren

```powershell
Get-MgContext |
    Format-List `
        Account,
        TenantId,
        AuthType,
        TokenCredentialType,
        Scopes
```

---

## 5. Benutzer in definierten Bookings-Kalendern als Administrator setzen

## Beschreibung

Dieses Skript:

- sucht den Benutzer anhand E-Mail-Adresse oder Anzeigename,
- setzt vorhandene Einträge auf `administrator`,
- fügt fehlende Einträge als `administrator` hinzu,
- gibt Kalender, E-Mail-Adresse, ID und Aktion aus,
- exportiert das Ergebnis als CSV.

Vor der Ausführung anpassen:

```powershell
$TargetUserEmail = "s.schwab@trolleymaker.com"
$TargetUserName  = "Simon Schwab"
```

## Befehl

```powershell
$TargetUserEmail = "s.schwab@trolleymaker.com"
$TargetUserName  = "Simon Schwab"

$BookingCalendars = @(
    "pfulbencard@trolleymaker.com",
    "ettenheimcard@trolleymaker.com",
    "schwabachcard@trolleymaker.com",
    "lecard@trolleymaker.com",
    "HenstedtUlzburgSmartCARD@trolleymaker.com",
    "foerdecard@trolleymaker.com",
    "BalingenCARD@trolleymaker.com",
    "hcard@trolleymaker.com",
    "WuermtalCARD@trolleymaker.com",
    "DETTINGENERMSCARD@trolleymaker.com",
    "HerbolzheimKarte@trolleymaker.com",
    "landshutcard@trolleymaker.com",
    "lahrcard@trolleymaker.com",
    "CALWCARDAPP@trolleymaker.com",
    "ErlebnisregionEuropaParkCARD@trolleymaker.com",
    "KenzingenCARD@trolleymaker.com",
    "wuncard@trolleymaker.com",
    "NeubulachCARD@trolleymaker.com",
    "besigheimcard@trolleymaker.com",
    "AbensbergCARD@trolleymaker.com",
    "viertaelercard@trolleymaker.com",
    "NeuriedCARD@trolleymaker.com",
    "haslachcard@trolleymaker.com",
    "echtfreiburgcard@trolleymaker.com",
    "SRCARD@trolleymaker.com",
    "BadenBadenCARD@trolleymaker.com",
    "badwaldseecitycard@trolleymaker.com",
    "0711card@trolleymaker.com",
    "TroCARD@trolleymaker.com",
    "KlettgauCARD@trolleymaker.com",
    "EmmendingenCARD@trolleymaker.com",
    "OffenburgCARD@trolleymaker.com",
    "TeckCARD@trolleymaker.com"
)

$Results = foreach ($BookingId in $BookingCalendars) {
    Write-Host "`nPrüfe $BookingId" -ForegroundColor Cyan

    try {
        $EncodedBookingId = [System.Uri]::EscapeDataString($BookingId)

        $StaffResponse = Invoke-MgGraphRequest `
            -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$EncodedBookingId/staffMembers" `
            -Headers @{
                Prefer = "include-unknown-enum-members"
            }

        $ExistingStaff = $StaffResponse.value |
            Where-Object {
                $_.emailAddress -ieq $TargetUserEmail -or
                $_.displayName -eq $TargetUserName
            }

        if ($ExistingStaff) {
            foreach ($Staff in $ExistingStaff) {
                $EncodedStaffId = [System.Uri]::EscapeDataString($Staff.id)

                $Body = @{
                    "@odata.type" = "#microsoft.graph.bookingStaffMember"
                    role = "administrator"
                    isEmailNotificationEnabled = $true
                    useBusinessHours = $true
                    timeZone = "Europe/Berlin"
                } |
                    ConvertTo-Json -Depth 10

                Invoke-MgGraphRequest `
                    -Method PATCH `
                    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$EncodedBookingId/staffMembers/$EncodedStaffId" `
                    -Body $Body `
                    -ContentType "application/json" |
                    Out-Null

                [PSCustomObject]@{
                    BookingCalendar = $BookingId
                    DisplayName     = $Staff.displayName
                    StaffEmail      = $Staff.emailAddress
                    StaffId         = $Staff.id
                    Action          = "Vorhanden - Rolle auf administrator gesetzt"
                    Error           = $null
                }
            }
        }
        else {
            $Body = @{
                "@odata.type" = "#microsoft.graph.bookingStaffMember"
                displayName = $TargetUserName
                emailAddress = $TargetUserEmail
                role = "administrator"
                timeZone = "Europe/Berlin"
                useBusinessHours = $true
                isEmailNotificationEnabled = $true
            } |
                ConvertTo-Json -Depth 10

            $Created = Invoke-MgGraphRequest `
                -Method POST `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$EncodedBookingId/staffMembers" `
                -Body $Body `
                -ContentType "application/json"

            [PSCustomObject]@{
                BookingCalendar = $BookingId
                DisplayName     = $Created.displayName
                StaffEmail      = $Created.emailAddress
                StaffId         = $Created.id
                Action          = "Neu als administrator hinzugefügt"
                Error           = $null
            }
        }
    }
    catch {
        [PSCustomObject]@{
            BookingCalendar = $BookingId
            DisplayName     = $TargetUserName
            StaffEmail      = $TargetUserEmail
            StaffId         = $null
            Action          = "Fehler"
            Error           = $_.Exception.Message
        }
    }
}

$Results |
    Format-List `
        BookingCalendar,
        DisplayName,
        StaffEmail,
        StaffId,
        Action,
        Error

$Results |
    Export-Csv `
        -Path ".\Bookings-Adminrolle-Ergebnis.csv" `
        -NoTypeInformation `
        -Encoding UTF8
```

---

# Teil C: Rechte und Rollen unterscheiden

## Exchange FullAccess

```powershell
Add-MailboxPermission
```

Damit kann ein Benutzer auf die Exchange-/Scheduling-Mailbox zugreifen.

## Bookings-Creator-Policy

```powershell
Set-OwaMailboxPolicy -BookingsMailboxCreationEnabled:$true
```

Damit wird die Erstellung neuer Bookings-Mailboxen über die zugewiesene OWA-Policy ermöglicht.

## Bookings-Administratorrolle

```powershell
role = "administrator"
```

Diese Rolle wird innerhalb des jeweiligen Bookings-Kalenders über Microsoft Graph gesetzt.

Eine Berechtigung ersetzt die anderen Ebenen nicht automatisch.
