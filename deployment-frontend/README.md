# Mia Frontend Deployment

This directory contains the deployment configuration for the Mia Frontend PHP application using AWS Fargate.

## Prerequisites

1. **AWS CLI**: Configured with appropriate permissions
2. **Docker**: Running and accessible
3. **PowerShell**: For running the deployment script
4. **Domain**: Configured domain name for the frontend
5. **SSL Certificate**: ACM certificate for HTTPS (optional)

## Quick Start

1. **Edit parameters.json** with your configuration:
   ```json
   {
     "ParameterKey": "FrontendDomains",
     "ParameterValue": "oxwinches.com,oxwinch.com,oxwinches.miaai.me"
   }
   ```

2. **Deploy the frontend**:
   ```powershell
   cd deployment-frontend
   .\deploy.ps1 -StackName 'mia-frontend' -ImageTag 'latest' -Region 'eu-west-2' -AWSProfile 'mia'
   ```

3. **Configure Cloudflare DNS**:
   - Point all domains to the ALB DNS name
   - Set SSL/TLS mode to "Full" or "Full (strict)" in Cloudflare
   - Enable "Always Use HTTPS" in Cloudflare

## Configuration

### Parameters

- **ProjectName**: Resource naming prefix (default: mia-frontend)
- **Environment**: Deployment environment (production/staging/development)
- **FrontendDomains**: Comma-separated list of all domains (e.g., oxwinches.com,oxwinch.com,oxwinches.miaai.me)
- **SSLCertificateArn**: ACM certificate ARN for HTTPS (must cover all domains)
- **ImageTag**: Docker image tag to deploy
- **WebCpu/WebMemory**: ECS task resources

### Environment Variables

The container is configured with:
- `MIA_API_URL`: Backend API URL
- `MIA_SITE_ID`: Site identifier
- `MIA_SSL_VERIFY`: SSL verification (true/false)
- `LOG_LEVEL`: Logging level

## Architecture

### Infrastructure
- **VPC**: Creates independent VPC with public subnets
- **ECS Fargate**: Serverless container hosting
- **Application Load Balancer**: HTTPS termination and routing
- **CloudWatch Logs**: Centralized logging

### Security
- Public subnets for ALB and ECS tasks
- Security groups restricting access
- IAM roles with minimal permissions
- HTTPS redirect (when SSL certificate provided)

### Networking
- **Public Subnets**: 10.1.1.0/24, 10.1.2.0/24
- **Port**: Container runs on port 3000
- **Health Check**: `/health` endpoint

## Deployment Process

1. **Prerequisites Check**: AWS CLI, Docker, parameters file
2. **ECR Repository**: Creates/verifies container repository
3. **Image Build**: Builds and pushes Docker image
4. **CloudFormation**: Deploys/updates infrastructure
5. **Service Update**: Forces ECS service deployment

## Monitoring

- **CloudWatch Logs**: `/ecs/mia-frontend-web`
- **ECS Console**: Monitor service health and tasks
- **ALB Target Groups**: Check health check status

## DNS Configuration (Cloudflare)

After deployment, configure your Cloudflare DNS for all domains:

1. **Get ALB DNS Name** from CloudFormation outputs

2. **Create DNS Records** in Cloudflare:
   ```
   Type: CNAME
   Name: oxwinches.com (or @)
   Target: [ALB DNS Name]
   Proxy status: Proxied (orange cloud) ← IMPORTANT
   
   Type: CNAME
   Name: oxwinch.com (or @)
   Target: [ALB DNS Name]
   Proxy status: Proxied (orange cloud) ← IMPORTANT
   
   Type: CNAME
   Name: oxwinches.miaai.me
   Target: [ALB DNS Name]
   Proxy status: Proxied (orange cloud) ← IMPORTANT
   ```

3. **SSL/TLS Settings** in Cloudflare:
   - SSL/TLS encryption mode: **Full** (not Full strict)
   - Always Use HTTPS: On
   - Automatic HTTPS Rewrites: On

### Certificate Options

Since Cloudflare proxies all traffic, you have flexible certificate options:

**Option 1: Cloudflare Origin Certificate (Recommended)**
- Generate a free 15-year certificate in Cloudflare (SSL/TLS → Origin Server → Create Certificate)
- Upload to ACM or use directly
- Covers all your domains but only trusted by Cloudflare (perfect for this use case)
- You don't need to manage certificates for customer domains

**Option 2: ACM Certificate for Subdomain Only**
- Keep your existing ACM certificate that only covers `oxwinches.miaai.me`
- Cloudflare handles SSL for customer domains (`oxwinches.com`, `oxwinch.com`)
- Set Cloudflare SSL mode to "Full" (not "Full strict")

**Option 3: Wildcard ACM Certificate**
- Use `*.miaai.me` to cover all your subdomains
- Still doesn't require managing customer domain certificates

**Why This Works:**
- Cloudflare terminates SSL for end users (using Cloudflare's certificate)
- Cloudflare re-encrypts to your ALB (using your certificate)
- In "Full" mode, Cloudflare doesn't validate your certificate's domain names
- Customer domains get Cloudflare's SSL certificate automatically

## Troubleshooting

### Common Issues

1. **ECR Repository Access**
   - Ensure AWS credentials have ECR permissions
   - Check region matches your configuration

2. **Image Build Fails**
   - Verify Docker is running
   - Check ECR permissions

3. **Service Won't Start**
   - Check CloudWatch logs
   - Verify environment variables
   - Check security group rules

4. **Health Check Failing**
   - Verify `/health` endpoint responds
   - Check container port configuration

### Useful Commands

```powershell
# Check stack status
aws cloudformation describe-stacks --stack-name mia-frontend --region eu-west-2

# View service logs
aws logs tail /ecs/mia-frontend-web --follow --region eu-west-2

# Force service update
aws ecs update-service --cluster mia-frontend-cluster --service mia-frontend-web --force-new-deployment --region eu-west-2
```

## Cost Optimization

- **Fargate**: Pay only for running tasks
- **Single AZ**: Can run in single AZ for cost savings (modify subnets)
- **Log Retention**: Set to 7 days to minimize storage costs
- **Resource Sizing**: Start with 256 CPU / 512 MB memory

## Security Best Practices

- SSL certificate required for production
- Security groups restrict access to necessary ports only
- IAM roles follow principle of least privilege
- Container runs as non-root user
- Health checks ensure service availability
- Independent VPC provides network isolation