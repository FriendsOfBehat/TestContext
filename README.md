# Test Context [![License](https://img.shields.io/packagist/l/friends-of-behat/test-context.svg)](https://packagist.org/packages/friends-of-behat/test-context) [![Version](https://img.shields.io/packagist/v/friends-of-behat/test-context.svg)](https://packagist.org/packages/friends-of-behat/test-context) [![Scrutinizer Quality Score](https://img.shields.io/scrutinizer/g/FriendsOfBehat/TestContext.svg)](https://scrutinizer-ci.com/g/FriendsOfBehat/TestContext/)

Provides a reusable Behat context for testing Behat extensions.
It lets you run Behat inside Behat - setting up temporary configurations, feature files, and contexts, then asserting on the results.

## Installation

```bash
composer require friends-of-behat/test-context --dev
```

## Configuration

Register the context in your test suite:

```yaml
# behat.yml
default:
    suites:
        default:
            contexts:
                - FriendsOfBehat\TestContext\Context\TestContext
```

```php
// behat.php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use FriendsOfBehat\TestContext\Context\TestContext;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('default'))
                    ->withContexts(TestContext::class)
            )
    );
```

## How It Works

Each scenario gets an isolated temporary directory that is created before and cleaned up after every scenario. When you use the setup steps, files are written into this directory.
`When I run Behat` executes a real Behat process in that directory, and the assertion steps check its exit code and output.

This means scenarios are fully independent — file changes from one scenario never leak into another.

## Usage Examples

### Testing a simple extension

```gherkin
Feature: My extension
    In order to ensure my extension works
    As a Behat extension developer
    I want to run Behat with my extension loaded

    Scenario: Extension registers successfully
        Given a Behat configuration containing:
        """
        default:
            extensions:
                My\Extension: ~
        """
        And a feature file containing:
        """
        Feature: Smoke test

            Scenario: It works
                Then it passes
        """
        And a context file "features/bootstrap/FeatureContext.php" containing:
        """
        <?php

        use Behat\Behat\Context\Context;
        use Behat\Step\Then;

        class FeatureContext implements Context
        {
            #[Then('it passes')]
            public function itPasses() {}
        }
        """
        When I run Behat
        Then it should pass
```

### Quick smoke tests with shorthand steps

```gherkin
Feature: Quick checks

    Scenario: Passing scenario
        Given a feature file with passing scenario
        When I run Behat
        Then it should pass with:
        """
        1 scenario (1 passed)
        1 step (1 passed)
        """

    Scenario: Failing scenario
        Given a feature file with failing scenario
        When I run Behat
        Then it should fail with:
        """
        1 scenario (1 failed)
        1 step (1 failed)
        """
```

### Asserting on error output

```gherkin
Scenario: Unknown extension causes a configuration error
    Given a Behat configuration containing:
    """
    default:
        extensions:
            Unknown\Extension: ~
    """
    When I run Behat
    Then it should fail with "ExtensionInitializationException"
```

## Available Steps

### Setup

| Step                                                   | Description                                                  |
|--------------------------------------------------------|--------------------------------------------------------------|
| `Given a Behat configuration containing:`              | Creates a temporary `behat.yml` (or `behat.php` for Behat 4) |
| `Given a feature file "path" containing:`              | Creates a feature file at the given path                     |
| `Given a feature file containing:`                     | Creates a feature file with an auto-generated path           |
| `Given a context file "path" containing:`              | Creates a PHP context file at the given path                 |
| `Given a file "path" containing:`                      | Creates any file at the given path                           |
| `Given a feature file with passing scenario`           | Generates a ready-made passing scenario with context         |
| `Given a feature file with failing scenario`           | Generates a ready-made failing scenario with context         |
| `Given a feature file with scenario with missing step` | Generates a scenario with an undefined step                  |
| `Given a feature file with scenario with pending step` | Generates a scenario with a pending step                     |

### Execution

| Step               | Description                                   |
|--------------------|-----------------------------------------------|
| `When I run Behat` | Runs Behat in the temporary working directory |

### Assertions

| Step                              | Description                                            |
|-----------------------------------|--------------------------------------------------------|
| `Then it should pass`             | Asserts Behat exited with code 0                       |
| `Then it should pass with "text"` | Asserts Behat passed and output contains text          |
| `Then it should fail`             | Asserts Behat exited with a non-zero code              |
| `Then it should fail with "text"` | Asserts Behat failed and output contains text          |
| `Then it should end with "text"`  | Asserts output contains text (regardless of exit code) |

All `with` variants also accept multiline pystrings.
