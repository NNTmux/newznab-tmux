<p align="center">
    <a href="https://packagist.org/packages/nntmux/newznab-tmux"><img src="https://poser.pugx.org/nntmux/newznab-tmux/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/nntmux/newznab-tmux"><img src="https://poser.pugx.org/nntmux/newznab-tmux/license.svg" alt="License"></a>
    <a href="https://www.patreon.com/bePatron?u=6160908"><img src="https://c5.patreon.com/external/logo/become_a_patron_button.png" alt="Become a Patron!" height="20"></a>
</p>

# NNTmux

NNTmux is a modern Usenet indexer built on Laravel, designed for high performance and scalability. It automatically scans Usenet, collects headers, and organizes them into searchable releases. The project is actively maintained and features multi-threaded processing, advanced search, and a web-based front-end with API access.

This project is a fork of [newznab plus](https://github.com/anth0/nnplus) and [nZEDb](https://github.com/nZEDb/nZEDb), with significant improvements:

- Multi-threaded processing (header retrieval, release creation, post-processing)
- Advanced search (name, subject, category, post-date)
- Intelligent local caching of metadata (TMDB, TVDB, TVMaze, Trakt, IMDB)
- Tmux engine for thread, database, and performance monitoring
- Image and video sample support
- Modern frontend stack: Vite, Tailwind CSS, Vue 3
- Full-text search via Elasticsearch or ManticoreSearch
- Dockerized development via Laravel Sail
- RESTful API compatible with newznab standard

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Search Engines](#search-engines)
- [Console Commands](#console-commands)
- [IRC Pre Channels](#irc-pre-channels)
- [TV & Movie Processing](#tv--movie-processing)
- [API](#api)
- [Docker & Development](#docker--development)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Prerequisites

- System administration experience (Linux recommended)
- PHP 8.3+ with extensions: curl, json, pdo_mysql, openssl, mbstring, xml, zip, gd, intl, pcntl
- MariaDB 10.6+ or MySQL 8+ (PostgreSQL not supported)
- Composer 2.x
- Node.js 18+ and npm for frontend assets
- nginx or Apache web server
- Optional: tmux (for multi-threaded processing)
- Optional: unrar, 7zip, ffmpeg, mediainfo (for post-processing)

### Recommended Hardware

| Scale | RAM | CPU | Disk |
|-------|-----|-----|------|
| Small (<1M releases) | 16GB | 4 cores | 100GB SSD |
| Medium (1-10M releases) | 32GB | 8 cores | 250GB SSD |
| Large (10M+ releases) | 64GB+ | 16+ cores | 500GB+ NVMe |

## Installation

### Quick Start

1. Clone the repository:
   ```bash
   git clone https://github.com/NNTmux/newznab-tmux.git
   cd newznab-tmux
   ```

2. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Copy and configure environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure your `.env` file (see [Configuration](#configuration))

5. Run database migrations:
   ```bash
   php artisan migrate
   ```

6. Install frontend assets:
   ```bash
   npm install
   npm run build
   ```

7. Set permissions:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

For detailed installation instructions, see the [Ubuntu Install Guide](https://github.com/NNTmux/newznab-tmux/wiki/Ubuntu-Install-guide).

## Configuration

### Essential .env Settings

```env
# Application
APP_NAME=NNTmux
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nntmux
DB_USERNAME=nntmux
DB_PASSWORD=your_secure_password

# Usenet Server
NNTP_SERVER=news.your-provider.com
NNTP_PORT=563
NNTP_SSLENABLED=true
NNTP_USERNAME=your_username
NNTP_PASSWORD=your_password

# API Keys (obtain from respective services)
TMDB_API_KEY=your_tmdb_key
TVDB_API_KEY=your_tvdb_key
TVMAZE_API_KEY=              # Optional, no key required for basic usage
TRAKT_CLIENT_ID=your_trakt_client_id
TRAKT_CLIENT_SECRET=your_trakt_secret
OMDB_API_KEY=your_omdb_key
FANART_API_KEY=your_fanart_key
GIANTBOMB_API_KEY=your_giantbomb_key

# Search Engine (choose one)
SEARCH_ENGINE=manticore      # Options: manticore, elasticsearch
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9308
# Or for Elasticsearch:
# ELASTICSEARCH_HOST=127.0.0.1
# ELASTICSEARCH_PORT=9200

# IRC Pre Scraping (optional)
SCRAPE_IRC_SERVER=irc.synirc.net
SCRAPE_IRC_PORT=6697
SCRAPE_IRC_TLS=true
SCRAPE_IRC_USERNAME=YourUniqueNick
```

### Queue Configuration

NNTmux uses Laravel's queue system for background processing:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

For high-volume processing, consider using [Laravel Horizon](https://github.com/NNTmux/newznab-tmux/wiki/Laravel-Horizon).

## Database Setup

### Initial Setup

```bash
# Run migrations
php artisan migrate

# Seed initial data (categories, groups, etc.)
php artisan db:seed
```

### Database Tuning

For large-scale indexing, proper database tuning is critical. Key settings:

```ini
# /etc/mysql/mariadb.conf.d/99-nntmux.cnf
[mysqld]
innodb_buffer_pool_size = 8G          # 50-70% of available RAM
innodb_log_file_size = 1G
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_type = 0
query_cache_size = 0
max_connections = 500
tmp_table_size = 256M
max_heap_table_size = 256M
```

Use [mysqltuner.pl](http://mysqltuner.pl) for recommendations:
```bash
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/mysqltuner.pl
perl mysqltuner.pl
```

### Collation Migration

For proper Unicode support (emojis, special characters):
```bash
php artisan nntmux:convert-collation utf8mb4_unicode_ci
```

## Search Engines

NNTmux supports two full-text search engines:

### ManticoreSearch (Recommended)

```bash
# Install ManticoreSearch
wget https://repo.manticoresearch.com/manticore-repo.noarch.deb
sudo dpkg -i manticore-repo.noarch.deb
sudo apt update
sudo apt install manticore

# Configure in .env
SEARCH_ENGINE=manticore
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9308

# Build indexes
php artisan nntmux:index-manticore
```

### Elasticsearch

```bash
# Install Elasticsearch
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list
sudo apt update && sudo apt install elasticsearch

# Configure in .env
SEARCH_ENGINE=elasticsearch
ELASTICSEARCH_HOST=127.0.0.1
ELASTICSEARCH_PORT=9200

# Build indexes
php artisan nntmux:index-elasticsearch
```

## Console Commands

NNTmux provides numerous Artisan commands for management and maintenance.

### Release Processing

```bash
# Start the tmux processing engine
php artisan tmux:start

# Stop tmux processing
php artisan tmux:stop

# Process releases manually
php artisan nntmux:process-releases

# Update release names
php artisan nntmux:update-releases
```

### TV & Movie Processing

```bash
# Reprocess unmatched TV releases
php artisan nntmux:reprocess-tv

# Refresh TV episodes for a specific show
php artisan tv:refresh-episodes --video-id=12345

# Refresh all shows with missing seasons
php artisan tv:refresh-episodes --missing-seasons

# Search by title and refresh
php artisan tv:refresh-episodes --title="Show Name"

# Delete existing and re-fetch from specific provider
php artisan tv:refresh-episodes --video-id=12345 --provider=tmdb --delete-existing

# Dry run to preview changes
php artisan tv:refresh-episodes --missing-seasons --dry-run
```

### Database Maintenance

```bash
# Optimize tables
php artisan nntmux:optimize-tables

# Clean old releases
php artisan nntmux:cleanup --days=365

# Purge Laravel Pulse data (if using Pulse)
php artisan pulse:purge

# Clear application caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### User Management

```bash
# Create admin user
php artisan nntmux:create-admin

# Reset user password
php artisan nntmux:reset-password --email=user@example.com
```

## IRC Pre Channels

NNTmux can scrape IRC pre channels for early release information.

### Active IRC Networks

| Network | Server | Ports | SSL |
|---------|--------|-------|-----|
| SynIRC | irc.synirc.net | 6667, 6697, 7001 | Yes |
| p2p-net | irc.p2p-net.eu | 6697, 7000 | Yes |
| lillesky | irc.lillesky.org | 6667, 7000 | Yes |
| Abjects | irc.abjects.net | 6667, 6697 | Yes |

### Configuration

```env
SCRAPE_IRC_SERVER=irc.synirc.net
SCRAPE_IRC_PORT=6697
SCRAPE_IRC_TLS=true
SCRAPE_IRC_USERNAME=YourUniqueNickname
SCRAPE_IRC_REALNAME=Your Name
SCRAPE_IRC_CHANNELS=#PreNNTmux,#nZEDbPRE
```

### PreDB API Alternatives

Instead of or in addition to IRC, you can use PreDB APIs:

- **predb.ovh** - Public API with RSS feeds: `https://predb.ovh/api/v1/`
- **predb.net** - Comprehensive API: `https://predb.net/api-documentation/`
- **predb.me** - Public PreDB with search
- **predb.live** - NFO database included

## TV & Movie Processing

NNTmux fetches metadata from multiple sources with fallback support.

### Provider Priority

1. **TMDB** (The Movie Database) - Primary for movies and TV
2. **TVDB** (TheTVDB) - Fallback for TV shows
3. **TVMaze** - Additional TV metadata
4. **Trakt** - User ratings and additional data
5. **OMDB/IMDB** - Movie ratings and legacy data

### Episode Matching

The TV processing pipeline automatically:
- Matches releases to shows by name
- Downloads episode information for matched shows
- Detects and fetches missing seasons when processing new releases

If episodes aren't matching properly:

```bash
# Check what video ID a show has
php artisan tinker
>>> DB::table('videos')->where('title', 'like', '%Show Name%')->get(['id', 'title', 'tmdb']);

# Refresh episodes for that show
php artisan tv:refresh-episodes --video-id=<ID>
```

## API

NNTmux provides a newznab-compatible API for integration with download clients and media managers (Sonarr, Radarr, etc.).

### Endpoints

```
GET /api?t=caps           # Server capabilities
GET /api?t=search&q=      # Search releases
GET /api?t=tvsearch       # TV search
GET /api?t=movie          # Movie search
GET /api?t=music          # Music search
GET /api?t=book           # Book search
GET /api?t=details&id=    # Release details
GET /api?t=getnzb&id=     # Download NZB
```

### API Keys

Users obtain API keys from their profile page. Configure per-user rate limits in the admin panel.

For detailed API documentation, see the [NNTmux API v2 Wiki](https://github.com/NNTmux/newznab-tmux/wiki/NNTmux-API-version-2).

## Docker & Development

### Laravel Sail (Docker)

```bash
# Start containers
./vendor/bin/sail up -d

# Run artisan commands
./vendor/bin/sail artisan migrate

# Stop containers
./vendor/bin/sail down
```

### Frontend Development

```bash
# Install dependencies
npm install

# Development with hot reload
npm run dev

# Production build
npm run build
```

### Code Style

```bash
# PHP formatting (Laravel Pint)
./vendor/bin/pint

# JavaScript/Vue linting
npm run lint
```

## Troubleshooting

### Common Issues

**Releases not being created:**
```bash
# Check for errors in logs
tail -f storage/logs/laravel.log

# Verify NNTP connection
php artisan nntmux:test-nntp

# Check group status
php artisan tinker
>>> DB::table('usenet_groups')->where('active', 1)->count();
```

**TV/Movie not matching:**
```bash
# Reprocess specific release
php artisan nntmux:reprocess-release --id=12345

# Check API keys
php artisan nntmux:test-apis
```

**High disk usage from Laravel Pulse:**
```bash
# Check Pulse table sizes
mysql -e "SELECT table_name, ROUND(data_length/1024/1024, 2) as 'Size (MB)' FROM information_schema.tables WHERE table_schema='nntmux' AND table_name LIKE 'pulse%';"

# Purge old data
php artisan pulse:purge
```

**Permission issues:**
```bash
# Fix ownership
sudo chown -R www-data:www-data storage bootstrap/cache

# Fix permissions
sudo chmod -R 775 storage bootstrap/cache
```

### Logs

- **Application logs:** `storage/logs/laravel.log`
- **Tmux logs:** `misc/update/tmux/logs/`
- **nginx logs:** `/var/log/nginx/`
- **PHP-FPM logs:** `/var/log/php-fpm/`

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Run tests: `php artisan test`
5. Submit a pull request

## Support

- **Discord:** [Join our server](https://discord.gg/GjgGSzkrjh)
- **GitHub Issues:** [Report bugs](https://github.com/NNTmux/newznab-tmux/issues)
- **Wiki:** [Documentation](https://github.com/NNTmux/newznab-tmux/wiki)

## License

NNTmux is open-source software licensed under the [GPL v3](LICENSE). External libraries include their own licenses in their respective folders.
