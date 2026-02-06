# Mia Frontend CloudFormation Deployment Script

param(
    [string]$StackName = "mia-frontend",
    [string]$ImageTag = "latest",
    [string]$Region = "eu-west-2",
    [string]$AWSProfile = "mia"
)

$ErrorActionPreference = "Stop"

Write-Host "Mia Frontend CloudFormation Deployment" -ForegroundColor Blue
Write-Host "======================================" -ForegroundColor Blue

# Check prerequisites
Write-Host "Checking prerequisites..." -ForegroundColor Yellow

# Check AWS CLI
try {
    if ($AWSProfile) {
        aws sts get-caller-identity --profile $AWSProfile --no-cli-pager | Out-Null
        Write-Host "AWS CLI configured successfully - Profile: $AWSProfile" -ForegroundColor Green
    } else {
        aws sts get-caller-identity --no-cli-pager | Out-Null
        Write-Host "AWS CLI configured successfully" -ForegroundColor Green
    }
} catch {
    Write-Host "AWS CLI not configured" -ForegroundColor Red
    exit 1
}

# Check Docker
try {
    docker info | Out-Null
    Write-Host "Docker running successfully" -ForegroundColor Green
} catch {
    Write-Host "Docker not running" -ForegroundColor Red
    exit 1
}

# Check parameters file
if (!(Test-Path "parameters.json")) {
    Write-Host "parameters.json not found" -ForegroundColor Red
    Write-Host "Please edit parameters.json with your values"
    exit 1
}
Write-Host "parameters.json found successfully" -ForegroundColor Green

# Frontend is standalone - no backend stack dependencies needed
Write-Host "Frontend deployment is standalone - no backend dependencies" -ForegroundColor Green

# Update ImageTag in parameters
Write-Host "Updating image tag to: $ImageTag" -ForegroundColor Yellow
$parameters = Get-Content "parameters.json" | ConvertFrom-Json
foreach ($param in $parameters) {
    if ($param.ParameterKey -eq "ImageTag") {
        $param.ParameterValue = $ImageTag
        break
    }
}
$parameters | ConvertTo-Json -Depth 10 | Set-Content "parameters.json"

# Create ECR repository if it doesn't exist
Write-Host "Ensuring ECR repository exists..." -ForegroundColor Yellow

$repoName = "$StackName-web"
Write-Host "Checking repository: $repoName" -ForegroundColor Blue

$ErrorActionPreference = "Continue"
if ($AWSProfile) {
    $null = aws ecr describe-repositories --repository-names $repoName --region $Region --profile $AWSProfile --no-cli-pager 2>&1
} else {
    $null = aws ecr describe-repositories --repository-names $repoName --region $Region --no-cli-pager 2>&1
}
$ErrorActionPreference = "Stop"

if ($LASTEXITCODE -ne 0) {
    Write-Host "Creating repository: $repoName" -ForegroundColor Yellow
    if ($AWSProfile) {
        aws ecr create-repository --repository-name $repoName --region $Region --profile $AWSProfile --no-cli-pager | Out-Null
    } else {
        aws ecr create-repository --repository-name $repoName --region $Region --no-cli-pager | Out-Null
    }
} else {
    Write-Host "Repository exists: $repoName" -ForegroundColor Green
}

# Get account ID for building repository URI
if ($AWSProfile) {
    $accountId = (aws sts get-caller-identity --profile $AWSProfile --query Account --output text --no-cli-pager)
} else {
    $accountId = (aws sts get-caller-identity --query Account --output text --no-cli-pager)
}

# Build repository URI
$webRepo = "$accountId.dkr.ecr.$Region.amazonaws.com/$StackName-web"
$ecrLoginCommand = "aws ecr get-login-password --region $Region | docker login --username AWS --password-stdin $accountId.dkr.ecr.$Region.amazonaws.com"

