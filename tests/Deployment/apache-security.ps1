param([string]$ProjectBaseUrl = 'http://localhost/toyota')

$ErrorActionPreference = 'Stop'
$ProjectBaseUrl = $ProjectBaseUrl.TrimEnd('/')
foreach ($relativePath in @('.env', 'composer.json', 'composer.lock', 'SPEC.md', 'storage/logs/laravel.log', 'tests/Fixtures/approve_submission.php', '.git/config', '.laravel-tools/browser/admin-dashboard.html')) {
    $targetUrl = "$ProjectBaseUrl/$relativePath"
    try {
        $response = Invoke-WebRequest -Uri $targetUrl -UseBasicParsing -TimeoutSec 10
        $status = [int]$response.StatusCode
    } catch {
        if (!$_.Exception.Response) { throw }
        $status = [int]$_.Exception.Response.StatusCode
    }
    if ($status -notin @(403, 404)) { throw "Private path accessible: $relativePath (HTTP $status)." }
    Write-Output "PASS private path $relativePath HTTP $status"
}
$landing = Invoke-WebRequest -Uri "$ProjectBaseUrl/public/" -UseBasicParsing -TimeoutSec 10
if ([int]$landing.StatusCode -ne 200) { throw 'Public landing is unavailable.' }
Write-Output 'PASS public landing HTTP 200'
