# Local Development

To test features or fixes in a local Laravel application without publishing the package to a repository, use a Composer path repository.

## Setup in Local App

1. Add the local path to your application's `composer.json`. By default, Composer will symlink the directory:

```json
"repositories": [
    {
        "type": "path",
        "url": "D:\\Dev\\php\\artisan-overrides",
        "options": {
            "symlink": true
        }
    }
],
```
```

2. Require the package:

```bash
composer require masgeek/artisan-toolkit:@dev
```

## Testing Changes

1. **Publish Config**: Ensure you publish the toolkit config in your local app to enable the commands:
   ```bash
   php artisan vendor:publish --tag=artisan-toolkit-config
   ```

2. **Enable Commands**: Open `config/artisan-toolkit.php` in your local app and ensure the commands you are testing are set to their class name (not `false`).

3. **Real-time Updates**: Because it is a path repository, Composer creates a symlink. Changes made in the `artisan-overrides` folder are reflected immediately in the local app.

## Verification

Run the command in your local app to verify:
```bash
php artisan your:command-name
```
