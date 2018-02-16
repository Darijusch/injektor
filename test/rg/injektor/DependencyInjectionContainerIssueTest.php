<?php
/**
 * This file is part of rg\injektor.
 *
 * (c) ResearchGate GmbH <bastian.hofmann@researchgate.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Johannes Brinksmeier <johannes.brinksmeier@googlemail.com>
 */
namespace rg\injektor;

include_once 'test_classes_issue.php';

class DependencyInjectionContainerIssueTest extends \PHPUnit\Framework\TestCase
{
    public function testGetInstanceOfClassWithUnderscores()
    {
        $config = new Configuration(__DIR__ . '/test_config_issue.php');
        $dic = new DependencyInjectionContainer($config);
        $class = $dic->getInstanceOfClass('issue\ClassWithDependencyToClassWithUnderscores');
        $this->assertInstanceOf('issue\Class_With_Underscores', $class->getDependency());
    }

    public function testGetInstanceOfClassThatInjectsInterfaceInSameNamespace()
    {
        $dic = new DependencyInjectionContainer();
        $class = $dic->getInstanceOfClass('issue9\name\A');
        $this->assertInstanceOf('issue9\name\A', $class);
    }

    public function testGetInstanceOfClassWhereDefaultImplementedByIsNotTheFirstItem() {
        $dic = new DependencyInjectionContainer();
        $class = $dic->getInstanceOfClass('issueImplementedByOrder\name\B');
        $this->assertInstanceOf('issueImplementedByOrder\name\D', $class);
    }
}
