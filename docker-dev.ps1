# Mia Frontend - Local Docker Development Script

param(
    [string]$Action = "start",
    [switch]$Build = $false,
    [switch]$Clean = $false
)

$ErrorActionPreference = "Stop"

Write-Host "Mia Frontend - Local Docker Development" -ForegroundColor Blue
Write-Host "=======================================" -ForegroundColor Blue

function Show-Usage {
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\docker-dev.ps1 start [-Build]    # Start services"
    Write-Host "  .\docker-dev.ps1 stop              # Stop services"
    Write-Host "  .\docker-dev.ps1 restart [-Build]  # Restart services"
    Write-Host "  .\docker-dev.ps1 logs              # Show logs"
    Write-Host "  .\docker-dev.ps1 shell             # Open shell in frontend"
    Write-Host "  .\docker-dev.ps1 clean             # Remove containers and images"
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "  -Build    Force rebuild of Docker images"
    Write-Host "  -Clean    Remove existing containers and images"
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

function Stop-Services {
    Write-Host "Stopping services..." -ForegroundColor Yellow
    docker-compose down
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[OK] Services stopped" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Failed to stop services" -ForegroundColor Red
        exit 1
    }
}

function Build-Images {
    Write-Host "Building Docker images..." -ForegroundColor Yellow
    
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
    
    # Build the images
    docker-compose build
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Docker build failed" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "[OK] Docker images built successfully" -ForegroundColor Green
}

function Start-Services {
    Write-Host "Starting services..." -ForegroundColor Yellow
    
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
    
    # Start services
    docker-compose up -d
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Failed to start services" -ForegroundColor Red
        exit 1
    }
    
    # Wait a moment for services to start
    Start-Sleep -Seconds 2
    
    Write-Host "[OK] Services started successfully" -ForegroundColor Green
    Write-Host ""
    Write-Host "Frontend: http://localhost:3000" -ForegroundColor Blue
    Write-Host "Admin: http://localhost:3000/systemAdmin" -ForegroundColor Blue
    Write-Host "Health: http://localhost:3000/health" -ForegroundColor Blue
    Write-Host "Memcached: localhost:11211" -ForegroundColor Blue
    Write-Host ""
    Write-Host "Useful commands:" -ForegroundColor Yellow
    Write-Host "  .\docker-dev.ps1 logs    # View logs"
    Write-Host "  .\docker-dev.ps1 shell   # Open shell"
    Write-Host "  .\docker-dev.ps1 stop    # Stop services"
}

function Show-Logs {
    Write-Host "Service logs:" -ForegroundColor Yellow
    docker-compose logs -f
}

function Open-Shell {
    Write-Host "Opening shell in frontend container..." -ForegroundColor Yellow
    docker-compose exec frontend /bin/bash
}

function Clean-All {
    Write-Host "Cleaning up Docker resources..." -ForegroundColor Yellow
    
    # Stop and remove containers, networks, and images
    docker-compose down --rmi all --volumes
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[OK] Cleanup completed" -ForegroundColor Green
    } else {
        Write-Host "[WARNING] Cleanup completed with warnings" -ForegroundColor Yellow
    }
}

# Main script logic
Test-Docker

switch ($Action.ToLower()) {
    "start" {
        if ($Clean) {
            Clean-All
        }
        if ($Build) {
            Build-Images
        }
        Start-Services
    }
    
    "stop" {
        Stop-Services
    }
    
    "restart" {
        Stop-Services
        if ($Build) {
            Build-Images
        }
        Start-Services
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