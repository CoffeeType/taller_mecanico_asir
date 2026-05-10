@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-jmeter-ui.ps1"
if errorlevel 1 (
  echo.
  echo Error al iniciar la UI de JMeter. Revisa el mensaje anterior.
  pause
)
