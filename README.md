# ITK Site crawler
Built on Symfony 5.

## Requirements
[https://symfony.com/doc/current/setup.html#technical-requirements](https://symfony.com/doc/current/setup.html#technical-requirements)

## Installation
```
composer install
```

## Usage
```
bin/console crawlItkSites --help
```

### Usage example
```
bin/console crawlItkSites gdpr_compliant --domain="http://deltag.aarhus.dk" --max-visits=20
```
