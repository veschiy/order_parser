# Native Windows build notes

This Laravel parser app can be packaged as a desktop app with NativePHP,
but **building a Windows `.exe` cannot be done on the current Ubuntu server**.

Why it failed:
- NativePHP uses Electron Builder for Windows packaging.
- Electron Builder needs Wine when it runs on Linux.
- Wine is not installed on this machine, and passwordless `sudo` is not available.
- Even with Wine, the build process expects PHP/runtime database files and may
  need additional NativePHP configuration.

What is needed for a Windows build:
1. A Windows development/CI machine, or a Linux machine where you can install Wine.
2. If using Linux, install Wine first, for example on Ubuntu:
   ```sh
   sudo apt-get update
   sudo apt-get install -y wine wine64
   ```
3. Restore the NativePHP package:
   ```sh
   composer require nativephp/desktop
   php artisan native:install
   ```
4. Register `App\Providers\NativeAppServiceProvider` in the NativePHP runtime
   only, add the NativePHP migration/database files, and configure Singer/
   credentials if signing is needed.
5. Build:
   ```sh
   php artisan native:build win x64
   ```

Recommended approach:
- Keep this project as a normal Laravel web app.
- Use a Windows runner/machine for desktop packaging.
- Do not commit `nativephp/electron/dist` or package lock changes caused by
  NativePHP unless they are intentionally part of a desktop release branch.

Current web app:
- `composer check` passes on PHP 8.4.
- Parsing, search, CSV export, editing, and soft deletes all work in browser mode.
