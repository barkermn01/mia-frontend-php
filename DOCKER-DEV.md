# Local Docker Development

This guide helps you run the Mia Frontend system locally using Docker, which matches the production environment exactly.

## Quick Start

### Option 1: PowerShell Script (Recommended)

```powershell
# Start the development environment
.\docker-dev.ps1 start -Build

# View logs
.\docker-dev.ps1 logs

# Open shell in container
.\docker-dev.ps1 shell

# Stop the environment
.\docker-dev.ps1 stop
```

### Option 2: Docker Compose

```bash
# Start services
docker-compose up -d --build

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

## Access Points

Once running, you can access:

- **Frontend**: http://localhost:3000
- **Admin Panel**: http://localhost:3000/systemAdmin
- **Health Check**: http://localhost:3000/health
- **Memcached**: localhost:11211 (if using docker-compose)

## Development Workflow

### Making Changes

1. **Code Changes**: Edit files normally - they're mounted as volumes
2. **PHP Changes**: Refresh browser (no restart needed)
3. **Template Changes**: Clear cache or disable caching
4. **Dockerfile Changes**: Rebuild with `.\docker-dev.ps1 restart -Build`

### Debugging

```powershell
# View real-time logs
.\docker-dev.ps1 logs

# Open shell for debugging
.\docker-dev.ps1 shell

# Inside container, you can:
php -v                    # Check PHP version
composer install          # Install dependencies
tail -f /var/log/apache2/error.log  # View Apache errors
```

### Environment Configuration

The container runs with development settings:
- `ENV=development`
- `MIA_DEBUG=true`
- Volume mounting for live code changes

## PowerShell Script Commands

```powershell
# Start container (build if needed)
.\docker-dev.ps1 start

# Force rebuild and start
.\docker-dev.ps1 start -Build

# Start on different port
.\docker-dev.ps1 start -Port 8080

# Restart container
.\docker-dev.ps1 restart

# Stop container
.\docker-dev.ps1 stop

# View logs (follow mode)
.\docker-dev.ps1 logs

# Open bash shell in container
.\docker-dev.ps1 shell

# Clean up everything
.\docker-dev.ps1 clean

# Show help
.\docker-dev.ps1 help
```

## Docker Compose Commands

```bash
# Start services in background
docker-compose up -d

# Start with rebuild
docker-compose up -d --build

# View logs
docker-compose logs -f frontend

# Stop services
docker-compose down

# Remove everything including volumes
docker-compose down -v --rmi all
```

## Troubleshooting

### Container Won't Start

1. Check Docker is running: `docker info`
2. Check port availability: `netstat -an | findstr :3000`
3. View build logs: `.\docker-dev.ps1 start -Build`
4. Check container logs: `.\docker-dev.ps1 logs`

### Permission Issues

```powershell
# Fix file permissions (if needed)
.\docker-dev.ps1 shell
chown -R www-data:www-data /var/www/html
```

### Cache Issues

```powershell
# Clear all caches
.\docker-dev.ps1 shell
rm -rf /tmp/cache/*
```

### API Connection Issues

1. Check `.env` file has correct API URL
2. Verify API is accessible: `curl https://api.miaai.me/health`
3. Check container environment: `.\docker-dev.ps1 shell` then `env | grep MIA`

## Production Parity

This local setup matches production:
- Same PHP version (8.4)
- Same Apache configuration
- Same file structure
- Same environment variables
- Same Docker base image

The only differences:
- Volume mounting for live development
- Debug mode enabled
- Cache disabled for faster development