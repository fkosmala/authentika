# Authentika

Authentika immortalizes your creations and generate a JSON file with SHA-512 checksum for your file. The generated digital certificate is submit to the HIVE Blockchain. See it in action on [https://authentika.art](https://authentika.art).

## Requirements

- PHP 7.3+
- php-zip, php-xml & php-curl packages
- Python 3 and [Beem/Beempy](https://github.com/holgern/beem) > 0.23.6

## Clone this repository

Download zip and extract it onto your webserver or use GIT :

```
git clone https://github.com/fkosmala/authentika
```

## Get composer and install dependancies

You must install [Composer](https://getcomposer.org/). Aftar that, just run :

```
php composer.phar update
```

It will install all the dependancies.

## Configure your webserver

Based on Slim4, the configuration for your webserver is avaiblable on [Slim 4 WebServer documentation](http://www.slimframework.com/docs/v3/start/web-servers.html) . The root directory is ```public``` 

## Rename the account file

Rename ```account.sample.json``` into ```account.json``` and edit this file to add your HIVE account and posting key.

## Have Fun !

That's all ! You can submit genuine certificate on the HIVE blockchain !