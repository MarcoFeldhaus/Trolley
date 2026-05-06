$bookingId = "DETTINGENERMSCARD@trolleymaker.com"
$userToRemove = "m.seidel@trolleymaker.com"

$encodedBookingId = [System.Uri]::EscapeDataString($bookingId)

$staffResponse = Invoke-MgGraphRequest -Method GET `
    -Uri "https://graph.microsoft.com/v1.0/solutions/bookingBusinesses/$encodedBookingId/staffMembers"

$staffResponse.value |
    Where-Object {
        $_.emailAddress -ieq $userToRemove -or
        $_.displayName -like "*Michael Seidel*"
    } |
    Select-Object id, displayName, emailAddress |
    Format-List