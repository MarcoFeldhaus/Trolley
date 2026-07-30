1. Frisch als Trolleymaker einloggen

cd C:\Users\m.feldhaus\Trolleymaker_Gitlab\backend

$apiKey = "ucw8wCErrE6xDFrB5sCx9KlLOETBWvdQ"

$loginBody = @{
  mail = "m.feldhaus@trolleymaker.com"
  password = "Testtm123!?"
} | ConvertTo-Json

$response = Invoke-WebRequest `
  -Method Post `
  -Uri "http://localhost:8082/portals/api/v1/simplyCity/login" `
  -Headers @{
    "X-API-Key" = $apiKey
    "Content-Type" = "application/json"
  } `
  -Body $loginBody

$response.StatusCode
$response.Content
$response.Headers["Set-Cookie"]


2. Dann Token aus dem Cookie ziehen:

$sessionToken = ([regex]::Match($response.Headers["Set-Cookie"], "X-Authorization=([^;]+)").Groups[1].Value)
$sessionToken

2. Neue virtuelle Karte erzeugen
$body = @{
  cardHolderId = "0xCUSTOMER"
  cardTypeId = 1
} | ConvertTo-Json

$newCard = Invoke-RestMethod `
  -Method Post `
  -Uri "http://localhost:8082/portals/api/v1/regions/1/cards/virtual/register" `
  -Headers @{
    "X-API-Key" = $apiKey
    "X-Authorization" = $sessionToken
    "Content-Type" = "application/json"
  } `
  -Body $body

$newCard
$newCardId = $newCard.cardNumber
$newCardId


3. Login in GenesisWorld (CAS) und prüfen, ob die Karte dort angelegt wurde.
  $env:PGPASSWORD="1234"

& "C:\Program Files\PostgreSQL\18\bin\psql.exe" `
  -U global_payment `
  -h 127.0.0.1 `
  -p 5432 `
  -d global_payment `
  -c "SELECT * FROM tb_gw_mapping ORDER BY gw_data_type_id, identifier, gw_value;"

4. Log-Files prüfen
    cd C:\Users\m.feldhaus\Trolleymaker_Gitlab\global-payment

    Select-String .\global-payment-local.log `
    -Pattern "responseBody|status|TransactionConnector|CardBalanceConnector|80000008|80000007|error" `
    -Context 5,20
