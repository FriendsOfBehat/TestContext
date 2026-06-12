<?php

declare(strict_types=1);

namespace FriendsOfBehat\TestContext\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TestContext implements Context
{
    private Filesystem $filesystem;

    private string $workingDir;

    private ?Process $process = null;

    #[BeforeScenario]
    public function beforeScenario(): void
    {
        $this->filesystem = new Filesystem();
        $this->workingDir = sprintf('%s/%s', sys_get_temp_dir(), uniqid('', true));
        $this->filesystem->mkdir($this->workingDir, 0777);
        $this->process = null;
    }

    #[AfterScenario]
    public function afterScenario(): void
    {
        $this->filesystem->remove($this->workingDir);
    }

    #[Given('/^a Behat configuration containing(?: "([^"]+)"|:)$/')]
    public function thereIsConfiguration(string $content): void
    {
        $this->filesystem->dumpFile($this->workingDir . '/behat.dist.php', $content);
    }

    #[Given('/^a (?:.+ |)file "([^"]+)" containing(?: "([^"]+)"|:)$/')]
    public function thereIsFile(string $file, string $content): void
    {
        if (str_ends_with($file, '.php') && str_contains($content, '* @')) {
            $content = $this->replaceAnnotationsWithAttributes($content);
        }

        $this->filesystem->dumpFile($this->workingDir . '/' . $file, $content);
    }

    #[Given('/^a feature file containing(?: "([^"]+)"|:)$/')]
    public function thereIsFeatureFile(string $content): void
    {
        $this->thereIsFile(sprintf('features/%s.feature', uniqid('', true)), $content);
    }

    #[Given('/^a feature file with passing scenario$/')]
    public function thereIsFeatureFileWithPassingScenario(): void
    {
        $this->writeSharedFeatureContext();
        $this->thereIsFeatureFile(<<<'FEA'
Feature: Passing feature

    Scenario: Passing scenario
        Then it passes
FEA);
    }

    #[Given('/^a feature file with failing scenario$/')]
    public function thereIsFeatureFileWithFailingScenario(): void
    {
        $this->writeSharedFeatureContext();
        $this->thereIsFeatureFile(<<<'FEA'
Feature: Failing feature

    Scenario: Failing scenario
        Then it fails
FEA);
    }

    #[Given('/^a feature file with scenario with missing step$/')]
    public function thereIsFeatureFileWithScenarioWithMissingStep(): void
    {
        $this->writeSharedFeatureContext();
        $this->thereIsFeatureFile(<<<'FEA'
Feature: Feature with missing step

    Scenario: Scenario with missing step
        Then it does not have this step
FEA);
    }

    #[Given('/^a feature file with scenario with pending step$/')]
    public function thereIsFeatureFileWithScenarioWithPendingStep(): void
    {
        $this->writeSharedFeatureContext();
        $this->thereIsFeatureFile(<<<'FEA'
Feature: Feature with pending step

    Scenario: Scenario with pending step
        Then it has this step as pending
FEA);
    }

    #[When('/^I run Behat$/')]
    public function iRunBehat(): void
    {
        $this->process = new Process(
            [PHP_BINARY, BEHAT_BIN_PATH, '--strict', '-vvv', '--no-interaction', '--lang=en'],
            $this->workingDir,
        );
        $this->process->run();
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
    public function itShouldPassWith(string $expectedOutput): void
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
    public function itShouldFailWith(string $expectedOutput): void
    {
        $this->itShouldFail();
        $this->assertOutputMatches($expectedOutput);
    }

    #[Then('/^it should end with(?: "([^"]+)"|:)$/')]
    public function itShouldEndWith(string $expectedOutput): void
    {
        $this->assertOutputMatches($expectedOutput);
    }

    private function writeSharedFeatureContext(): void
    {
        $this->thereIsFile('features/bootstrap/FeatureContext.php', <<<'PHP'
<?php

declare(strict_types=1);

use Behat\Step\Then;

class FeatureContext implements \Behat\Behat\Context\Context
{
    #[Then('it passes')]
    public function itPasses(): void {}

    #[Then('it fails')]
    public function itFails(): void { throw new \RuntimeException(); }

    #[Then('it has this step as pending')]
    public function itHasThisStepAsPending(): void { throw new \Behat\Behat\Tester\Exception\PendingException(); }
}
PHP);
    }

    private function assertOutputMatches(string $expectedOutput): void
    {
        $output = $this->getProcessOutput();

        if (!preg_match('/' . preg_quote($expectedOutput, '/') . '/sm', $output)) {
            throw new \DomainException(sprintf(
                'Expected output to contain "%s", got:' . PHP_EOL . PHP_EOL . '%s',
                $expectedOutput,
                $output,
            ));
        }
    }

    private function getProcessOutput(): string
    {
        $process = $this->getProcess();

        return $process->getErrorOutput() . $process->getOutput();
    }

    private function getProcessExitCode(): int
    {
        return $this->getProcess()->getExitCode() ?? 1;
    }

    private function getProcess(): Process
    {
        if (null === $this->process) {
            throw new \BadMethodCallException('Behat process cannot be found. Did you run it before making assertions?');
        }

        return $this->process;
    }

    private function replaceAnnotationsWithAttributes(string $code): string
    {
        return (string) preg_replace_callback(
            '/^( *)\/\*\*\s*@(Given|When|Then|BeforeScenario|AfterScenario|BeforeFeature|AfterFeature)(?:\s+(.+?))?\s*\*\/$/m',
            static function (array $m): string {
                $indent = $m[1];
                $name = $m[2];
                $arg = isset($m[3]) ? "('" . str_replace("'", "\\'", $m[3]) . "')" : '';
                $ns = in_array($name, ['Given', 'When', 'Then'], true) ? 'Step' : 'Hook';

                return "{$indent}#[\\Behat\\{$ns}\\{$name}{$arg}]";
            },
            $code,
        );
    }
}
