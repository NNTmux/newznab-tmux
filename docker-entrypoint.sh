#!/bin/sh
set -e
# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from environment variables..."
    envsubst < .env.dist > .env
fi
if [ "$1" != 'php' ] && [ "$1" != 'sh' ]; then
    # Install dependencies if not already installed
    if [ ! -d 'vendor/' ]; then
        echo "Installing dependencies via Composer..."
        composer install --prefer-dist --no-progress --no-interaction
    fi


    # Check and wait for the database to be ready
    if grep -q ^DB_HOST= .env; then
        echo "Waiting for the database to be ready..."
        ATTEMPTS_LEFT_TO_REACH_DATABASE=60
        until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php artisan db:show 2>&1); do
            if [ $? -ne 0 ]; then
                # Stop attempting in case of an error
                ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
                echo "Database not ready yet. Attempts left: $ATTEMPTS_LEFT_TO_REACH_DATABASE."
                sleep 1
            else
                break
            fi
        done

        if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
            echo "Failed to connect to the database:"
            echo "$DATABASE_ERROR"
            exit 1
        else
            echo "Database is now ready."
        fi
    fi

    if [ ! -f '_install/install.lock' ]; then
        echo "Clearing application cache..."
        php artisan cache:clear
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear

        echo "Caching configuration and routes..."
        php artisan config:cache
        php artisan route:cache

        echo "Creating folders structure"

        mkdir -p /app/storage/public
        mkdir -p /app/storage/covers/anime
        mkdir -p /app/storage/covers/audio
        mkdir -p /app/storage/covers/audiosample
        mkdir -p /app/storage/covers/book
        mkdir -p /app/storage/covers/console
        mkdir -p /app/storage/covers/games
        mkdir -p /app/storage/covers/movies
        mkdir -p /app/storage/covers/music
        mkdir -p /app/storage/covers/preview
        mkdir -p /app/storage/covers/sample
        mkdir -p /app/storage/covers/tvrage
        mkdir -p /app/storage/covers/tvshows
        mkdir -p /app/storage/covers/video
        mkdir -p /app/storage/covers/xxx
        mkdir -p /app/storage/nzb

        # Run Laravel-specific post-installation commands
        echo "NNTmux installation..."
        php artisan nntmux:install --yes
        # Set permissions for storage and bootstrap/cache directories
        echo "Setting permissions on storage and bootstrap/cache directories..."
        chmod -R 775 bootstrap/cache
        chmod -R 777 storage resources
        chown -R www-data:www-data storage bootstrap/cache resources
        php artisan nntmux:create-es-indexes
        php artisan nntmux:populate --elastic --releases
        php artisan nntmux:populate --elastic --predb
    fi
fi

# Run the PHP entry point with arguments
exec docker-php-entrypoint "$@"
