@echo off
setlocal
set XDEBUG_MODE=coverage
php vendor/bin/phpunit --coverage-text=coverage.txt --coverage-clover=coverage.xml %*
if errorlevel 1 exit /b 1
php tests/check-coverage.php
exit /b %ERRORLEVEL%
