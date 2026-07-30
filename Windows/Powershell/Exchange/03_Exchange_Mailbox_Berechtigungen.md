# Exchange Online: Mailbox-Berechtigungen prüfen, vergeben und verifizieren

## Zweck

Diese Dokumentation behandelt `FullAccess`-Berechtigungen auf Exchange- und Bookings-/Scheduling-Mailboxen.

---

# 1. An Exchange Online anmelden

```powershell
$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com"

Connect-ExchangeOnline `
    -UserPrincipalName $AdminUserPrincipalName `
    -Device
```

---

# 2. Berechtigung eines bestimmten Benutzers auf einer Mailbox prüfen

```powershell
$Mailbox    = "support@trolleymaker.com"
$TargetUser = "s.schwab@trolleymaker.com"
$LocalPart  = $TargetUser.Split("@")[0]

Get-MailboxPermission -Identity $Mailbox |
    Where-Object {
        $_.User -like "*$TargetUser*" -or
        $_.User -like "*$LocalPart*"
    } |
    Format-Table `
        User,
        AccessRights,
        IsInherited,
        Deny `
        -AutoSize
```

---

# 3. FullAccess auf eine einzelne Mailbox vergeben

```powershell
$Mailbox    = "support@trolleymaker.com"
$TargetUser = "s.schwab@trolleymaker.com"

Add-MailboxPermission `
    -Identity $Mailbox `
    -User $TargetUser `
    -AccessRights FullAccess `
    -InheritanceType All `
    -AutoMapping $false
```

---

# 4. FullAccess auf mehrere Bookings-Mailboxen vergeben

## Beschreibung

Dieses Skript:

- prüft das ExchangeOnlineManagement-Modul,
- verbindet sich bei Bedarf mit Exchange Online,
- prüft vorhandene Rechte,
- setzt fehlendes `FullAccess`,
- verhindert doppeltes Setzen,
- exportiert das Ergebnis als CSV.

```powershell
param(
    [string]$TargetUser = "s.schwab@trolleymaker.com",
    [string]$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com",
    [string]$ExportPath = ".\Bookings-MailboxPermissions-Result.csv"
)

$ErrorActionPreference = "Stop"

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
    "TroCARD@trolleymaker.com"
)

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

$Results = foreach ($Calendar in $BookingCalendars) {
    Write-Host "`nBearbeite $Calendar" -ForegroundColor Cyan

    try {
        $Existing = Get-MailboxPermission `
            -Identity $Calendar `
            -ErrorAction Stop |
            Where-Object {
                $_.User -like $TargetUser -and
                $_.AccessRights -contains "FullAccess" -and
                $_.Deny -eq $false
            }

        if ($Existing) {
            [PSCustomObject]@{
                Calendar   = $Calendar
                TargetUser = $TargetUser
                Action     = "Skipped"
                Status     = "FullAccess bereits vorhanden"
                Error      = $null
            }

            continue
        }

        Add-MailboxPermission `
            -Identity $Calendar `
            -User $TargetUser `
            -AccessRights FullAccess `
            -InheritanceType All `
            -AutoMapping $false `
            -ErrorAction Stop

        [PSCustomObject]@{
            Calendar   = $Calendar
            TargetUser = $TargetUser
            Action     = "Added"
            Status     = "FullAccess gesetzt"
            Error      = $null
        }
    }
    catch {
        [PSCustomObject]@{
            Calendar   = $Calendar
            TargetUser = $TargetUser
            Action     = "Error"
            Status     = "Fehler"
            Error      = $_.Exception.Message
        }
    }
}

$Results | Format-Table -AutoSize

$Results |
    Export-Csv `
        -Path $ExportPath `
        -NoTypeInformation `
        -Encoding UTF8

Write-Host "`nErgebnis exportiert nach: $ExportPath" -ForegroundColor Green
```

---

# 5. FullAccess auf allen definierten Kalendern verifizieren

```powershell
param(
    [string]$TargetUser = "s.schwab@trolleymaker.com",
    [string]$ExportPath = ".\Bookings-Rights-Verification.csv"
)

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
    "TroCARD@trolleymaker.com"
)

$LocalPart = $TargetUser.Split("@")[0]

$Results = foreach ($Calendar in $BookingCalendars) {
    try {
        $Permission = Get-MailboxPermission `
            -Identity $Calendar `
            -ErrorAction Stop |
            Where-Object {
                (
                    $_.User -like "*$TargetUser*" -or
                    $_.User -like "*$LocalPart*"
                ) -and
                $_.AccessRights -contains "FullAccess" -and
                $_.Deny -eq $false
            }

        [PSCustomObject]@{
            Calendar      = $Calendar
            TargetUser    = $TargetUser
            HasFullAccess = [bool]$Permission
            UserShown     = if ($Permission) {
                $Permission.User -join ";"
            }
            else {
                $null
            }
            AccessRights  = if ($Permission) {
                $Permission.AccessRights -join ";"
            }
            else {
                $null
            }
            Deny          = if ($Permission) {
                $Permission.Deny -join ";"
            }
            else {
                $null
            }
            IsInherited   = if ($Permission) {
                $Permission.IsInherited -join ";"
            }
            else {
                $null
            }
            Error         = $null
        }
    }
    catch {
        [PSCustomObject]@{
            Calendar      = $Calendar
            TargetUser    = $TargetUser
            HasFullAccess = $false
            UserShown     = $null
            AccessRights  = $null
            Deny          = $null
            IsInherited   = $null
            Error         = $_.Exception.Message
        }
    }
}

$Results | Format-Table -AutoSize

$Results |
    Export-Csv `
        -Path $ExportPath `
        -NoTypeInformation `
        -Encoding UTF8

Write-Host "`nCAS-Mailbox / OWA-Policy:" -ForegroundColor Cyan

Get-CASMailbox -Identity $TargetUser |
    Select-Object `
        DisplayName,
        PrimarySmtpAddress,
        OwaMailboxPolicy |
    Format-Table -AutoSize

Write-Host "`nExportiert nach: $ExportPath" -ForegroundColor Green
```

---

# 6. Wichtig: Exchange-FullAccess ist nicht gleich Bookings-Rolle

`Add-MailboxPermission -AccessRights FullAccess` gewährt Zugriff auf die Exchange-Mailbox.

Die Rolle innerhalb von Microsoft Bookings, etwa:

```text
administrator
scheduler
member
```

wird separat über Microsoft Graph beziehungsweise die Bookings-Oberfläche verwaltet.
