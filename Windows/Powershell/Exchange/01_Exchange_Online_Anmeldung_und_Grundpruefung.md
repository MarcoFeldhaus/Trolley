# Exchange Online: Anmeldung und Grundprüfung

## Zweck

Diese Dokumentation beschreibt die Anmeldung an Exchange Online PowerShell und grundlegende Prüfungen für Mailboxen und Managementrollen.

---

## 1. ExchangeOnlineManagement-Modul installieren

Dieser Schritt ist nur erforderlich, wenn das Modul noch nicht installiert ist.

```powershell
if (-not (Get-Module -ListAvailable -Name ExchangeOnlineManagement)) {
    Install-Module ExchangeOnlineManagement -Scope CurrentUser -Force
}
```

## 2. Modul importieren

```powershell
Import-Module ExchangeOnlineManagement
```

---

## 3. Anmeldung per Device Code

### Beschreibung

Die Anmeldung erfolgt mit dem angegebenen Administratorkonto über den Device-Code-Flow.

```powershell
$AdminUserPrincipalName = "serviceadmin@trolleymaker.com"

Connect-ExchangeOnline `
    -UserPrincipalName $AdminUserPrincipalName `
    -Device
```

Nach dem Aufruf:

1. die angezeigte Microsoft-Anmeldeseite öffnen,
2. den Device Code eingeben,
3. mit dem angegebenen Administratorkonto anmelden.

---

## 4. Exchange-Verbindung kontrollieren

```powershell
Get-ConnectionInformation |
    Format-List `
        UserPrincipalName,
        State,
        ConnectionId,
        ModuleName,
        TokenStatus
```

Erwartet wird unter anderem:

```text
State : Connected
```

---

## 5. Nur bei Bedarf verbinden

Dieser Block prüft, ob bereits eine passende Exchange-Verbindung besteht.

```powershell
$AdminUserPrincipalName = "m.feldhaus@trolleymaker.com"

$connected = $false

try {
    $connected = (
        Get-ConnectionInformation -ErrorAction SilentlyContinue |
        Where-Object {
            $_.State -eq "Connected" -and
            $_.UserPrincipalName -eq $AdminUserPrincipalName
        }
    ) -ne $null
}
catch {
    $connected = $false
}

if (-not $connected) {
    Connect-ExchangeOnline `
        -UserPrincipalName $AdminUserPrincipalName `
        -Device
}
```

---

## 6. Exchange-Verbindung trennen

```powershell
Disconnect-ExchangeOnline -Confirm:$false
```

---

# Mailbox-Grunddaten prüfen

## 7. Stammdaten einer Mailbox anzeigen

```powershell
$Mailbox = "support@trolleymaker.com"

Get-Mailbox -Identity $Mailbox |
    Format-List `
        DisplayName,
        Alias,
        PrimarySmtpAddress,
        EmailAddresses,
        GrantSendOnBehalfTo
```

### Angezeigte Informationen

- Anzeigename
- Alias
- primäre SMTP-Adresse
- weitere E-Mail-Adressen
- Benutzer mit „Senden im Auftrag von“-Berechtigung

---

# Managementrollen prüfen

## 8. Rollen mit Bezug zu IMAP oder Anwendungen anzeigen

```powershell
Get-ManagementRoleAssignment |
    Where-Object {
        $_.Role -like "*IMAP*" -or
        $_.Role -like "*Application*"
    } |
    Format-List `
        Name,
        Role,
        App,
        CustomResourceScope,
        RecipientWriteScope
```

Dieser Befehl verändert nichts. Er zeigt passende Managementrollenzuweisungen an.

---

# Kurzprüfung der verwendeten Konten

```powershell
Write-Host "Exchange-Admin: $AdminUserPrincipalName" -ForegroundColor Cyan
Write-Host "Mailbox:        $Mailbox" -ForegroundColor Cyan
```
