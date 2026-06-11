# Zielbenutzer
$targetUserEmail = "s.schwab@trolleymaker.com"
$targetUserName  = "Simon Schwab"

# Graph-Verbindung herstellen
Disconnect-MgGraph -ErrorAction SilentlyContinue

Connect-MgGraph `
    -Scopes "Bookings.ReadWrite.All","Bookings.Manage.All","User.Read" `
    -UseDeviceCode

Get-MgContext | Format-List Account, TenantId, Scopes

# Bookings-Kalender
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
    "TroCARD@trolleymaker.com",

    # Neue Kalender
    "KlettgauCARD@trolleymaker.com",
    "EmmendingenCARD@trolleymaker.com",
    "OffenburgCARD@trolleymaker.com",
    "TeckCARD@trolleymaker.com"
)

$results = foreach ($bookingId in $bookingCalendars) {
    Write-Host "`nPrüfe $bookingId" -ForegroundColor Cyan

    try {
        $encodedBookingId = [System.Uri]::EscapeDataString($bookingId)

        $staffResponse = Invoke-MgGraphRequest -Method GET `
            -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedBookingId/staffMembers" `
            -Headers @{ "Prefer" = "include-unknown-enum-members" }

        $existingStaff = $staffResponse.value | Where-Object {
            $_.emailAddress -ieq $targetUserEmail -or
            $_.displayName -eq $targetUserName
        }

        if ($existingStaff) {
            foreach ($staff in $existingStaff) {
                $encodedStaffId = [System.Uri]::EscapeDataString($staff.id)

                $body = @{
                    "@odata.type" = "#microsoft.graph.bookingStaffMember"
                    role = "administrator"
                    isEmailNotificationEnabled = $true
                    useBusinessHours = $true
                    timeZone = "Europe/Berlin"
                } | ConvertTo-Json -Depth 10

                Invoke-MgGraphRequest -Method PATCH `
                    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedBookingId/staffMembers/$encodedStaffId" `
                    -Body $body `
                    -ContentType "application/json"

                [PSCustomObject]@{
                    BookingCalendar = $bookingId
                    Action          = "Vorhanden - Rolle auf administrator gesetzt"
                    StaffId         = $staff.id
                    StaffEmail      = $staff.emailAddress
                    Error           = $null
                }
            }
        }
        else {
            $body = @{
                "@odata.type" = "#microsoft.graph.bookingStaffMember"
                displayName = $targetUserName
                emailAddress = $targetUserEmail
                role = "administrator"
                timeZone = "Europe/Berlin"
                useBusinessHours = $true
                isEmailNotificationEnabled = $true
            } | ConvertTo-Json -Depth 10

            $created = Invoke-MgGraphRequest -Method POST `
                -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedBookingId/staffMembers" `
                -Body $body `
                -ContentType "application/json"

            [PSCustomObject]@{
                BookingCalendar = $bookingId
                Action          = "Neu als administrator hinzugefügt"
                StaffId         = $created.id
                StaffEmail      = $created.emailAddress
                Error           = $null
            }
        }
    }
    catch {
        [PSCustomObject]@{
            BookingCalendar = $bookingId
            Action          = "Fehler"
            StaffId         = $null
            StaffEmail      = $targetUserEmail
            Error           = $_.Exception.Message
        }
    }
}

$results | Format-Table -AutoSize
$results | Export-Csv ".\Bookings-Adminrolle-Simon-Schwab.csv" -NoTypeInformation -Encoding UTF8