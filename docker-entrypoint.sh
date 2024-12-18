#!/bin/sh
set -e
if [ "$1" != 'php' ] && [ "$1" != 'sh' ]; then
    # Install dependencies if not already installed
    if [ ! -d 'vendor/' ]; then
        echo "Installing dependencies via Composer..."
        composer install --prefer-dist --no-progress --no-interaction
    fi

    # Create .env file if it doesn't exist
    if [ ! -f .env ]; then
        echo "Creating .env file from environment variables..."
        envsubst < .env.dist > .env
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
    # Set permissions for storage and bootstrap/cache directories
    echo "Setting permissions on storage and bootstrap/cache directories..."
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
fi

# Run the PHP entry point with arguments
exec docker-php-entrypoint "$@"
