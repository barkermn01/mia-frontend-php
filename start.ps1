# Mia Storefront Startup Script
# This script starts all necessary services for the Mia Storefront

param(
    [string]$Port = "3000",
    [string]$ServerHost = "localhost",
    [switch]$Production = $false,
    [switch]$SkipMemcached = $false,
    [switch]$Help = $false
)

# Set execution policy for current session if needed
try {
    Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process -Force -ErrorAction SilentlyContinue
} catch {
    # Ignore if we can't set execution policy
}

# Check PowerShell version and set colors accordingly
$PSVersionMajor = $PSVersionTable.PSVersion.Major
if ($PSVersionMajor -ge 7) {
    # PowerShell 7+ supports ANSI colors
    $Red = "`e[31m"
    $Green = "`e[32m"
    $Yellow = "`e[33m"
    $Blue = "`e[34m"
    $Magenta = "`e[35m"
    $Cyan = "`e[36m"
    $White = "`e[37m"
    $Reset = "`e[0m"
} else {
    # Fallback for older PowerShell versions
    $Red = ""
    $Green = ""
    $Yellow = ""
    $Blue = ""
    $Magenta = ""
    $Cyan = ""
    $White = ""
    $Reset = ""
}

function Write-ColorOutput {
    param([string]$Message, [string]$Color = $White)
    if ($Color -eq "" -or $Reset -eq "") {
        Write-Host $Message
    } else {
        Write-Host "$Color$Message$Reset"
    }
}

function Show-Help {
    Write-ColorOutput "Mia Storefront Startup Script" $Cyan
    Write-ColorOutput "================================" $Cyan
    Write-Host ""
    Write-ColorOutput "Usage:" $Yellow
    Write-Host "  .\start.ps1 [OPTIONS]"
    Write-Host ""
    Write-ColorOutput "Options:" $Yellow
    Write-Host "  -Port <port>        Port to run the server on (default: 3000)"
    Write-Host "  -ServerHost <host>  Host to bind to (default: localhost)"
    Write-Host "  -Production         Run in production mode"
    Write-Host "  -SkipMemcached      Skip memcached startup check"
    Write-Host "  -Help               Show this help message"
    Write-Host ""
    Write-ColorOutput "Examples:" $Yellow
    Write-Host "  .\start.ps1                          # Start on localhost:3000"
    Write-Host "  .\start.ps1 -Port 3000               # Start on port 3000"
    Write-Host "  .\start.ps1 -ServerHost 0.0.0.0 -Port 80  # Start on all interfaces, port 80"
    Write-Host "  .\start.ps1 -Production              # Start in production mode"
    exit 0
}

if ($Help) {
    Show-Help
}

Write-ColorOutput "Starting Mia Storefront..." $Cyan
Write-ColorOutput "==============================" $Cyan

