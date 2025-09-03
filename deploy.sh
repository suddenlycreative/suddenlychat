#!/bin/bash
cd /www/wwwroot/chat.suddenly.com.br/core
git add .
git commit -m "Atualização automática em $(date '+%d/%m/%Y %H:%M')"
git push

