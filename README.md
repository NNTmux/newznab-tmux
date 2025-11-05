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
- Intelligent local caching of metadata
- Tmux engine for thread, database, and performance monitoring
- Image and video sample support
- Modern frontend stack: Vite, Tailwind CSS, Vue 3
- Dockerized development via Laravel Sail

## Prerequisites

- System administration experience (Linux recommended)
- PHP 8.3+ and required extensions
- MariaDB 10+ or MySQL 8+ (Postgres not supported)
- Node.js for frontend assets
- Recommended: 64GB RAM, 8+ cores, 320GB+ disk space

## Database Tuning

For large-scale indexing, tune your database for performance. Use [mysqltuner.pl](http://mysqltuner.pl) and set `innodb_buffer_pool_size` appropriately (1-2GB per million releases).

For further tuning advice, see:
- [How do I tune MySQL for performance?](https://stackoverflow.com/questions/1047497/how-do-i-tune-mysql-for-performance)
- [How to optimize MySQL server performance?](https://stackoverflow.com/questions/600032/how-to-optimize-mysql-server-performance)
- [How to optimize MariaDB for large databases?](https://stackoverflow.com/questions/32421909/how-to-optimize-mariadb-for-large-databases)

## Installation

Follow the [Ubuntu install guide](https://github.com/NNTmux/newznab-tmux/wiki/Ubuntu-Install-guide) and [Composer install guide](https://github.com/NNTmux/newznab-tmux/wiki/Installing-Composer).

## Docker & Development

NNTmux uses Laravel Sail for Docker-based development. To start:

1. Edit your `.env` file for configuration.
2. Run:
   ```
   ./sail up -d
   ```

Frontend assets use Vite, Tailwind CSS, and Vue 3. See `package.json` for scripts and dependencies.

## Contribution & Support

- Active development: see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines
- Support: [Discord](https://discord.gg/GjgGSzkrjh)

## License

NNTmux is GPL v3. See LICENSE for details. External libraries include their own licenses in respective folders.
