# Mia Frontend - Local Docker Development Script

param(
    [string]$Action = "start",
    [string]$Port = "3000",
    [switch]$Build = $false,
    [switch]$Clean = $false
)

$ErrorActionPreference = "Stop"

Write-Host "Mia Frontend - Local Docker Development" -ForegroundColor Blue
Write-Host "=======================================" -ForegroundColor Blue

$imageName = "mia-frontend-local"
$containerName = "mia-frontend-dev"

function Show-Usage {
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\docker-dev.ps1 start [-Build] [-Port 3000]  # Start container"
    Write-Host "  .\docker-dev.ps1 stop                         # Stop container"
    Write-Host "  .\docker-dev.ps1 restart [-Build]             # Restart container"
    Write-Host "  .\docker-dev.ps1 logs                         # Show container logs"
    Write-Host "  .\docker-dev.ps1 shell                        # Open shell in container"
    Write-Host "  .\docker-dev.ps1 clean                        # Remove container and image"
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "  -Build    Force rebuild of Docker image"
    Write-Host "  -Port     Port to run on (default: 3000)"
    Write-Host "  -Clean    Remove existing container and image"
    Write-Host ""
}

function Test-Docker {
    try {
        docker info | Out-Null
        Write-Host "[OK] Docker is running" -ForegroundColor Green
    } catch {
        Write-Host "[ERROR] Docker is not running or not installed" -ForegroundColor Red
        Write-Host "Please start Docker Desktop and try again" -ForegroundColor Yellow
        exit 1
    }
}

function Stop-Container {
    Write-Host "Stopping container..." -ForegroundColor Yellow
    $ErrorActionPreference = "Continue"
    docker stop $containerName 2>&1 | Out-Null
    docker rm $containerName 2>&1 | Out-Null
    $ErrorActionPreference = "Stop"
    Write-Host "[OK] Container stopped and removed" -ForegroundColor Green
}

function Build-Image {
    Write-Host "Building Docker image..." -ForegroundColor Yellow
    
    # Check if .env file exists
    if (!(Test-Path ".env")) {
        Write-Host "[WARNING] .env file not found - creating from .env.example" -ForegroundColor Yellow
        if (Test-Path ".env.example") {
            Copy-Item ".env.example" ".env"
            Write-Host "[OK] Created .env from .env.example" -ForegroundColor Green
        } else {
            Write-Host "[ERROR] .env.example not found - please create .env file" -ForegroundColor Red
            exit 1
        }
    }
    
    # Build the image
    docker build -t $imageName -f deployment-frontend/Dockerfile .
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Docker build failed" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "[OK] Docker image built successfully" -ForegroundColor Green
}

function Start-Container {
    Write-Host "Starting container on port $Port..." -ForegroundColor Yellow
    
    # Check if container is already running
    $running = docker ps --filter "name=$containerName" --format "{{.Names}}" 2>$null
    if ($running -eq $containerName) {
        Write-Host "[WARNING] Container is already running" -ForegroundColor Yellow
        Write-Host "Access at: http://localhost:$Port" -ForegroundColor Blue
        return
    }
    
    # Remove existing stopped container
    $ErrorActionPreference = "Continue"
    docker rm $containerName 2>&1 | Out-Null
    $ErrorActionPreference = "Stop"
    
    # Start new container
    docker run -d `
        --name $containerName `
        -p "${Port}:3000" `
        -v "${PWD}:/var/www/html" `
        -e "ENV=development" `
        -e "MIA_DEBUG=true" `
        -e "CACHE_ENABLED=false" `
        $imageName
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Failed to start container" -ForegroundColor Red
        exit 1
    }
    
    # Wait a moment for container to start
    Start-Sleep -Seconds 2
    
    # Check if container is running
    $status = docker ps --filter "name=$containerName" --format "{{.Status}}" 2>$null
    if ($status) {
        Write-Host "[OK] Container started successfully" -ForegroundColor Green
        Write-Host ""
        Write-Host "Frontend: http://localhost:$Port" -ForegroundColor Blue
        Write-Host "Admin: http://localhost:$Port/systemAdmin" -ForegroundColor Blue
        Write-Host "Health: http://localhost:$Port/health" -ForegroundColor Blue
        Write-Host ""
        Write-Host "Useful commands:" -ForegroundColor Yellow
        Write-Host "  .\docker-dev.ps1 logs    # View logs"
        Write-Host "  .\docker-dev.ps1 shell   # Open shell"
        Write-Host "  .\docker-dev.ps1 stop    # Stop container"
    } else {
        Write-Host "[ERROR] Container failed to start" -ForegroundColor Red
        Write-Host "Checking logs..." -ForegroundColor Yellow
        docker logs $containerName
        exit 1
    }
}

function Show-Logs {
    Write-Host "Container logs:" -ForegroundColor Yellow
    docker logs -f $containerName
}

function Open-Shell {
    Write-Host "Opening shell in container..." -ForegroundColor Yellow
    docker exec -it $containerName /bin/bash
}

function Clean-All {
    Write-Host "Cleaning up Docker resources..." -ForegroundColor Yellow
    
    # Stop and remove container
    $ErrorActionPreference = "Continue"
    docker stop $containerName 2>&1 | Out-Null
    docker rm $containerName 2>&1 | Out-Null
    
    # Remove image
    docker rmi $imageName 2>&1 | Out-Null
    $ErrorActionPreference = "Stop"
    
    Write-Host "[OK] Cleanup completed" -ForegroundColor Green
}

# Main script logic
Test-Docker

switch ($Action.ToLower()) {
    "start" {
        if ($Build -or $Clean) {
            if ($Clean) {
                Clean-All
            }
            Build-Image
        } else {
            # Check if image exists
            $imageExists = docker images --filter "reference=$imageName" --format "{{.Repository}}" 2>$null
            if (!$imageExists) {
                Write-Host "Image not found - building..." -ForegroundColor Yellow
                Build-Image
            }
        }
        Start-Container
    }
    
    "stop" {
        Stop-Container
    }
    
    "restart" {
        Stop-Container
        if ($Build) {
            Build-Image
        }
        Start-Container
    }
    
    "logs" {
        Show-Logs
    }
    
    "shell" {
        Open-Shell
    }
    
    "clean" {
        Clean-All
    }
    
    "help" {
        Show-Usage
    }
    
    default {
        Write-Host "[ERROR] Unknown action: $Action" -ForegroundColor Red
        Show-Usage
        exit 1
    }
}