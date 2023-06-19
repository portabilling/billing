# PortaOne Billing API and ESPF wrapper for PHP

## Purpose

This package intended to simplify communication to PortaOne billing system while creating user portal applications, integration and provisioning code. Build for composer with PSR-4 autoload, support concurrent/async call for faster load big count of entities and a good to integrate with DI containers.

It is decoupled from most of depenencies, requiring standart PSR interfaces instead of exact implementations. It uses:
- [PSR-7](https://www.php-fig.org/psr/psr-7) standard objects used, Request, Response and Stream
- [PSR-17](https://www.php-fig.org/psr/psr-17) requestFactory and StreamFactory to create PSR-7 objects
- [PSR-16](https://www.php-fig.org/psr/psr-16) SimpleCache object to save session data and provide session persistance. Very basic implementations packaged, enough to handle session storage in not too high load ineronment.
- [PSR-18](https://www.php-fig.org/psr/psr-18) HTTP clients supported, but it lacks of effective support for concurrent and async requestst. It is better to use advanced clients like Guzzle.

## Usage
Start with [usage example](https://github.com/portabilling/billing/blob/master/examples/BillingUsage.php), check [phpDoc](https://portabilling.github.io/billing/). This lib may make communications to the billing much simpler, but you still need deep knowledge of billing architecture, entities and logics. Use [PortaOne API docs](https://docs.portaone.com/), mind your API reference with live test ability always on your fingertips: at path `doc/api/` of the same server where admin web interface reside.

- Use [Billing](https://portabilling.github.io/billing/classes/Porta-Billing-Billing.html) or [ESPF](https://portabilling.github.io/billing/classes/Porta-Billing-ESPF.html) class to access main API or ESPF API. 
- Create it with [Config](https://portabilling.github.io/billing/classes/Porta-Billing-Config.html) class, loaded with necessary config data or provide any other class, implementing [ConfigInterface](https://portabilling.github.io/billing/classes/Porta-Billing-Interfaces-ConfigInterface.html). If you use DI container with autowire function, you may setup it over container. 
- For session persistance over multiple script runs - use any PSR-16 SimpleCache implementaton. Unless you have other caching requirements in your project, you may use included simple FileCache implementation. Otherwise, use any other PSR-6 implementation or more heavy PSR-6 cache over PSR-16 to PSR-6 brbdge     
- Then, as you instatiated wrapper, which handleas all auth, session and other issues, use it:
    - for biling use [call()](https://portabilling.github.io/billing/classes/Porta-Billing-Billing.html#method_call) to communicate with billing. You only need API endpoint and parameters to send. If you use advanced cient with proper adapter (as Guzzle), you alo may use and [callConcurrent()](https://portabilling.github.io/billing/classes/Porta-Billing-Billing.html#method_callConcurrent) and [callAsync()](https://portabilling.github.io/billing/classes/Porta-Billing-Billing.html#method_callAsync). 
    - ESPF has different approach with [get()](https://portabilling.github.io/billing/classes/Porta-Billing-ESPF.html#method_get), [post()](https://portabilling.github.io/billing/classes/Porta-Billing-ESPF.html#method_post), [put()](https://portabilling.github.io/billing/classes/Porta-Billing-ESPF.html#method_put) and [delete()](https://portabilling.github.io/billing/classes/Porta-Billing-ESPF.html#method_delete), read the ESPF API docs carefuly.
- All the time be ready to catch [Exceptions](https://portabilling.github.io/billing/packages/Exceptions.html) if something goes wrong.

## Installation
In the Composer storage. Just add proper require section:
```
    "require": {
        "porta/billing": "^0.1"
    }
```
Please, review the [changelog](https://github.com/portabilling/billing/blob/master/CHANGELOG.MD) before to change used version.

## Dependencies
Composer dependencies:
- php: ^7.4|^8.0|^8.1|^8.2
- psr/http-client: ^1.0.2
- psr/http-factory: ^1.0.2
- psr/simple-cache: ^1.0.1
- psr/http-factory-implementation: ^1.0
- psr/http-message-implementation: ^1.0

Form all this, factory is what yo really eed to choose and provide. It could be [guzzlehttp/psr7](https://packagist.org/packages/guzzlehttp/psr7) or [slim/psr7](https://packagist.org/packages/slim/psr7) or [nyholm/psr7](https://packagist.org/packages/nyholm/psr7) or whatever else.

Some dependencies not in composer profile, but need be kept in mind:
- **psr/simple-cache-implementation**. Very simple implementaton of PSR-16 included, and it could be enough for simple use cases. If you need more  caching for the project - just use PSR-16 cache of your choice or PSR-6 with bridge to PSR-16. Wire to configuration and go. 
- **psr/http-client-implementation**. Library uses own HTTP client adapter interface to allow concurrent and async calls. It **may** use any PSR-18 HTTP client and PSR-18 adapter is packaged. But it is better to use more advanced client. Guzzle directly supported by extra package.

## Testing
Tested with PHPUnit 9.6 on php 7.4 to 8.2, quite good coverage, which does not mean everything will work as intended. Guzzle used for testing, not yet decoupled in tests.

Current testing and compatibility status check on [Github flows](https://github.com/portabilling/billing/actions/workflows/Compatibility.yml)

To test, install in dev mode and use: 
- `composer test` command from library root for run all standard modular tests
- `composer livetest` to run a test against live PortaOne billing server if you have one available.

For live testing run `livetest` once and it will create config file templeate. Then edit the file to provide host, username and password or token and run `livetest` again. Do not forget to remove the config file after tests!

Manualy tested and used with PortaBilling release MR100.