#!/bin/bash

# Bash script to verify Docker builds for all services

compose_files=(
    "docker-compose.yml"
    "docker-compose.coolify.yml"
    "docker-compose.coolify.monitoring.yml"
    "docker-compose.coolify.app.yml"
)

all_passed=true

# Text colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
NC='\033[0m'

echo -e "${CYAN}Starting build verification...${NC}"

for file in "${compose_files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${YELLOW}Verifying builds in $file...${NC}"
        # --pull ensures we have latest base images
        docker compose -f "$file" build --pull
        
        if [ $? -ne 0 ]; then
            echo -e "${RED}FAILED: Build verification for $file failed.${NC}"
            all_passed=false
        else
            echo -e "${GREEN}PASSED: Build verification for $file succeeded.${NC}"
        fi
    else
        echo -e "${GRAY}Skipping $file (not found).${NC}"
    fi
done

if [ "$all_passed" = true ]; then
    echo -e "\n${GREEN}All builds verified successfully!${NC}"
    exit 0
else
    echo -e "\n${RED}Some builds failed verification.${NC}"
    exit 1
fi
