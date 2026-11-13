@echo off
set PATH=C:\php;%PATH%
echo ===================================================
echo   Starting NAD Tool (Laravel + SQLite Local)
echo   Open in browser: http://127.0.0.1:8000
echo   Press Ctrl+C to terminate the server
echo ===================================================
php artisan serve --host=127.0.0.1 --port=8000