# Convert parameters to CLI format
$parametersList = @()
foreach ($param in $parameters) {
    # Ensure ParameterValue is treated as a string, even if it contains commas
    $value = $param.ParameterValue
    if ($value -is [array]) {
        # If PowerShell converted it to an array, join it back
        $value = $value -join ','
    }
    # Escape the value by wrapping in quotes if it contains commas
    if ($value -match ',') {
        $parametersList += "ParameterKey=$($param.ParameterKey),ParameterValue=`"$value`""
    } else {
        $parametersList += "ParameterKey=$($param.ParameterKey),ParameterValue=$value"
    }
}

Write-Host "Web Repository: $webRepo"

# Login to ECR and build/push image BEFORE deploying main stack
Write-Host "Logging into ECR..." -ForegroundColor Yellow
if ($AWSProfile) {
    $ecrLoginWithProfile = $ecrLoginCommand -replace "aws ecr get-login-password", "aws ecr get-login-password --profile $AWSProfile"
    Invoke-Expression $ecrLoginWithProfile
} else {
    Invoke-Expression $ecrLoginCommand
}

# Build and push image
Write-Host "Building and pushing Docker image..." -ForegroundColor Yellow

# Web Frontend Image
Write-Host "Building Frontend image..." -ForegroundColor Blue
Push-Location ".."
docker build -t "mia-frontend:${ImageTag}" -f deployment-frontend/Dockerfile .
docker tag "mia-frontend:${ImageTag}" "${webRepo}:${ImageTag}"
docker push "${webRepo}:${ImageTag}"
Write-Host "Frontend image pushed successfully" -ForegroundColor Green
Pop-Location

# Now deploy the main CloudFormation stack
Write-Host "Deploying main CloudFormation stack..." -ForegroundColor Yellow

# Check if main stack exists
$stackExists = $false
$ErrorActionPreference = "Continue"
if ($AWSProfile) {
    $null = aws cloudformation describe-stacks --stack-name $StackName --region $Region --profile $AWSProfile --no-cli-pager 2>&1
} else {
    $null = aws cloudformation describe-stacks --stack-name $StackName --region $Region --no-cli-pager 2>&1
}
$ErrorActionPreference = "Stop"

if ($LASTEXITCODE -eq 0) {
    $stackExists = $true
    Write-Host "Stack exists - updating..." -ForegroundColor Yellow
} else {
    Write-Host "Stack does not exist - creating..." -ForegroundColor Yellow
}

if ($stackExists) {
    # Update existing stack
    if ($AWSProfile) {
        $ErrorActionPreference = "Continue"
        $updateOutput = aws cloudformation update-stack `
            --stack-name $StackName `
            --template-body file://mia-frontend.yaml `
            --parameters file://parameters.json `
            --capabilities CAPABILITY_IAM `
            --region $Region `
            --profile $AWSProfile `
            --no-cli-pager 2>&1
        $updateExitCode = $LASTEXITCODE
        $ErrorActionPreference = "Stop"
        
        if ($updateExitCode -eq 0) {
            Write-Host "Waiting for stack update to complete..." -ForegroundColor Yellow
            aws cloudformation wait stack-update-complete --stack-name $StackName --region $Region --profile $AWSProfile --no-cli-pager
            
            # Force ECS service to update with new task definition
            Write-Host "Forcing ECS service to update with new image..." -ForegroundColor Yellow
            $clusterName = "$StackName-cluster"
            $serviceName = "$StackName-web"
            
            Write-Host "Updating service: $serviceName" -ForegroundColor Blue
            try {
                aws ecs update-service --cluster $clusterName --service $serviceName --force-new-deployment --region $Region --profile $AWSProfile --no-cli-pager 2>&1 | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Service $serviceName update initiated" -ForegroundColor Green
                } else {
                    Write-Host "Warning: Failed to update service $serviceName" -ForegroundColor Yellow
                }
            } catch {
                Write-Host "Warning: Error updating service $serviceName - $($_.Exception.Message)" -ForegroundColor Yellow
            }
            
            Write-Host "Service is updating in the background. This may take a few minutes..." -ForegroundColor Yellow
            Write-Host "You can monitor progress in the AWS Console." -ForegroundColor Blue
        } elseif ($updateOutput -match "No updates are to be performed") {
            Write-Host "No CloudFormation updates needed - infrastructure is up to date" -ForegroundColor Green
            
            # Still force ECS service to update with new image
            Write-Host "Forcing ECS service to update with new image..." -ForegroundColor Yellow
            $clusterName = "$StackName-cluster"
            $serviceName = "$StackName-web"
            
            Write-Host "Updating service: $serviceName" -ForegroundColor Blue
            try {
                aws ecs update-service --cluster $clusterName --service $serviceName --force-new-deployment --region $Region --profile $AWSProfile --no-cli-pager 2>&1 | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Service $serviceName update initiated" -ForegroundColor Green
                } else {
                    Write-Host "Warning: Failed to update service $serviceName" -ForegroundColor Yellow
                }
            } catch {
                Write-Host "Warning: Error updating service $serviceName - $($_.Exception.Message)" -ForegroundColor Yellow
            }
            
            Write-Host "Service is updating in the background. This may take a few minutes..." -ForegroundColor Yellow
            Write-Host "You can monitor progress in the AWS Console." -ForegroundColor Blue
        } else {
            Write-Host "CloudFormation update failed:" -ForegroundColor Red
            Write-Host $updateOutput -ForegroundColor Red
            exit 1
        }
    } else {
        $ErrorActionPreference = "Continue"
        $updateOutput = aws cloudformation update-stack `
            --stack-name $StackName `
            --template-body file://mia-frontend.yaml `
            --parameters file://parameters.json `
            --capabilities CAPABILITY_IAM `
            --region $Region `
            --no-cli-pager 2>&1
        $updateExitCode = $LASTEXITCODE
        $ErrorActionPreference = "Stop"
        
        if ($updateExitCode -eq 0) {
            Write-Host "Waiting for stack update to complete..." -ForegroundColor Yellow
            aws cloudformation wait stack-update-complete --stack-name $StackName --region $Region --no-cli-pager
            
            # Force ECS service to update with new task definition
            Write-Host "Forcing ECS service to update with new image..." -ForegroundColor Yellow
            $clusterName = "$StackName-cluster"
            $serviceName = "$StackName-web"
            
            Write-Host "Updating service: $serviceName" -ForegroundColor Blue
            try {
                aws ecs update-service --cluster $clusterName --service $serviceName --force-new-deployment --region $Region --no-cli-pager 2>&1 | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Service $serviceName update initiated" -ForegroundColor Green
                } else {
                    Write-Host "Warning: Failed to update service $serviceName" -ForegroundColor Yellow
                }
            } catch {
                Write-Host "Warning: Error updating service $serviceName - $($_.Exception.Message)" -ForegroundColor Yellow
            }
            
            Write-Host "Service is updating in the background. This may take a few minutes..." -ForegroundColor Yellow
            Write-Host "You can monitor progress in the AWS Console." -ForegroundColor Blue
        } elseif ($updateOutput -match "No updates are to be performed") {
            Write-Host "No CloudFormation updates needed - infrastructure is up to date" -ForegroundColor Green
            
            # Still force ECS service to update with new image
            Write-Host "Forcing ECS service to update with new image..." -ForegroundColor Yellow
            $clusterName = "$StackName-cluster"
            $serviceName = "$StackName-web"
            
            Write-Host "Updating service: $serviceName" -ForegroundColor Blue
            try {
                aws ecs update-service --cluster $clusterName --service $serviceName --force-new-deployment --region $Region --no-cli-pager 2>&1 | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Service $serviceName update initiated" -ForegroundColor Green
                } else {
                    Write-Host "Warning: Failed to update service $serviceName" -ForegroundColor Yellow
                }
            } catch {
                Write-Host "Warning: Error updating service $serviceName - $($_.Exception.Message)" -ForegroundColor Yellow
            }
            
            Write-Host "Service is updating in the background. This may take a few minutes..." -ForegroundColor Yellow
            Write-Host "You can monitor progress in the AWS Console." -ForegroundColor Blue
        } else {
            Write-Host "CloudFormation update failed:" -ForegroundColor Red
            Write-Host $updateOutput -ForegroundColor Red
            exit 1
        }
    }
} else {
    # Create new stack
    if ($AWSProfile) {
        aws cloudformation create-stack `
            --stack-name $StackName `
            --template-body file://mia-frontend.yaml `
            --parameters file://parameters.json `
            --capabilities CAPABILITY_IAM `
            --region $Region `
            --profile $AWSProfile `
            --no-cli-pager
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Waiting for stack creation to complete..." -ForegroundColor Yellow
            aws cloudformation wait stack-create-complete --stack-name $StackName --region $Region --profile $AWSProfile --no-cli-pager
        }
    } else {
        aws cloudformation create-stack `
            --stack-name $StackName `
            --template-body file://mia-frontend.yaml `
            --parameters file://parameters.json `
            --capabilities CAPABILITY_IAM `
            --region $Region `
            --no-cli-pager
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Waiting for stack creation to complete..." -ForegroundColor Yellow
            aws cloudformation wait stack-create-complete --stack-name $StackName --region $Region --no-cli-pager
        }
    }
}

