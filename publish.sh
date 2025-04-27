#!/bin/bash
# Quick script to publish the Livewire URL fix to the production server

# Modify these variables to match your hosting setup
SERVER="yourusername@yourserver.com"
REMOTE_PATH="/path/to/your/barmada/directory"

# Files to upload
echo "Uploading fixed files..."

# Upload the Livewire URL fix
scp -v public/script-interceptor.js public/livewire-url-fix.js "$SERVER:$REMOTE_PATH/public/"

# Upload the modified config
scp -v config/livewire.php "$SERVER:$REMOTE_PATH/config/"

# Upload the modified app provider
scp -v app/Providers/AppServiceProvider.php "$SERVER:$REMOTE_PATH/app/Providers/"

# Upload the modified layout
scp -v resources/views/layouts/app.blade.php "$SERVER:$REMOTE_PATH/resources/views/layouts/"

echo "Files uploaded. Please update your SERVER and REMOTE_PATH variables in this script before running it."
echo "Done. If you still see issues, you may need to clear the Laravel cache on the server with:"
echo "  - php artisan view:clear"
echo "  - php artisan config:clear"
echo "  - php artisan cache:clear"
echo "  - php artisan route:clear" 