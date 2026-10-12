param([string]$Php = 'php')
$adminName = Read-Host 'Nome do administrador'
$adminEmail = Read-Host 'E-mail do administrador'
$adminPassword = Read-Host 'Senha (12 a 72 bytes)' -AsSecureString
$adminPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($adminPassword)
try {
    $adminInput = @{ nome = $adminName; email = $adminEmail; senha = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($adminPointer) }
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $adminInput | ConvertTo-Json -Compress | & $Php (Join-Path $PSScriptRoot 'provisionar_admin.php')
    $adminExit = $LASTEXITCODE
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($adminPointer)
    $adminInput = $null
    $adminPassword.Dispose()
}
exit $adminExit