# Skip the general error check since we've handled CloudFormation errors above
Write-Host "CloudFormation stack deployment completed" -ForegroundColor Green

# Get main stack outputs for final display
Write-Host "Getting deployment information..." -ForegroundColor Yellow
if ($AWSProfile) {
    $outputs = aws cloudformation describe-stacks --stack-name $StackName --region $Region --profile $AWSProfile --query "Stacks[0].Outputs" --no-cli-pager | ConvertFrom-Json
} else {
    $outputs = aws cloudformation describe-stacks --stack-name $StackName --region $Region --query "Stacks[0].Outputs" --no-cli-pager | ConvertFrom-Json
}

# Display results
Write-Host ""
Write-Host "Deployment completed successfully!" -ForegroundColor Green
Write-Host "=================================="
Write-Host "Deployment Information:" -ForegroundColor Blue

$albDns = ($outputs | Where-Object { $_.OutputKey -eq "ALBDNSName" }).OutputValue
$frontendDomain = ($outputs | Where-Object { $_.OutputKey -eq "FrontendDomain" }).OutputValue

Write-Host "ALB DNS Name: $albDns"
Write-Host "Frontend Domain: $frontendDomain"
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Configure your CloudFlare DNS:"
Write-Host "   - Create CNAME: $frontendDomain -> $albDns"
Write-Host "2. Test your deployment:"
Write-Host "   - Frontend: curl http://$frontendDomain"
Write-Host "   - Health Check: curl http://$frontendDomain/health"
Write-Host ""
Write-Host "Monitor your deployment:" -ForegroundColor Blue
Write-Host "AWS Console: https://console.aws.amazon.com/ecs/home?region=$Region#/clusters/$StackName-cluster"

Write-Host "All done!" -ForegroundColor Green