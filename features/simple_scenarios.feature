Feature: Simple scenarios
    In order to test my extensions quickly
    As a Behat extension developer
    I want to generate the desired output using only one step

    Scenario: Simple passing scenario
        Given a feature file with passing scenario
        When I run Behat
        Then it should pass with:
        """
        1 scenario (1 passed)
        1 step (1 passed)
        """

    Scenario: Simple failing scenario
        Given a feature file with failing scenario
        When I run Behat
        Then it should fail with:
        """
        1 scenario (1 failed)
        1 step (1 failed)
        """

    Scenario: Simple scenario with missing step
        Given a feature file with scenario with missing step
        When I run Behat
        Then it should fail with:
        """
        1 scenario (1 undefined)
        1 step (1 undefined)
        """

    Scenario: Simple scenario with pending step
        Given a feature file with scenario with pending step
        When I run Behat
        Then it should fail with:
        """
        1 scenario (1 pending)
        1 step (1 pending)
        """
