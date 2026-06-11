<#
.SYNOPSIS
    Entfernt einen Mitarbeiter aus mehreren Microsoft Bookings-Kalendern.

.DESCRIPTION
    Dieses Skript prueft Microsoft Bookings-Kalender ueber Microsoft Graph auf einen bestimmten Mitarbeiter
    und entfernt ihn optional. Fuer den naechsten Einsatz muessen normalerweise nur $UserToRemove und
    $NameToRemove angepasst werden. Standardmaessig laeuft das Skript im CheckOnly-Modus.

.VORAUSSETZUNGEN
    - Microsoft.Graph PowerShell Modul
    - Anmeldung mit einem Benutzer, der Bookings lesen/aendern darf, z. B. serviceadmin@trolleymaker.com
    - Benötigte Graph Scopes: Bookings.ReadWrite.All, Bookings.Manage.All, User.Read
    - Der Serviceadmin muss auf die jeweiligen Bookings-Mailboxen berechtigt sein bzw. in Bookings als Admin funktionieren.

.AUSFUEHRUNG
    1. PowerShell als Admin/User starten.
    2. Connect-MgGraph -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All","User.Read" -UseDeviceCode
    3. In diesem Skript $UserToRemove und $NameToRemove anpassen.
    4. Erst mit $Mode = "CheckOnly" ausfuehren.
    5. Wenn die Treffer passen, $Mode = "Delete" setzen und erneut ausfuehren.
#>

# =========================
# ANPASSEN FUER DEN EINSATZ
# =========================
$UserToRemove = "m.seidel@trolleymaker.com"
$NameToRemove = "Michael Seidel"

# Moegliche Werte: "CheckOnly" oder "Delete"
$Mode = "CheckOnly"

# Optional: Ausgabeordner
$OutputFolder = "."

# =========================
# BOOKING-KALENDER
# =========================
# Hinweis:
# DisplayName ist nur fuer die Ausgabe.
# GraphId ist der Identifier, der fuer Microsoft Graph verwendet wird.
# Einige Kalender haben abweichende PrimarySmtpAddress/Alias-Werte.

