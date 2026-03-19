<?php

declare(strict_types=1);

/*
 * This file is part of the TestContext package.
 *
 * (c) Kamil Kokot <kamil@kokot.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FriendsOfBehat\TestContext\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeFeature;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class TestContext implements Context
{
    private static string $workingDir;

    private static Filesystem $filesystem;

    private static string $phpBin;

    private ?Process $process = null;

    #[BeforeFeature]
    public static function beforeFeature(): void
    {
        self::$workingDir = sprintf('%s/%s/', sys_get_temp_dir(), uniqid('', true));
        self::$filesystem = new Filesystem();
        self::$phpBin = self::findPhpBinary();
    }

    #[BeforeScenario]
    public function beforeScenario(): void
    {
        self::$filesystem->remove(self::$workingDir);
        self::$filesystem->mkdir(self::$workingDir, 0777);
    }

    #[AfterScenario]
    public function afterScenario(): void
    {
        self::$filesystem->remove(self::$workingDir);
    }

    #[Given('/^a Behat configuration containing(?: "([^"]+)"|:)$/')]
    public function thereIsConfiguration(?string $content): void
    {
        if (self::isBehat4()) {
            $this->thereIsFile('behat.php', sprintf(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

return new class implements \Behat\Config\ConfigInterface {
    public function toArray(): array
    {
        $config = Yaml::parse(%s);

        foreach ($config as &$profile) {
            if (!is_array($profile) || !isset($profile['extensions'])) {
                continue;
            }

            $resolved = [];
            foreach ($profile['extensions'] as $name => $extensionConfig) {
                $resolved[$this->resolveExtensionClassName($name)] = $extensionConfig;
            }
            $profile['extensions'] = $resolved;
        }

        return $config;
    }

    private function resolveExtensionClassName(string $name): string
    {
        if (class_exists($name)) {
            return $name;
        }

        $parts = explode('\\', $name);
        $last = preg_replace('/Extension$/', '', end($parts)) . 'Extension';
        $guessed = $name . '\\ServiceContainer\\' . $last;

        if (class_exists($guessed)) {
            return $guessed;
        }

        return $name;
    }
};
PHP,
                var_export((string) $content, true),
            ));

            return;
        }

        $this->thereIsFile('behat.yml', $content);
    }

    #[Given('/^a (?:.+ |)file "([^"]+)" containing(?: "([^"]+)"|:)$/')]
    public function thereIsFile(?string $file, ?string $content): void
    {
        self::$filesystem->dumpFile(self::$workingDir . '/' . $file, (string) $content);
    }

    #[Given('/^a feature file containing(?: "([^"]+)"|:)$/')]
    public function thereIsFeatureFile(?string $content): void
    {
        $this->thereIsFile(sprintf('features/%s.feature', md5(uniqid('', true))), $content);
    }

    #[Given('/^a feature file with passing scenario$/')]
    public function thereIsFeatureFileWithPassingScenario(): void
    {
        $this->thereIsFile('features/bootstrap/FeatureContext.php', <<<CON
<?php

declare(strict_types=1);

class FeatureContext implements \Behat\Behat\Context\Context
{
    #[\Behat\Step\Then('it passes')]
    public function itPasses(): void {}
}
CON
);

        $this->thereIsFeatureFile(<<<FEA
Feature: Passing feature

    Scenario: Passing scenario
        Then it passes
FEA
);
    }

    #[Given('/^a feature file with failing scenario$/')]
    public function thereIsFeatureFileWithFailingScenario(): void
    {
        $this->thereIsFile('features/bootstrap/FeatureContext.php', <<<CON
<?php

declare(strict_types=1);

class FeatureContext implements \Behat\Behat\Context\Context
{
    #[\Behat\Step\Then('it fails')]
    public function itFails(): void { throw new \RuntimeException(); }
}
CON
        );

        $this->thereIsFeatureFile(<<<FEA
Feature: Failing feature

    Scenario: Failing scenario
        Then it fails
FEA
        );
    }

    #[Given('/^a feature file with scenario with missing step$/')]
    public function thereIsFeatureFileWithScenarioWithMissingStep(): void
    {
        $this->thereIsFile('features/bootstrap/FeatureContext.php', <<<CON
<?php

declare(strict_types=1);

class FeatureContext implements \Behat\Behat\Context\Context {}
CON
        );

        $this->thereIsFeatureFile(<<<FEA
Feature: Feature with missing step

    Scenario: Scenario with missing step
        Then it does not have this step
FEA
        );
    }

    #[Given('/^a feature file with scenario with pending step$/')]
    public function thereIsFeatureFileWithScenarioWithPendingStep(): void
    {
        $this->thereIsFile('features/bootstrap/FeatureContext.php', <<<CON
<?php

declare(strict_types=1);

class FeatureContext implements \Behat\Behat\Context\Context
{
    #[\Behat\Step\Then('it has this step as pending')]
    public function itFails(): void { throw new \Behat\Behat\Tester\Exception\PendingException(); }
}
CON
        );

        $this->thereIsFeatureFile(<<<FEA
Feature: Feature with pending step

    Scenario: Scenario with pending step
        Then it has this step as pending
FEA
        );
    }

    #[When('/^I run Behat$/')]
    public function iRunBehat(): void
    {
        /** @phpstan-ignore-next-line */
        $this->process = new Process([self::$phpBin, trim(escapeshellarg(BEHAT_BIN_PATH), "'"), '--strict', '-vvv', '--no-interaction', '--lang=en'], self::$workingDir);
        $this->process->start();
        $this->process->wait();
    }

    #[Then('/^it should pass$/')]
    public function itShouldPass(): void
    {
        if (0 === $this->getProcessExitCode()) {
            return;
        }

        throw new \DomainException(
            'Behat was expecting to pass, but failed with the following output:' . PHP_EOL . PHP_EOL . $this->getProcessOutput()
        );
    }

    #[Then('/^it should pass with(?: "([^"]+)"|:)$/')]
    public function itShouldPassWith(?string $expectedOutput): void
    {
        $this->itShouldPass();
        $this->assertOutputMatches($expectedOutput);
    }

    #[Then('/^it should fail$/')]
    public function itShouldFail(): void
    {
        if (0 !== $this->getProcessExitCode()) {
            return;
        }

        throw new \DomainException(
            'Behat was expecting to fail, but passed with the following output:' . PHP_EOL . PHP_EOL . $this->getProcessOutput()
        );
    }

    #[Then('/^it should fail with(?: "([^"]+)"|:)$/')]
    public function itShouldFailWith(?string $expectedOutput): void
    {
        $this->itShouldFail();
        $this->assertOutputMatches($expectedOutput);
    }

    #[Then('/^it should end with(?: "([^"]+)"|:)$/')]
    public function itShouldEndWith(?string $expectedOutput): void
    {
        $this->assertOutputMatches($expectedOutput);
    }

    private function assertOutputMatches(?string $expectedOutput): void
    {
        $pattern = '/' . preg_quote((string) $expectedOutput, '/') . '/sm';
        $output = $this->getProcessOutput();

        $result = preg_match($pattern, $output);
        if (false === $result) {
            throw new \InvalidArgumentException('Invalid pattern given:' . $pattern);
        }

        if (0 === $result) {
            throw new \DomainException(sprintf(
                'Pattern "%s" does not match the following output:' . PHP_EOL . PHP_EOL . '%s',
                $pattern,
                $output
            ));
        }
    }

    private function getProcessOutput(): string
    {
        $this->assertProcessIsAvailable();

        return sprintf('%s%s', $this->process?->getErrorOutput(), $this->process?->getOutput());
    }

    private function getProcessExitCode(): int
    {
        $this->assertProcessIsAvailable();

        return $this->process?->getExitCode() ?? -1;
    }

    private function assertProcessIsAvailable(): void
    {
        if (null === $this->process) {
            throw new \BadMethodCallException('Behat process cannot be found. Did you run it before making assertions?');
        }
    }

    private static function findPhpBinary(): string
    {
        $phpBinary = (new PhpExecutableFinder())->find();
        if (false === $phpBinary) {
            throw new \RuntimeException('Unable to find the PHP executable.');
        }

        return $phpBinary;
    }

    private static function isBehat4(): bool
    {
        return class_exists(\Behat\Config\Config::class);
    }
}
