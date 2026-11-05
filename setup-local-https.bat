@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-local-https.ps1" %*