$BookingCalendars = @(
    [PSCustomObject]@{ DisplayName = "Pfulbencard"; GraphId = "pfulbencard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "Ettenheimcard"; GraphId = "ettenheimcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "Schwabachcard"; GraphId = "schwabachcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "LE Card"; GraphId = "lecard@trolleymaker.com"; Notes = "Korrigiert von le-card" },
    [PSCustomObject]@{ DisplayName = "Henstedt-Ulzburg SmartCARD"; GraphId = "HenstedtUlzburgSmartCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Foerdecard"; GraphId = "foerdecard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "BalingenCARD"; GraphId = "BalingenCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "HCard"; GraphId = "hcard@trolleymaker.com"; Notes = "Korrigiert von huecard" },
    [PSCustomObject]@{ DisplayName = "WuermtalCARD"; GraphId = "WuermtalCARD@trolleymaker.com"; Notes = "Funktioniert mit URL-Encoding; zuletzt Mitarbeiter gefunden" },
    [PSCustomObject]@{ DisplayName = "DETTINGEN ERMSCARD"; GraphId = "DETTINGENERMSCARD@trolleymaker.com"; Notes = "Sonderfall: Graph lieferte 404/500; ggf. manuell pruefen" },
    [PSCustomObject]@{ DisplayName = "HerbolzheimKarte"; GraphId = "HerbolzheimKarte@trolleymaker.com"; Notes = "Korrigiert von herbolzheim-card; zuletzt Mitarbeiter gefunden" },
    [PSCustomObject]@{ DisplayName = "Landshutcard"; GraphId = "landshutcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "Lahrcard"; GraphId = "lahrcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "CalwCARD"; GraphId = "CALWCARDAPP@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Erlebnisregion Europa-Park CARD"; GraphId = "ErlebnisregionEuropaParkCARD@trolleymaker.com"; Notes = "Graph funktioniert mit PrimarySmtpAddress; Alias/Kopie: ErlebnisregionEuropaParkCARDKopie" },
    [PSCustomObject]@{ DisplayName = "KenzingenCARD"; GraphId = "KenzingenCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Wuncard"; GraphId = "wuncard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "NeubulachCARD"; GraphId = "NeubulachCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Besigheimcard"; GraphId = "besigheimcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "AbensbergCARD"; GraphId = "AbensbergCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Viertaelercard"; GraphId = "viertaelercard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "NeuriedCARD"; GraphId = "NeuriedCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Haslachcard"; GraphId = "haslachcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "Echt Freiburg Card"; GraphId = "echtfreiburgcard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "SR CARD"; GraphId = "SRCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Baden-Baden CARD"; GraphId = "BadenBadenCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" },
    [PSCustomObject]@{ DisplayName = "Bad Waldsee Citycard"; GraphId = "badwaldseecitycard@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "0711card"; GraphId = "0711card@trolleymaker.com"; Notes = "OK" },
    [PSCustomObject]@{ DisplayName = "TroCARD"; GraphId = "TroCARD@trolleymaker.com"; Notes = "Zuletzt keine funktionierende Graph-ID gefunden" }
)

# =========================
# FUNKTIONEN
# =========================
function Get-BookingStaffMembers {
    param(
        [Parameter(Mandatory = $true)] [string] $GraphId
    )

    # In den meisten erfolgreichen Faellen funktionierte URL-Encoding fuer E-Mail-Adressen.
    $encodedGraphId = [System.Uri]::EscapeDataString($GraphId)
    $uri = "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedGraphId/staffMembers"
    return Invoke-MgGraphRequest -Method GET -Uri $uri
}

function Remove-BookingStaffMember {
    param(
        [Parameter(Mandatory = $true)] [string] $GraphId,
        [Parameter(Mandatory = $true)] [string] $StaffId
    )

    $encodedGraphId = [System.Uri]::EscapeDataString($GraphId)
    $encodedStaffId = [System.Uri]::EscapeDataString($StaffId)
    $uri = "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedGraphId/staffMembers/$encodedStaffId"
    Invoke-MgGraphRequest -Method DELETE -Uri $uri
}

# =========================
# VORABPRUEFUNG
# =========================
Write-Host "Modus: $Mode" -ForegroundColor Yellow
Write-Host "Zu entfernender Mitarbeiter: $NameToRemove / $UserToRemove" -ForegroundColor Yellow

$ctx = Get-MgContext -ErrorAction SilentlyContinue
if (-not $ctx) {
    Write-Warning "Keine aktive Microsoft Graph Verbindung gefunden. Bitte zuerst Connect-MgGraph ausfuehren."
    Write-Host 'Connect-MgGraph -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All","User.Read" -UseDeviceCode'
    return
}

Write-Host "Graph Account: $($ctx.Account)" -ForegroundColor Yellow

# =========================
# PRUEFEN / LOESCHEN
# =========================
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$ResultsPath = Join-Path $OutputFolder "Bookings-Staff-Pruefung-$timestamp.csv"

$Results = foreach ($calendar in $BookingCalendars) {
    Write-Host "`nPruefe $($calendar.DisplayName) [$($calendar.GraphId)]" -ForegroundColor Cyan

    try {
        $staffResponse = Get-BookingStaffMembers -GraphId $calendar.GraphId

        $matches = $staffResponse.value | Where-Object {
            $_.emailAddress -ieq $UserToRemove -or $_.displayName -eq $NameToRemove
        }

        if (-not $matches) {
            Write-Host "Nicht gefunden" -ForegroundColor Green
            [PSCustomObject]@{
                DisplayName = $calendar.DisplayName
                GraphId = $calendar.GraphId
                Status = "Nicht gefunden"
                StaffName = $null
                StaffEmail = $null
                StaffId = $null
                Action = "Keine Aktion"
                Error = $null
                Notes = $calendar.Notes
            }
            continue
        }

        foreach ($staff in $matches) {
            $action = "Nur gefunden"
            $errorMessage = $null

            if ($Mode -eq "Delete") {
                try {
                    Remove-BookingStaffMember -GraphId $calendar.GraphId -StaffId $staff.id
                    $action = "Geloescht"
                    Write-Host "Geloescht: $($staff.displayName) / $($staff.emailAddress)" -ForegroundColor Green
                }
                catch {
                    $action = "Loeschen fehlgeschlagen"
                    $errorMessage = $_.Exception.Message
                    Write-Warning "Loeschen fehlgeschlagen: $errorMessage"
                }
            }
            else {
                Write-Host "Gefunden: $($staff.displayName) / $($staff.emailAddress) / $($staff.id)" -ForegroundColor Red
            }

            [PSCustomObject]@{
                DisplayName = $calendar.DisplayName
                GraphId = $calendar.GraphId
                Status = "Gefunden"
                StaffName = $staff.displayName
                StaffEmail = $staff.emailAddress
                StaffId = $staff.id
                Action = $action
                Error = $errorMessage
                Notes = $calendar.Notes
            }
        }
    }
    catch {
        Write-Warning "Fehler: $($_.Exception.Message)"
        [PSCustomObject]@{
            DisplayName = $calendar.DisplayName
            GraphId = $calendar.GraphId
            Status = "Fehler"
            StaffName = $null
            StaffEmail = $null
            StaffId = $null
            Action = "Nicht verarbeitet"
            Error = $_.Exception.Message
            Notes = $calendar.Notes
        }
    }
}

$Results | Format-Table DisplayName, GraphId, Status, StaffName, StaffEmail, Action -AutoSize
$Results | Export-Csv -Path $ResultsPath -NoTypeInformation -Encoding UTF8
Write-Host "`nCSV exportiert: $ResultsPath" -ForegroundColor Yellow

$Remaining = $Results | Where-Object { $_.Status -eq "Gefunden" -and $_.Action -ne "Geloescht" }
$Errors = $Results | Where-Object { $_.Status -eq "Fehler" }

if ($Remaining) {
    Write-Host "`nNoch gefundene Mitarbeiter-Eintraege:" -ForegroundColor Red
    $Remaining | Select-Object DisplayName, GraphId, StaffName, StaffEmail, StaffId | Format-Table -AutoSize
}

if ($Errors) {
    Write-Host "`nKalender mit Fehlern:" -ForegroundColor Yellow
    $Errors | Select-Object DisplayName, GraphId, Error, Notes | Format-Table -AutoSize
}
