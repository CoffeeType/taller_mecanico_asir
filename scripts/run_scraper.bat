@echo off
echo Instalando dependencias de Node.js...
cd ..
call npm install

echo.
echo Ejecutando scraper de noticias...
call npm run scrape

echo.
echo Presiona cualquier tecla para cerrar...
pause >nul
