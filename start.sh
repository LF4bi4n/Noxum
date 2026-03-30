cd /c/xampp/htdocs/noxum
cat > start.sh << 'EOF'
#!/bin/sh
PORT=$(printenv PORT)
echo "Starting PHP on port: $PORT"
php -S 0.0.0.0:$PORT router.php
EOF