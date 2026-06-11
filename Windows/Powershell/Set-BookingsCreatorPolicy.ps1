<#
.SYNOPSIS
  Richtet eine OWA-Mailbox-Policy ein, mit der ein Benutzer neue Microsoft Bookings-Kalender erstellen kann.

.DESCRIPTION
  Dieses Script erstellt bei Bedarf die Policy "BookingsCreators", aktiviert
  BookingsMailboxCreationEnabled und weist sie dem Zielbenutzer zu.
  Der Benutzer muss trotzdem eine passende Microsoft-365-Lizenz mit Bookings besitzen.

.PARAMETER TargetUser
  Konto, das neue Bookings-Kalender über die Weboberfläche erstellen/klonen soll.

.PARAMETER AdminUserPrincipalName
  Admin-Konto für Connect-ExchangeOnline.

.PARAMETER PolicyName
  Name der OWA-Mailbox-Policy.

.EXAMPLE
  .\Set-BookingsCreatorPolicy.ps1 -TargetUser "s.schwab@trolleymaker.com" -AdminUserPrincipalName "m.feldhaus@trolleymaker.com"
#>
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

$policy = Get-OwaMailboxPolicy -Identity $PolicyName -ErrorAction SilentlyContinue

if (-not $policy) {
    Write-Host "Erstelle OWA-Mailbox-Policy: $PolicyName" -ForegroundColor Cyan
    New-OwaMailboxPolicy -Name $PolicyName | Out-Null
}
else {
    Write-Host "OWA-Mailbox-Policy existiert bereits: $PolicyName" -ForegroundColor Yellow
}

Set-OwaMailboxPolicy -Identity $PolicyName -BookingsMailboxCreationEnabled:$true

Set-CASMailbox -Identity $TargetUser -OwaMailboxPolicy $PolicyName

Write-Host "`nPrüfung Benutzer:" -ForegroundColor Cyan
Get-CASMailbox -Identity $TargetUser | Select-Object DisplayName, PrimarySmtpAddress, OwaMailboxPolicy | Format-Table -AutoSize

Write-Host "`nPrüfung Policy:" -ForegroundColor Cyan
Get-OwaMailboxPolicy -Identity $PolicyName | Format-List Name, BookingsMailboxCreationEnabled
