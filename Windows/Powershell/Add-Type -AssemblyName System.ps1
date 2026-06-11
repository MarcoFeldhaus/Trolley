Add-Type -AssemblyName System.Web

$inputFile = "C:\Users\m.feldhaus\Downloads\agbs.html"
$outputFile = "C:\Users\m.feldhaus\Downloads\agbs_decoded.html"

$content = Get-Content -Path $inputFile -Raw -Encoding UTF8
$decoded = [System.Web.HttpUtility]::HtmlDecode($content)

Set-Content -Path $outputFile -Value $decoded -Encoding UTF8