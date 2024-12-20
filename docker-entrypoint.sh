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
        echo "Creating folders structure"
        mkdir -p /app/storage/app/public
        mkdir -p "$COVERS_PATH/anime"
        mkdir -p "$COVERS_PATH/audio"
        mkdir -p "$COVERS_PATH/audiosample"
        mkdir -p "$COVERS_PATH/book"
        mkdir -p "$COVERS_PATH/console"
        mkdir -p "$COVERS_PATH/games"
        mkdir -p "$COVERS_PATH/movies"
        mkdir -p "$COVERS_PATH/music"
        mkdir -p "$COVERS_PATH/preview"
        mkdir -p "$COVERS_PATH/sample"
        mkdir -p "$COVERS_PATH/tvrage"
        mkdir -p "$COVERS_PATH/tvshows"
        mkdir -p "$COVERS_PATH/video"
        mkdir -p "$COVERS_PATH/xxx"
        mkdir -p "$PATH_TO_NZBS"
        mkdir -p "$TEMP_UNRAR_PATH"
        mkdir -p "$TEMP_UNZIP_PATH"
        # Set permissions for storage and bootstrap/cache directories
        echo "Setting permissions on storage and bootstrap/cache directories..."
        chmod -R 775 bootstrap/cache
        chmod -R 777 storage resources
        chown -R www-data:www-data storage bootstrap/cache resources

        echo "Clearing application cache..."
        php artisan cache:clear
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear

        echo "Caching configuration and routes..."
        php artisan config:cache
        php artisan route:cache

        # Run Laravel-specific post-installation commands
        echo "NNTmux installation..."
        php artisan nntmux:install --yes

        if [ "${ELASTICSEARCH_ENABLED}" == "true" ]; then
            echo "Elasticsearch initialisation"
            php artisan nntmux:create-es-indexes
            php artisan nntmux:populate --elastic --releases
            php artisan nntmux:populate --elastic --predb
        else
            echo "Manticore initialisation"
            php artisan nntmux:populate --manticore --releases
            php artisan nntmux:populate --manticore --predb
        fi
    fi
fi

# Run the PHP entry point with arguments
exec docker-php-entrypoint "$@"
