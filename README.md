# PrestaShop Docker

Deploy a fully functional PrestaShop e-commerce store on [42helv.com](https://42helv.com).

## Features

- **One-Click Deploy** - Deploy directly from 42helv.com templates
- **MySQL Database** - Automatically provisioned and configured
- **Nginx + PHP** - Optimized configuration for PrestaShop
- **Auto Cleanup** - Removes install folder for security
- **SSL Ready** - Works with 42helv.com automatic SSL

## Quick Start

### Deploy on 42helv.com

1. Sign up at [42helv.com](https://42helv.com)
2. Go to **Services** → **Create New**
3. Select the **PrestaShop** template
4. Configure your store and deploy

Your PrestaShop store will be available at `https://your-site.42helv.com/`

### Default Admin Access

After deployment:
- **Shop URL**: `https://your-site.42helv.com/`
- **Admin URL**: `https://your-site.42helv.com/admin`
- **Email**: `demo@prestashop.com`
- **Password**: `prestashop_demo`

⚠️ **Important**: Change your admin password immediately after first login!

## Configuration

The following environment variables are available:

| Variable | Default | Description |
|----------|---------|-------------|
| `PS_FOLDER_ADMIN` | `admin` | Admin folder name |
| `PS_FOLDER_INSTALL` | `install` | Install folder name |
| `DB_HOST` | (auto) | Database host |
| `DB_USER` | (auto) | Database user |
| `DB_PASSWD` | (auto) | Database password |
| `DB_NAME` | `prestashop` | Database name |

## Local Development

```bash
# Clone the repository
git clone https://github.com/dannysimfukwe/prestashop-docker.git
cd prestashop-docker

# Start with Docker Compose
docker-compose up -d

# Access PrestaShop at http://localhost:8080
```

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Nginx (Port 80)                     │
│                    + PHP-FPM                            │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                      PrestaShop                         │
│                   (e-commerce CMS)                     │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                   MySQL (Port 3306)                     │
│                   (product database)                   │
└─────────────────────────────────────────────────────────┘
```

## Tech Stack

- [PrestaShop](https://www.prestashop-project.org/) - Open-source e-commerce platform
- Nginx - Web server
- PHP 8.x - Programming language
- MySQL - Database

## Security Notes

1. **Change default password** - Update admin credentials after first login
2. **Install folder removed** - Automatically cleaned up after setup
3. **SSL enabled** - Automatic HTTPS via 42helv.com

## Troubleshooting

### Admin panel not accessible?
Check that the install folder has been removed. If not, delete `install/` folder manually.

### Database connection issues?
Verify your `DB_HOST`, `DB_USER`, and `DB_PASSWD` environment variables are correct.

### Need to reset?
Delete all files and the database, then redeploy from 42helv.com.

## License

MIT License - Deploy freely on 42helv.com or your own infrastructure.

---

Built with ❤️ on [42helv.com](https://42helv.com)