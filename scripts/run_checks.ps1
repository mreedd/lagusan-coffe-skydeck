# Run PHP syntax check and the SQL scanner locally using XAMPP PHP
$php = 'C:\xampp\php\php.exe'
if (!(Test-Path $php)) {
    Write-Error "php.exe not found at $php. Update the path to your PHP executable."
    exit 1
}

Write-Host "Running php -l across repo..."
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & $php -l $_.FullName
}

Write-Host "Running SQL scanner..."
& $php "$(Join-Path $PSScriptRoot 'sql_scan.php')"

Write-Host "Checks complete."