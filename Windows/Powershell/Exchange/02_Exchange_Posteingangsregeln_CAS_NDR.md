# Exchange Online: Posteingangsregeln für CAS und NDR-Nachrichten

## Zweck

Diese Dokumentation beschreibt die Verwaltung einer Exchange-Posteingangsregel, die Zustellfehler, NDRs und weitere unerwünschte Systemnachrichten in den Ordner `CAS_IGNORE` verschiebt.

Beispiel:

```text
Mailbox: support@trolleymaker.com
Regel:   CAS - NDRs nicht importieren
Ordner:  CAS_IGNORE
```

---

# 1. An Exchange Online anmelden

```powershell
$AdminUserPrincipalName = "serviceadmin@trolleymaker.com"

Connect-ExchangeOnline `
    -UserPrincipalName $AdminUserPrincipalName `
    -Device
```

---

# 2. Alle Posteingangsregeln einer Mailbox anzeigen

## Beschreibung

Dieser Befehl zeigt Name, Status, Priorität, Bedingungen und Aktionen aller Regeln.

```powershell
$Mailbox = "support@trolleymaker.com"

Get-InboxRule -Mailbox $Mailbox |
    Format-List `
        Name,
        Enabled,
        Priority,
        Description,
        From,
        SentTo,
        SubjectContainsWords,
        MoveToFolder,
        ForwardTo,
        RedirectTo,
        DeleteMessage,
        MarkAsRead,
        StopProcessingRules
```

---

# 3. Regel neu erstellen

## Beschreibung

Die Regel verschiebt Nachrichten mit bestimmten Begriffen im Betreff in den Ordner `CAS_IGNORE` und beendet anschließend die weitere Regelverarbeitung.

Der Zielordner muss bereits existieren.

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

New-InboxRule `
    -Mailbox $Mailbox `
    -Name $RuleName `
    -SubjectContainsWords @(
        "Undeliverable",
        "Unzustellbar",
        "Nicht zustellbar",
        "Delivery Status Notification",
        "Mail delivery failed",
        "Delivery has failed",
        "Returned mail",
        "failure notice",
        "Delivery delayed"
    ) `
    -MoveToFolder "$Mailbox`:\CAS_IGNORE" `
    -StopProcessingRules $true
```

---

# 4. Aktuelle Betreffbegriffe einer Regel anzeigen

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

$Rule = Get-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName

$Rule.SubjectContainsWords
```

---

# 5. Einen einzelnen Betreffbegriff ergänzen

## Beschreibung

Dieser Block erhält alle vorhandenen Begriffe und ergänzt einen neuen Wert ohne Duplikate.

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"
$NewWord  = "Automatic reply: Delivery failed"

$Rule = Get-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName

$UpdatedWords = @(
    $Rule.SubjectContainsWords + $NewWord
) |
    Where-Object {
        $_ -and $_.Trim() -ne ""
    } |
    Select-Object -Unique

Set-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName `
    -SubjectContainsWords $UpdatedWords
```

---

# 6. Mehrere Betreffbegriffe ergänzen

## Beschreibung

Die vorhandenen Begriffe bleiben erhalten. Die neuen Begriffe werden ergänzt und doppelte Einträge entfernt.

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

$Rule = Get-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName

$NewWords = @(
    "Delivery incomplete",
    "Message not delivered",
    "Mail System Error",
    "Undelivered Mail Returned to Sender",
    "Willkommen bei CAS",
    "Quarantäne-Bericht",
    "Ergebnis Versand des Mailings",
    "Fälligkeit der Aufgabe",
    "Unzustellbar"
)

$UpdatedWords = @(
    $Rule.SubjectContainsWords + $NewWords
) |
    Where-Object {
        $_ -and $_.Trim() -ne ""
    } |
    Select-Object -Unique

Set-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName `
    -SubjectContainsWords $UpdatedWords
```

---

# 7. Betreffliste vollständig ersetzen

## Wichtiger Unterschied

`Set-InboxRule -SubjectContainsWords` ersetzt die komplette vorhandene Liste.

Deshalb muss die vollständige gewünschte Liste angegeben werden.

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

$SubjectWords = @(
    "Undeliverable",
    "Unzustellbar",
    "Nicht zustellbar",
    "Delivery Status Notification",
    "Delivery has failed",
    "Mail delivery failed",
    "Returned mail",
    "failure notice",
    "Delivery delayed",
    "Delivery incomplete",
    "Message not delivered",
    "Mail System Error",
    "Undelivered Mail Returned to Sender",
    "Willkommen bei CAS",
    "Quarantäne-Bericht",
    "Ergebnis Versand des Mailings",
    "Fälligkeit der Aufgabe",
    "[ECHT FREIBURG CARD] Produkt im Lieferrückstand",
    "Undelivered Mails Returned to Sender",
    "Undelivered E-Mail returned to Sender",
    "[ECHT FREIBURG CARD] Produkt nicht vorrätig"
)

Set-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName `
    -SubjectContainsWords $SubjectWords
```

---

# 8. Priorität der Regel setzen

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

Set-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName `
    -Priority 1
```

---

# 9. Regel nach Änderungen vollständig kontrollieren

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

Get-InboxRule `
    -Mailbox $Mailbox `
    -Identity $RuleName |
    Format-List `
        Name,
        Enabled,
        Priority,
        SubjectContainsWords,
        MoveToFolder,
        StopProcessingRules
```

---

# 10. Nur die Betreffliste kontrollieren

```powershell
$Mailbox  = "support@trolleymaker.com"
$RuleName = "CAS - NDRs nicht importieren"

(
    Get-InboxRule `
        -Mailbox $Mailbox `
        -Identity $RuleName
).SubjectContainsWords |
    Sort-Object
```

---

# 11. Wichtige Hinweise

- Das direkte Setzen von `SubjectContainsWords` ersetzt die bisherige Liste.
- Zum reinen Ergänzen zuerst die vorhandene Regel lesen, anschließend alte und neue Begriffe zusammenführen.
- Der Ordner `CAS_IGNORE` muss in der Mailbox vorhanden sein.
- `StopProcessingRules $true` verhindert, dass nachfolgende Regeln dieselbe Nachricht weiterverarbeiten.
