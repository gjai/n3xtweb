# Configuration File Protection

## Issue Resolution

The `config.php` file was previously tracked in the repository, which meant that during FTP updates, the configured version would be overwritten with the placeholder version from the repository.

## Solution Implemented

### 1. Removed config.php from Git Tracking
- The `config.php` file has been removed from git tracking using `git rm --cached config/config.php`
- It is already properly excluded in `.gitignore` under `config/config.php`

### 2. Installation Process
- The `config.php` file is now only generated during installation via `install.php`
- The installation process creates the file from `config/config.template.php`
- An `.installed` marker file is created to indicate successful installation

### 3. Update Protection Mechanisms

#### Automatic Redirection
- `index.php` automatically redirects to `install.php` if `config.php` doesn't exist
- This ensures the system cannot run without proper configuration

#### Update Process Protection
Multiple layers of protection during system updates:

1. **Critical Directories Protection**
   - The entire `config/` directory is protected during updates
   - Other critical directories: `uploads/`, `logs/`, `backups/`

2. **Specific File Exclusion**
   - `config.php` is specifically excluded from being overwritten
   - Other protected files: `.htaccess`, `.ovhconfig`

3. **ConfigProtector Class**
   - New helper class to verify configuration protection
   - Validates that config files are properly protected
   - Integrated into the update process for additional safety

### 4. Benefits

- **FTP Update Safety**: config.php will never be overwritten during FTP updates
- **Git Repository Cleanliness**: No sensitive configuration data in the repository
- **Installation Integrity**: System properly handles missing configuration
- **Update Process Safety**: Multiple protection layers during automated updates

### 5. Testing

The protection mechanisms have been tested to ensure:
- config.php is not tracked by git
- config.php is properly excluded in .gitignore
- index.php redirects to installation when config is missing
- Update process properly protects configuration files
- ConfigProtector class provides additional verification

This resolves the issue where FTP updates could overwrite the site's configuration.