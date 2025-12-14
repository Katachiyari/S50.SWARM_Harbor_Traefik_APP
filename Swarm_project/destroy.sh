#!/bin/bash

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}⚠️  Destruction de toutes les VMs...${NC}"
echo ""

vagrant destroy -f

echo ""
echo -e "${GREEN}✓ VMs détruites${NC}"
echo "État : vagrant status"
