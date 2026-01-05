# Development Configuration Helper
# This script helps set up the development environment

param(
    [switch]$InstallMemcached = $false,
    [switch]$InstallPHP = $false,
    [switch]$InstallComposer = $false,
    [switch]$Help = $false
)

$Red = "`e[31m"
$Green = "`e[32m"
$Yellow = "`e[33m"
$Blue = "`e[34m"
$Cyan = "`e[36m"
$Reset = "`e[0m"

function Write-ColorOutput {
    param([string]$Message, [string]$Color = "`e[37m")
    Write-Host "$Color$Message$Reset"
}

function Show-Help {
    Write-ColorOutput "Development Configuration Helper" $Cyan
    Write-ColorOutput "=================================" $Cyan
    Write-Host ""
    Write-ColorOutput "This script helps install and configure development dependencies." $Yellow
    Write-Host ""
    Write-ColorOutput "Options:" $Yellow
    Write-Host "  -InstallPHP         Show PHP installation instructions"
    Write-Host "  -InstallComposer    Show Composer installation instructions"
    Write-Host "  -InstallMemcached   Show Memcached installation instructions"
    Write-Host "  -Help               Show this help message"
    Write-Host ""
    Write-ColorOutput "Examples:" $Yellow
    Write-Host "  .\dev-config.ps1 -InstallPHP"
    Write-Host "  .\dev-config.ps1 -InstallMemcached"
    exit 0
}

if ($Help) {
    Show-Help
}

Write-ColorOutput "🔧 Mia Storefront Development Setup" $Cyan
Write-ColorOutput "====================================" $Cyan

if ($InstallPHP) {
    Write-ColorOutput "📥 PHP Installation Instructions" $Blue
    Write-ColorOutput "================================" $Blue
    Write-Host ""
    Write-ColorOutput "Option 1: Using Chocolatey (Recommended)" $Yellow
    Write-Host "1. Install Chocolatey: https://chocolatey.org/install"
    Write-Host "2. Run: choco install php --version=8.4.0"
    Write-Host "3. Run: choco install php-memcached"
    Write-Host ""
    Write-ColorOutput "Option 2: Manual Installation" $Yellow
    Write-Host "1. Download PHP 8.4+ from: https://windows.php.net/download/"
    Write-Host "2. Extract to C:\php"
    Write-Host "3. Add C:\php to your PATH environment variable"
    Write-Host "4. Copy php.ini-development to php.ini"
    Write-Host "5. Enable extensions in php.ini:"
    Write-Host "   - extension=curl"
    Write-Host "   - extension=mbstring"
    Write-Host "   - extension=openssl"
    Write-Host "   - extension=memcached (if available)"
    Write-Host ""
    Write-ColorOutput "Option 3: Using XAMPP" $Yellow
    Write-Host "1. Download XAMPP from: https://www.apachefriends.org/"
    Write-Host "2. Install and add PHP to PATH (usually C:\xampp\php)"
    Write-Host ""
}

if ($InstallComposer) {
    Write-ColorOutput "📥 Composer Installation Instructions" $Blue
    Write-ColorOutput "=====================================" $Blue
    Write-Host ""
    Write-ColorOutput "Option 1: Using Installer (Recommended)" $Yellow
    Write-Host "1. Download from: https://getcomposer.org/Composer-Setup.exe"
    Write-Host "2. Run the installer"
    Write-Host "3. Restart your terminal"
    Write-Host ""
    Write-ColorOutput "Option 2: Manual Installation" $Yellow
    Write-Host "1. Download composer.phar from: https://getcomposer.org/download/"
    Write-Host "2. Create composer.bat with:"
    Write-Host "   @php `"%~dp0composer.phar`" %*"
    Write-Host "3. Add to PATH"
    Write-Host ""
}

if ($InstallMemcached) {
    Write-ColorOutput "📥 Memcached Installation Instructions" $Blue
    Write-ColorOutput "======================================" $Blue
    Write-Host ""
    Write-ColorOutput "Option 1: Using Docker (Recommended)" $Yellow
    Write-Host "1. Install Docker Desktop: https://www.docker.com/products/docker-desktop"
    Write-Host "2. Run: docker run -d -p 11211:11211 --name memcached memcached"
    Write-Host "3. Memcached will be available on localhost:11211"
    Write-Host ""
    Write-ColorOutput "Option 2: Windows Binary" $Yellow
    Write-Host "1. Download from: https://commaster.net/content/installing-memcached-windows"
    Write-Host "2. Extract and run memcached.exe"
    Write-Host "3. Install as service (optional): memcached.exe -d install"
    Write-Host ""
    Write-ColorOutput "Option 3: Using WSL2" $Yellow
    Write-Host "1. Install WSL2 with Ubuntu"
    Write-Host "2. Run: sudo apt update && sudo apt install memcached"
    Write-Host "3. Start: sudo systemctl start memcached"
    Write-Host ""
}

# If no specific installation requested, show general setup
if (-not ($InstallPHP -or $InstallComposer -or $InstallMemcached)) {
    Write-ColorOutput "🔍 Checking Current Environment" $Blue
    Write-ColorOutput "===============================" $Blue
    Write-Host ""
    
    # Check PHP
    try {
        $phpVersion = php -v 2>$null
        if ($LASTEXITCODE -eq 0) {
            $versionLine = ($phpVersion -split "`n")[0]
            Write-ColorOutput "✅ PHP: $versionLine" $Green
        } else {
            throw "Not found"
        }
    } catch {
        Write-ColorOutput "❌ PHP: Not installed" $Red
        Write-ColorOutput "   Run: .\dev-config.ps1 -InstallPHP" $Yellow
    }
    
    # Check Composer
    try {
        $composerVersion = composer --version 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-ColorOutput "✅ Composer: $composerVersion" $Green
        } else {
            throw "Not found"
        }
    } catch {
        Write-ColorOutput "❌ Composer: Not installed" $Red
        Write-ColorOutput "   Run: .\dev-config.ps1 -InstallComposer" $Yellow
    }
    
    # Check Memcached
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $tcpClient.Connect("localhost", 11211)
        $tcpClient.Close()
        Write-ColorOutput "✅ Memcached: Running on localhost:11211" $Green
    } catch {
        Write-ColorOutput "❌ Memcached: Not running" $Red
        Write-ColorOutput "   Run: .\dev-config.ps1 -InstallMemcached" $Yellow
    }
    
    # Check PHP extensions
    Write-Host ""
    Write-ColorOutput "PHP Extensions:" $Blue
    $extensions = @("curl", "mbstring", "json", "memcached")
    foreach ($ext in $extensions) {
        try {
            $result = php -m 2>$null | Select-String -Pattern "^$ext$"
            if ($result) {
                Write-ColorOutput "✅ $ext" $Green
            } else {
                Write-ColorOutput "❌ $ext" $Red
            }
        } catch {
            Write-ColorOutput "❌ $ext (PHP not available)" $Red
        }
    }
    
    Write-Host ""
    Write-ColorOutput "Quick Setup Commands:" $Cyan
    Write-Host "  .\dev-config.ps1 -InstallPHP         # PHP installation help"
    Write-Host "  .\dev-config.ps1 -InstallComposer    # Composer installation help"
    Write-Host "  .\dev-config.ps1 -InstallMemcached   # Memcached installation help"
    Write-Host "  .\start.ps1                          # Start the storefront"
}

Write-Host ""
Write-ColorOutput "📚 Additional Resources:" $Cyan
Write-Host "  - Project README: README.md"
Write-Host "  - Environment Config: .env.example"
Write-Host "  - Mia SDK Docs: vendor/mia/php-sdk/wiki/"