#!/bin/bash

# FunCaptcha Server - Docker Test Script
# Tests the Docker build and health endpoint

set -e

echo "🔧 FunCaptcha Server - Docker Test"
echo "=================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed${NC}"
    echo "Please install Docker from https://docs.docker.com/get-docker/"
    exit 1
fi

echo -e "${GREEN}✅ Docker found${NC}"
echo ""

# Step 1: Build the image
echo "📦 Step 1: Building Docker image..."
if docker build -t funcaptcha-server:latest .; then
    echo -e "${GREEN}✅ Build successful${NC}"
else
    echo -e "${RED}❌ Build failed${NC}"
    exit 1
fi
echo ""

# Step 2: Run the container
echo "🚀 Step 2: Starting container..."
CONTAINER_ID=$(docker run -d -p 8080:8080 funcaptcha-server:latest)
echo -e "${GREEN}✅ Container started: $CONTAINER_ID${NC}"
echo ""

# Wait for service to start
echo "⏳ Waiting for services to initialize..."
sleep 5

# Step 3: Test health endpoint
echo "🧪 Step 3: Testing health endpoint..."
MAX_RETRIES=10
RETRY_COUNT=0
SUCCESS=false

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if curl -s http://localhost:8080/arkoselabs/health > /dev/null 2>&1; then
        RESPONSE=$(curl -s http://localhost:8080/arkoselabs/health)
        echo -e "${GREEN}✅ Health check passed${NC}"
        echo "Response: $RESPONSE"
        SUCCESS=true
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
            echo "⏳ Retry $RETRY_COUNT/$MAX_RETRIES..."
            sleep 2
        fi
    fi
done

if [ "$SUCCESS" = false ]; then
    echo -e "${RED}❌ Health check failed after $MAX_RETRIES attempts${NC}"
    echo "Container logs:"
    docker logs $CONTAINER_ID
    docker stop $CONTAINER_ID
    exit 1
fi

echo ""
echo "📊 Container Information:"
echo "========================"
echo "Image: funcaptcha-server:latest"
echo "Container ID: $CONTAINER_ID"
echo "Port: 8080"
echo "Health Endpoint: http://localhost:8080/arkoselabs/health"
echo ""

# Step 4: Show available endpoints
echo "📡 Available Endpoints:"
echo "======================"
echo "GET  /arkoselabs/health     - Health check"
echo "POST /arkoselabs/gfct       - Get challenge"
echo "POST /arkoselabs/fcca       - Submit answer"
echo "GET  /arkoselabs/init_load  - Initialize"
echo "GET  /arkoselabs/rtigimage  - Get image"
echo "POST /arkoselabs/pkeytoken  - Get token"
echo ""

# Step 5: Cleanup prompt
echo "🛑 Container Management:"
echo "======================"
echo "To stop the container:"
echo "  docker stop $CONTAINER_ID"
echo ""
echo "To remove the container:"
echo "  docker rm $CONTAINER_ID"
echo ""
echo "To remove the image:"
echo "  docker rmi funcaptcha-server:latest"
echo ""

# Cleanup
echo "🧹 Stopping container (test complete)..."
docker stop $CONTAINER_ID > /dev/null
echo -e "${GREEN}✅ Test completed successfully!${NC}"
echo ""
echo "✨ Your server is ready for deployment to Render!"
