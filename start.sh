cat > start.sh << 'EOF'
#!/bin/sh
exec php -S 0.0.0.0:$PORT router.php
EOF