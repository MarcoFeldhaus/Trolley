<#
.SYNOPSIS
  Vergibt FullAccess auf alle definierten Microsoft Bookings-/Scheduling-Mailboxen.

.DESCRIPTION
  Dieses Script wird von einem Exchange-/Microsoft-365-Admin ausgeführt.
  Der Zielbenutzer kann danach bestehende Bookings-Kalender im Kontext der
  jeweiligen Scheduling-Mailbox bearbeiten. Falls die Bookings-Weboberfläche
  für einzelne Kalender trotzdem nicht alle Funktionen anzeigt, muss der
  Benutzer in Bookings selbst zusätzlich als Administrator eingetragen werden.

.PARAMETER TargetUser
  Konto, das berechtigt werden soll.

.PARAMETER AdminUserPrincipalName
  Admin-Konto für Connect-ExchangeOnline.

.PARAMETER ExportPath
  Pfad für die Ergebnis-CSV.

.EXAMPLE
  .\Grant-BookingsMailboxPermissions.ps1 -TargetUser "s.schwab@trolleymaker.com" -AdminUserPrincipalName "m.feldhaus@trolleymaker.com"
#>
param(
    [string]$TargetUser = "s.schwab@trolleymaker.com",
    [string]$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com",
    [string]$ExportPath = ".\Bookings-MailboxPermissions-Result.csv"
)

$ErrorActionPreference = "Stop"

$bookingCalendars = @(
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

$connected = $false
try {
    $connected = (Get-ConnectionInformation -ErrorAction SilentlyContinue | Where-Object {
        $_.State -eq "Connected" -and $_.UserPrincipalName -eq $AdminUserPrincipalName
    }) -ne $null
}
catch {
    $connected = $false
}

if (-not $connected) {
    Connect-ExchangeOnline -UserPrincipalName $AdminUserPrincipalName
}

$results = foreach ($calendar in $bookingCalendars) {
    Write-Host "`nBearbeite $calendar" -ForegroundColor Cyan

    try {
        $existing = Get-MailboxPermission -Identity $calendar -ErrorAction Stop | Where-Object {
            $_.User -like $TargetUser -and
            $_.AccessRights -contains "FullAccess" -and
            $_.Deny -eq $false
        }

        if ($existing) {
            Write-Host "Bereits vorhanden: $TargetUser auf $calendar" -ForegroundColor Yellow
            [PSCustomObject]@{
                Calendar = $calendar
                TargetUser = $TargetUser
                Action = "Skipped"
                Status = "FullAccess bereits vorhanden"
                Error = $null
            }
            continue
        }

        Add-MailboxPermission `
            -Identity $calendar `
            -User $TargetUser `
            -AccessRights FullAccess `
            -InheritanceType All `
            -AutoMapping $false `
            -ErrorAction Stop

        Write-Host "Berechtigt: $TargetUser auf $calendar" -ForegroundColor Green
        [PSCustomObject]@{
            Calendar = $calendar
            TargetUser = $TargetUser
            Action = "Added"
            Status = "FullAccess gesetzt"
            Error = $null
        }
    }
    catch {
        Write-Warning "Fehler bei $calendar : $($_.Exception.Message)"
        [PSCustomObject]@{
            Calendar = $calendar
            TargetUser = $TargetUser
            Action = "Error"
            Status = "Fehler"
            Error = $_.Exception.Message
        }
    }
}

$results | Format-Table -AutoSize
$results | Export-Csv -Path $ExportPath -NoTypeInformation -Encoding UTF8
Write-Host "`nErgebnis exportiert nach: $ExportPath" -ForegroundColor Green
