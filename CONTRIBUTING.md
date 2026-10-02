# Contributing

Worldsmith requires PHP 8.4 and targets Bedriox API `^0.4`.

Install development dependencies:

```console
composer install
```

Run verification:

```console
composer test
composer analyse
composer style
```

Syntax-check production files on PowerShell:

```powershell
rg --files src -g '*.php' | ForEach-Object { php -l $_ }
```

Before submitting a change, update the relevant documentation and changelog. Never import `Bedriox\Server`, write into the PHAR, directly manipulate world storage, or turn an unknown teleport target into an implicit world creation.