# Check if we're in the right directory
if (-not (Test-Path "composer.json")) {
    Write-ColorOutput "Error: composer.json not found. Please run this script from the project root directory." $Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check PHP installation
Write-ColorOutput "Checking PHP installation..." $Blue
try {
    $phpOutput = & php -v 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "PHP command failed"
    }
    $versionLine = ($phpOutput -split "`n")[0]
    Write-ColorOutput "PHP found: $versionLine" $Green
    
    # Check PHP version (require 8.0+)
    if ($versionLine -match "PHP (\d+)\.(\d+)") {
        $major = [int]$matches[1]
        $minor = [int]$matches[2]
        if ($major -lt 8) {
            Write-ColorOutput "Warning: PHP 8.0+ recommended. Current version: $major.$minor" $Yellow
        }
    }
} catch {
    Write-ColorOutput "Error: PHP is not installed or not in PATH." $Red
    Write-ColorOutput "Please install PHP 8.0+ and add it to your PATH." $Red
    Write-ColorOutput "Download from: https://windows.php.net/download/" $Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Check required PHP extensions
Write-ColorOutput "Checking PHP extensions..." $Blue
$requiredExtensions = @("curl", "json", "mbstring")
$optionalExtensions = @("memcached")
$missingRequired = @()

foreach ($ext in $requiredExtensions) {
    try {
        $result = & php -m 2>&1 | Select-String -Pattern "^$ext$"
        if ($result) {
            Write-ColorOutput "Extension '$ext' is installed" $Green
        } else {
            Write-ColorOutput "Extension '$ext' is missing" $Red
            $missingRequired += $ext
        }
    } catch {
        Write-ColorOutput "Extension '$ext' check failed" $Red
        $missingRequired += $ext
    }
}

foreach ($ext in $optionalExtensions) {
    try {
        $result = & php -m 2>&1 | Select-String -Pattern "^$ext$"
        if ($result) {
            Write-ColorOutput "Extension '$ext' is installed" $Green
        } else {
            Write-ColorOutput "Extension '$ext' is optional but recommended" $Yellow
        }
    } catch {
        Write-ColorOutput "Extension '$ext' check failed (optional)" $Yellow
    }
}

if ($missingRequired.Count -gt 0) {
    Write-ColorOutput "Missing required PHP extensions: $($missingRequired -join ', ')" $Red
    Write-ColorOutput "Please install the missing extensions and restart." $Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check Composer installation
Write-ColorOutput "Checking Composer..." $Blue
try {
    $composerOutput = & composer --version 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Composer command failed"
    }
    Write-ColorOutput "Composer found: $composerOutput" $Green
} catch {
    Write-ColorOutput "Error: Composer is not installed or not in PATH." $Red
    Write-ColorOutput "Please install Composer from https://getcomposer.org/" $Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Install/Update dependencies
if (-not (Test-Path "vendor")) {
    Write-ColorOutput "Installing dependencies..." $Blue
    try {
        & composer install --no-dev 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Composer install failed"
        }
        Write-ColorOutput "Dependencies installed successfully" $Green
    } catch {
        Write-ColorOutput "Error: Failed to install dependencies." $Red
        Write-ColorOutput "Error: $_" $Red
        Read-Host "Press Enter to exit"
        exit 1
    }
} else {
    Write-ColorOutput "Dependencies already installed" $Green
}

# Check for .env file
Write-ColorOutput "Checking environment configuration..." $Blue
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Write-ColorOutput "Creating .env file from .env.example..." $Yellow
        Copy-Item ".env.example" ".env"
        Write-ColorOutput "Please edit .env file with your configuration before continuing." $Yellow
        Write-ColorOutput "Required: MIA_SITE_ID" $Yellow
        
        # Try to open .env file in default editor
        try {
            if (Get-Command notepad -ErrorAction SilentlyContinue) {
                Start-Process notepad ".env"
            } elseif (Get-Command code -ErrorAction SilentlyContinue) {
                Start-Process code ".env"
            } else {
                Write-ColorOutput ".env file created at: $(Get-Location)\.env" $Yellow
            }
        } catch {
            Write-ColorOutput ".env file created at: $(Get-Location)\.env" $Yellow
        }
        
        Write-Host ""
        Write-ColorOutput "Press any key to continue after configuring .env..." $Cyan
        $null = Read-Host
    } else {
        Write-ColorOutput "Error: No .env or .env.example file found." $Red
        Read-Host "Press Enter to exit"
        exit 1
    }
}

# Load environment variables from .env file
if (Test-Path ".env") {
    Write-ColorOutput "Loading environment variables..." $Blue
    try {
        Get-Content ".env" | ForEach-Object {
            if ($_ -match "^([^#=]+)=(.*)$") {
                $name = $matches[1].Trim()
                $value = $matches[2].Trim()
                # Remove quotes if present
                $value = $value -replace '^"(.*)"$', '$1'
                $value = $value -replace "^'(.*)'$", '$1'
                [Environment]::SetEnvironmentVariable($name, $value, "Process")
            }
        }
        Write-ColorOutput "Environment variables loaded" $Green
    } catch {
        Write-ColorOutput "Warning: Could not load all environment variables" $Yellow
    }
}

# Test API connection
$apiUrl = [Environment]::GetEnvironmentVariable("MIA_API_URL")
if ($apiUrl) {
    Write-ColorOutput "Testing API connection..." $Blue
    try {
        $healthUrl = "$apiUrl/health"
        $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
        Write-ColorOutput "API is accessible at $apiUrl" $Green
    } catch {
        Write-ColorOutput "Warning: Cannot connect to API at $apiUrl" $Yellow
        Write-ColorOutput "Error: $($_.Exception.Message)" $Yellow
        Write-ColorOutput "Please ensure your Mia API is running on $apiUrl" $Yellow
        Write-Host ""
        $response = Read-Host "Continue anyway? (y/N)"
        if ($response -notmatch "^[Yy]") {
            exit 1
        }
    }
}

