Feature: Simple scenarios
    In order to test my extensions quickly
    As a Behat extension developer
    I want to generate the desired output using only one step

    Scenario: Simple passing scenario
        Given a feature file with passing scenario
        When I run Behat
        Then it should pass with "1 scenario"
