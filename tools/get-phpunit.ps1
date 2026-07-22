# Fetch the PHPUnit phar used by this project's test suite.
# The phar is gitignored (dev-only, never deployed to the web root); run this once
# after cloning, then: php tools\phpunit.phar
$ErrorActionPreference = 'Stop'
$version = '11'
$dest = Join-Path $PSScriptRoot 'phpunit.phar'
$url = "https://phar.phpunit.de/phpunit-$version.phar"
Write-Host "Downloading PHPUnit $version -> $dest"
Invoke-WebRequest -Uri $url -OutFile $dest
& php $dest --version
