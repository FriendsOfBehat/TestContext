Feature: Independent scenarios
    In order to test my Behat extension
    As a Behat extension developer
    I want to be sure that scenarios does not share anything

    Background:
        Given a context file "features/bootstrap/FeatureContext.php" containing:
        """
        <?php

        use Behat\Behat\Context\Context;

        class FeatureContext implements Context
        {
            /** @Then it passes */
            public function itPasses() {}
        }
        """

    Scenario: First scenario
        Given a feature file "features/feature.feature" containing:
        """
        Feature: Passing feature

            Scenario: Passing scenario
                Then it passes
        """
        When I run Behat
        Then it should pass

    Scenario: Second scenario
        When I run Behat
        Then it should fail with:
        """
        No scenarios
        No steps
        """