# Check Memcached (if not skipped)
if (-not $SkipMemcached) {
    Write-ColorOutput "Checking Memcached..." $Blue
    
    try {
        $memcachedHost = [Environment]::GetEnvironmentVariable("MEMCACHED_HOST")
        if (-not $memcachedHost) { $memcachedHost = "localhost" }
        
        $memcachedPort = [Environment]::GetEnvironmentVariable("MEMCACHED_PORT")
        if (-not $memcachedPort) { $memcachedPort = "11211" }
        
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $tcpClient.ReceiveTimeout = 3000
        $tcpClient.SendTimeout = 3000
        $tcpClient.Connect($memcachedHost, [int]$memcachedPort)
        $tcpClient.Close()
        Write-ColorOutput "Memcached is running on ${memcachedHost}:${memcachedPort}" $Green
    } catch {
        Write-ColorOutput "Warning: Cannot connect to Memcached on ${memcachedHost}:${memcachedPort}" $Yellow
        Write-ColorOutput "The application will work but without caching." $Yellow
        Write-ColorOutput "To start Memcached:" $Yellow
        Write-ColorOutput "- Docker: docker run -d -p 11211:11211 memcached" $Yellow
        Write-ColorOutput "- Or use -SkipMemcached to skip this check" $Yellow
        
        Write-Host ""
        $response = Read-Host "Continue without Memcached? (y/N)"
        if ($response -notmatch "^[Yy]") {
            exit 1
        }
    }
} else {
    Write-ColorOutput "Skipping Memcached check" $Yellow
}

# Set environment mode
if ($Production) {
    [Environment]::SetEnvironmentVariable("APP_ENV", "production", "Process")
    Write-ColorOutput "Running in production mode" $Magenta
} else {
    [Environment]::SetEnvironmentVariable("APP_ENV", "development", "Process")
    Write-ColorOutput "Running in development mode" $Yellow
}

# Create public directory if it doesn't exist
if (-not (Test-Path "public")) {
    Write-ColorOutput "Creating public directory..." $Blue
    New-Item -ItemType Directory -Path "public" -Force | Out-Null
}

# Validate that index.php exists
if (-not (Test-Path "public/index.php")) {
    Write-ColorOutput "Error: public/index.php not found." $Red
    Write-ColorOutput "Please ensure the public directory contains index.php" $Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check if port is available
Write-ColorOutput "Checking port availability..." $Blue
try {
    $tcpClient = New-Object System.Net.Sockets.TcpClient
    $tcpClient.ReceiveTimeout = 1000
    $tcpClient.SendTimeout = 1000
    $tcpClient.Connect($ServerHost, [int]$Port)
    $tcpClient.Close()
    
    # If we get here, something is already using the port
    Write-ColorOutput "Error: Port $Port is already in use on $ServerHost" $Red
    Write-ColorOutput "Common ports in use:" $Yellow
    Write-ColorOutput "- 8080: Often used by APIs (like Mia API)" $Yellow
    Write-ColorOutput "- 3000: Common development port" $Yellow
    Write-ColorOutput "- 8000: PHP/Python development servers" $Yellow
    Write-ColorOutput "- 5000: Flask/Node.js applications" $Yellow
    Write-Host ""
    Write-ColorOutput "Try a different port:" $Cyan
    Write-ColorOutput ".\start.ps1 -Port 3001" $Cyan
    Write-ColorOutput ".\start.ps1 -Port 4000" $Cyan
    Write-ColorOutput ".\start.ps1 -Port 5173" $Cyan
    Read-Host "Press Enter to exit"
    exit 1
} catch {
    # Port is available (connection failed)
    Write-ColorOutput "Port $Port is available" $Green
}

# Start the PHP development server
Write-ColorOutput "Starting PHP development server..." $Blue
Write-ColorOutput "Server: http://${ServerHost}:${Port}" $Cyan
Write-ColorOutput "Document Root: $(Get-Location)\public" $Cyan
Write-ColorOutput "Press Ctrl+C to stop the server" $Yellow
Write-Host ""

try {
    # Start PHP built-in server
    Set-Location "public"
    & php -S "${ServerHost}:${Port}" -t . index.php
} catch {
    Write-ColorOutput "Error starting server: $_" $Red
    Read-Host "Press Enter to exit"
    exit 1
} finally {
    # Return to original directory
    Set-Location ".."
    Write-ColorOutput "Server stopped" $Yellow
}

Write-ColorOutput "Goodbye!" $Cyan