<#
.SYNOPSIS
  Prüft FullAccess-Berechtigungen und Bookings-Creator-Policy für einen Zielbenutzer.

.EXAMPLE
  .\Verify-BookingsRights.ps1 -TargetUser "s.schwab@trolleymaker.com"
#>
param(
    [string]$TargetUser = "s.schwab@trolleymaker.com",
    [string]$ExportPath = ".\Bookings-Rights-Verification.csv"
)

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

$localPart = $TargetUser.Split('@')[0]

$results = foreach ($calendar in $bookingCalendars) {
    try {
        $perm = Get-MailboxPermission -Identity $calendar -ErrorAction Stop | Where-Object {
            ($_.User -like "*$TargetUser*" -or $_.User -like "*$localPart*") -and
            $_.AccessRights -contains "FullAccess" -and
            $_.Deny -eq $false
        }

        [PSCustomObject]@{
            Calendar = $calendar
            TargetUser = $TargetUser
            HasFullAccess = [bool]$perm
            UserShown = if ($perm) { ($perm.User -join ';') } else { $null }
            AccessRights = if ($perm) { ($perm.AccessRights -join ';') } else { $null }
            Deny = if ($perm) { ($perm.Deny -join ';') } else { $null }
            IsInherited = if ($perm) { ($perm.IsInherited -join ';') } else { $null }
            Error = $null
        }
    }
    catch {
        [PSCustomObject]@{
            Calendar = $calendar
            TargetUser = $TargetUser
            HasFullAccess = $false
            UserShown = $null
            AccessRights = $null
            Deny = $null
            IsInherited = $null
            Error = $_.Exception.Message
        }
    }
}

$results | Format-Table -AutoSize
$results | Export-Csv -Path $ExportPath -NoTypeInformation -Encoding UTF8

Write-Host "`nCAS-Mailbox / OWA-Policy:" -ForegroundColor Cyan
Get-CASMailbox -Identity $TargetUser | Select-Object DisplayName, PrimarySmtpAddress, OwaMailboxPolicy | Format-Table -AutoSize

Write-Host "`nExportiert nach: $ExportPath" -ForegroundColor Green
