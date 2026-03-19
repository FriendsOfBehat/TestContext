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

if (class_exists(\Behat\Hook\BeforeFeature::class)) {
    require_once __DIR__ . '/Behat310/Context/TestContext.php';
} else {
    require_once __DIR__ . '/Behat3/Context/TestContext.php';
}
