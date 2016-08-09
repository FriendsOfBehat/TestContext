# Test Context [![License](https://img.shields.io/packagist/l/friends-of-behat/test-context.svg)](https://packagist.org/packages/friends-of-behat/test-context) [![Version](https://img.shields.io/packagist/v/friends-of-behat/test-context.svg)](https://packagist.org/packages/friends-of-behat/test-context) [![Build status on Linux](https://img.shields.io/travis/FriendsOfBehat/TestContext/master.svg)](http://travis-ci.org/FriendsOfBehat/TestContext) [![Scrutinizer Quality Score](https://img.shields.io/scrutinizer/g/FriendsOfBehat/TestContext.svg)](https://scrutinizer-ci.com/g/FriendsOfBehat/TestContext/)

Provides reusable context that helps in testing Behat extensions.

## Usage

1. Install it:

```bash
$ composer require friends-of-behat/test-context --dev
```

2. Include in your suite:

```yaml
default:
    # ...
    suites:
        default:
            contexts:
                - FriendsOfBehat\TestContext\Context\TestContext
```
