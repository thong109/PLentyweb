# App third party

## Vimeo.

```
    API version : 3.4
    Client identifier : 77f988b49390070d2c27672e2bc666811912ff60
    Client secret : beIY0j/uzBmYj/FAnFcShOKAkn/SA5JGby0mT8wHh/iJJaLFXTxAywcURXh2fcWxXDqw2ELxd2XiVfht1ZYRwWRdzcRZaRUpPkV7hGjtebg9LzqPJEubXd6ZYA+FYEnS
    Token : 6616d8547ecfb525374608a56f5d49fd
```

## zoom.

```
    API Key : 9ZVbrlWJRveZBSESdv9ePg
    API Secret : TuGvR1RILB1WAwfe4G6pSDwKpjAMwjQNcWZI
    IM Chat History Token : eyJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJRcE02UG9SMVRFaVV3RFlSc3cta3lnIn0.d6zk3ILAf915iwrPwWQdqHrso4Jrvwqmu_mrRvLQuwM
```

## Stripe
```
Public key : pk_test_51HHSIEJC8x0qBBa65GI1oA433Wp2rqXvWvv1HfpNpJJOs5ezbT9ccoJ8TgwCCDjs6nAyB86ByrWX3jnXflMzVPJ200PhgfqNlP
Secret key : sk_test_51HHSIEJC8x0qBBa6VXTyiS85rDmi6a0K0cKEx5svRzr10KuHcp5HV3TbsU25CcrjIWHHEruT7vkebNZIItnyoHSQ00JSKu9Fms
```

# Account for system

## User for Admin Role
```
    username : admin
    password : password
```
## User for Store Role
```
    shop_id : 1,
    username: shop_one
    password: password

    shop_id : 2,
    username: shop_two
    password: password
```


#Migrate
## make new migrate
```
    php bin/console doctrine:migrations:generate
```

## Running Migrate
```
    php bin/console doctrine:migrations:migrate
```
## Doctrine ORM
```
    bin/console eccube:generate:proxies
    bin/console doctrine:schema:update
    bin/console doctrine:schema:update --dump-sql
    bin/console doctrine:schema:update --force
```

# Install plugin
## ProductReview
```
    Install :
    bin/console eccube:plugin:install --code=ProductReview4
    Active
    bin/console eccube:plugin:enable --code=ProductReview4
    
    bin/console eccube:plugin:install
    bin/console eccube:plugin:uninstall
    bin/console eccube:plugin:enable
    bin/console eccube:plugin:disable 
    
    More
    bin/console eccube:plugin:install --code=PluginCode
    bin/console eccube:plugin:enable --code=PluginCode
    bin/console eccube:plugin:disable --code=PluginCode
    bin/console eccube:plugin:uninstall --code=PluginCode
    bin/console eccube:plugin:uninstall --code=PluginCode --uninstall-force=true
```
##Credit Card
```
    4242 4242 4242 4242
    4000 0000 0000 3220
```
