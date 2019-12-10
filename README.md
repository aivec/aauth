# Aauth
Proprietary authentication package for [Aivec plugins](aivec.co.jp/plugin).

## Building
Using a special development-only `composer-dev.json` is required when building this library. Use the below command each time **BEFORE YOU COMMIT**
```bash
$ export COMPOSER=composer-dev.json && composer install --no-dev
```

## Why are the vendor and dist directories version controlled?
Good question.<br>
Short answer: [mozart](github.com/coenjacobs/mozart) is not very good.<br>
Long answer: when this library is included as a composer dependency in another project that uses `mozart`, `mozart` doesn't know how to handle it.<br>

Another reason is because `mozart` *cannot* successfully bundle `guzzle` by simply running `./vendor/bin/mozart compose`. There are *many* bugs in `mozart` that make automating the build process for this library impossible.