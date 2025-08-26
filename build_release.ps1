$version = "1.0.3"
$releaseDir = "release_tmp_$version"
$zipName = "advanced-php-mailer-$version.zip"

Remove-Item -Recurse -Force $releaseDir -ErrorAction SilentlyContinue
Remove-Item -Force $zipName -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $releaseDir

# Copy whitelist
Copy-Item -Recurse src $releaseDir
Copy-Item -Recurse vendor $releaseDir
Copy-Item composer.json, composer.lock, README.md, LICENSE $releaseDir -ErrorAction SilentlyContinue
Copy-Item -Recurse bin, examples, docs $releaseDir -ErrorAction SilentlyContinue
Copy-Item CONTRIBUTING.md, CODE_OF_CONDUCT.md, INSTALL.md $releaseDir -ErrorAction SilentlyContinue

# Remove Russian docs
Remove-Item -Force "$releaseDir\SHARED_HOSTING_SETUP.md" -ErrorAction SilentlyContinue
Remove-Item -Force "$releaseDir\GIT_COMMANDS.md" -ErrorAction SilentlyContinue

# Create release notes
@"
Release v1.0.3 — Stable SMTP fixes and diagnostics

- Fix: Resolved send/read flow bug in SmtpTransport (removed duplicated reads).
- Improve: Detailed opt-in SMTP diagnostics (raw responses, timings, socket meta).
- Improve: Stabilization delay after TLS enable; improved auth handling.
- Added: FileLogger example and improved contact handler example.
- Tests/CI/Docs: phpunit.xml, CI tweaks, README updates.
"@ | Out-File -FilePath "$releaseDir\RELEASE_NOTES.md" -Encoding utf8

Compress-Archive -Path "$releaseDir\*" -DestinationPath $zipName -Force
Write-Output "Created $zipName"