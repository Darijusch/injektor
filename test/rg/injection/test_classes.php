<?php
/*
 * This file is part of rg\injection.
 *
 * (c) ResearchGate GmbH <bastian.hofmann@researchgate.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace rg\injection;

require_once 'test_classes_not_injectable.php';

class DICTestClassOne {
    /**
     * @var \rg\injection\DICTestClassTwo
     */
    public $two;
    /**
     * @var \rg\injection\DICTestClassThree
     */
    public $three;

    /**
     * @inject
     * @var \rg\injection\DICTestClassThree
     */
    protected $four;

    /**
     * @return DICTestClassThree
     */
    public function getFour() {
        return $this->four;
    }

    /**
     * @inject
     * @param DICTestClassTwo $two
     * @param DICTestClassThree $three
     */
    public function __construct(DICTestClassTwo $two, DICTestClassThree $three) {
        $this->two = $two;
        $this->three = $three;
    }

    /**
     * @inject
     * @param DICTestClassTwo $two
     * @param DICTestClassThree $three
     * @return string
     */
    public function getSomething(DICTestClassTwo $two, DICTestClassThree $three) {
        return $two->getSomething() . $three->getSomething();
    }

    /**
     * @inject
     * @param DICTestClassTwo $two
     * @param $three
     * @return string
     */
    public function getSomethingTwo(DICTestClassTwo $two, $three) {
        return $two->getSomething() . $three->getSomething();
    }

    public function getSomethingNotInjectible(DICTestClassTwo $two, DICTestClassThree $three) {
        return $two->getSomething() . $three->getSomething();
    }

    public function noTypeHint($foo) {

    }
}

class DICTestClassOneConfigured extends DICTestAbstractClass implements DICTestInterface {

}

class DICTestClassTwo {
    /**
     * @var \rg\injection\DICTestClassThree
     */
    public $three;

    /**
     * @inject
     * @param DICTestClassThree $three
     */
    public function __construct(DICTestClassThree $three) {
        $this->three = $three;
    }

    public function getSomething() {
        return 'bar';
    }
}

class DICTestClassThree {

    public function __construct() {

    }

    public function getSomething() {
        return 'foo';
    }
}

class DICTestClassNoInject {

    public function __construct(DICTestClassThree $three) {

    }
}

class DICTestClassNoTypeHint {

    public $one;
    public $two;

    /**
     * @inject
     */
    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }
}

class DICTestClassNoTypeHintOptionalArgument {

    public $one;
    public $two;
    public $ar;

    public function __construct($one, $two = 'bar', array $ar = array()) {
        $this->one = $one;
        $this->two = $two;
        $this->ar = $ar;
    }
}

class DICTestClassNoParamTypeHint {
    /**
     * @inject
     */
    public $two;
}

class DICTestClassPrivateProperty {
    /**
     * @inject
     * @var DICTestClassNoConstructor
     */
    private $two;
}

class DICTestClassPropertyDoubledAnnotation {
    /**
     * @inject
     * @var \rg\injection\DICTestClassNoConstructor
     * @var \rg\injection\DICTestClassPrivateProperty
     */
    public $two;
}

class DICTestClassNoConstructor {
}


class DICTestAnnotatedInterfaceImpl implements DICTestAnnotatedInterface {

}

class DICTestAnnotatedInterfaceImplOne implements DICTestAnnotatedInterface {

}

class DICTestAnnotatedInterfaceImplTwo implements DICTestAnnotatedInterface {

}

class DICTestNamed {
    public $one;
    /**
     * @inject
     * @var \rg\injection\DICTestAnnotatedInterface
     * @named implTwo
     */
    public $two;

    /**
     * @inject
     * @param DICTestAnnotatedInterface $one
     * @named implOne $one
     */
    public function __construct(DICTestAnnotatedInterface $one) {
        $this->one = $one;
    }

    /**
     * @inject
     * @param DICTestAnnotatedInterface $one
     * @named implOne $one
     * @return \rg\injection\DICTestAnnotatedInterface
     */
    public function doSomething(DICTestAnnotatedInterface $one) {
        return $one;
    }
}

class DICTestAnnotatedInterfaceNamedConfigImpl implements DICTestAnnotatedInterfaceNamedConfig {

}

class DICTestAnnotatedInterfaceNamedConfigImplOne implements DICTestAnnotatedInterfaceNamedConfig {

}

class DICTestAnnotatedInterfaceNamedConfigImplTwo implements DICTestAnnotatedInterfaceNamedConfig {

}

class DICTestNamedConfig {

    public $one;

    /**
     * @inject
     * @var \rg\injection\DICTestAnnotatedInterfaceNamedConfig
     * @named implTwo
     */
    public $two;

    /**
     * @inject
     * @param DICTestAnnotatedInterfaceNamedConfig $one
     * @named implOne $one
     */
    public function __construct(DICTestAnnotatedInterfaceNamedConfig $one) {
        $this->one = $one;
    }

    /**
     * @inject
     * @param DICTestAnnotatedInterfaceNamedConfig $one
     * @named implOne $one
     * @return \rg\injection\DICTestAnnotatedInterfaceNamedConfig
     */
    public function doSomething(DICTestAnnotatedInterfaceNamedConfig $one) {
        return $one;
    }
}

class DICTestSingleton {
    public $foo;
    public $instance;

    /**
     * @inject
     * @var rg\injection\DICTestClassNoConstructor
     */
    public $injectedProperty;

    private function __construct($foo, $instance) {
        $this->foo = $foo;
        $this->instance = $instance;
    }

    /**
     * @inject
     * @static
     * @param DICTestClassNoConstructor $instance
     * @return Singleton
     */
    public static function getInstance(DICTestClassNoConstructor $instance) {
        return new DICTestSingleton('foo', $instance);
    }
}

/**
 * @singleton
 */
class DICTestAnnotatedSingleton {
}

class DICTestAspects {
    public $one;
    public $two;
    public $cone;
    public $ctwo;

    /**
     * @inject
     * @param \rg\injection\DICTestAnnotatedSingleton $one
     * @param $two
     * @before \rg\injection\DICTestBeforeAspect one=1&two=bar
     * @before \rg\injection\DICTestBeforeAspect
     * @after \rg\injection\DICTestAfterAspect foo=bar
     */
    public function __construct(DICTestAnnotatedSingleton $one, $two) {
        $this->cone = $one;
        $this->ctwo = $two;
    }

    /**
     * @inject
     * @param \rg\injection\DICTestAnnotatedSingleton $one
     * @param $two
     * @before \rg\injection\DICTestBeforeAspect one=1&two=bar
     * @before \rg\injection\DICTestBeforeAspect
     * @after \rg\injection\DICTestAfterAspect foo=bar
     */
    public function aspectFunction(DICTestAnnotatedSingleton $one, $two) {
        $this->one = $one;
        $this->two = $two;

        return 'foo';
    }
}

class DICTestBeforeAspect implements \rg\injection\aspects\Before {
    public function execute($aspectArguments, $className, $functionName, $functionArguments) {
        $functionArguments['two'] = array(
            $functionArguments['two'],
            $aspectArguments,
            $className,
            $functionName
        );
        return $functionArguments;
    }
}

class DICTestAfterAspect implements \rg\injection\aspects\After {
    public function execute($aspectArguments, $className, $functionName, $result) {
        $result = array(
            $result,
            $aspectArguments,
            $className,
            $functionName
        );
        return $result;
    }
}

class DICTestInterceptAspectClass {
    public $one;
    public $two;
    public $cone;
    public $ctwo;

    /**
     * @inject
     * @param \rg\injection\DICTestAnnotatedSingleton $one
     * @param $two
     * @intercept \rg\injection\DICTestInterceptAspect one=1&two=bar
     */
    public function __construct(DICTestAnnotatedSingleton $one, $two) {
        $this->cone = $one;
        $this->ctwo = $two;
    }

    /**
     * @inject
     * @param \rg\injection\DICTestAnnotatedSingleton $one
     * @param $two
     * @intercept \rg\injection\DICTestInterceptAspect one=1&two=bar
     */
    public function aspectFunction(DICTestAnnotatedSingleton $one, $two) {
        $this->one = $one;
        $this->two = $two;

        return 'foo';
    }
}

class DICTestInterceptAspect implements \rg\injection\aspects\Intercept {
    public function execute($aspectArguments, $className, $functionName, $functionArguments, $lastResult) {
        return array(
            $functionArguments,
            $aspectArguments,
            $className,
            $functionName,
            $lastResult
        );
    }
}

class DICTestProvidedInterfaceImpl1 implements DICTestProvidedInterface {

}

class DICTestProvidedInterfaceImpl2 implements DICTestProvidedInterface {

}

class DICTestSimpleProvidedDecorator implements DICTestSimpleProvidedInterface {
    private $providedClass;

    public function setProvidedClass($providedClass) {
        $this->providedClass = $providedClass;
    }

    public function getProvidedClass() {
        return $this->providedClass;
    }
}

class DICTestProvidedDecorator implements DICTestProvidedInterface {

    private $providedClass;

    public function setProvidedClass($providedClass) {
        $this->providedClass = $providedClass;
    }

    public function getProvidedClass() {
        return $this->providedClass;
    }
}

class DICTestNamedProvidedImpl1Dependency {
    public $providedInterface1;
    public $providedInterface2;

    /**
     * @inject
     *
     * @named impl1 $providedInterface1
     * @named impl2 $providedInterface2
     */
    public function __construct(DICTestProvidedInterface $providedInterface1, DICTestProvidedInterface $providedInterface2) {
        $this->providedInterface1 = $providedInterface1;
        $this->providedInterface2 = $providedInterface2;
    }
}

class DICTestSimpleProvidedImplDependency {

    public $providedInterface;

    /**
     * @inject
     */
    public function __construct(DICTestSimpleProvidedInterface $providedInterface) {
        $this->providedInterface = $providedInterface;
    }

    /**
     * @inject
     *
     * @param DICTestSimpleProvidedInterface $providedInterface
     */
    public function someMethod(DICTestSimpleProvidedInterface $providedInterface) {
        return $providedInterface;
    }
}
class DICTestProvider implements \rg\injection\Provider {

    private $decorator;
    private $name;

    /**
     * @inject
     */
    public function __construct(DICTestProvidedDecorator $decorator, $name = null) {
        $this->decorator = $decorator;
        $this->name = $name;
    }

    public function get() {
        switch ($this->name) {
            case 'impl1':
                $this->decorator->setProvidedClass(new DICTestProvidedInterfaceImpl1());
                break;
            case 'impl2':
                $this->decorator->setProvidedClass(new DICTestProvidedInterfaceImpl2());
                break;
        }
        return $this->decorator;
    }
}


class DICTestProviderNoAnnotation implements \rg\injection\Provider {

    private $decorator;

    private $name;

    /**
     * @inject
     */
    public function __construct(DICTestProvidedDecorator $decorator, $name = null) {
        $this->decorator = $decorator;
        $this->name = $name;
    }

    public function get() {
        return new DICTestProvidedInterfaceNoConfigImpl($this->name);
    }
}

class DICTestProvidedInterfaceNoConfigImpl implements DICTestProvidedInterfaceNoConfig {
    public $name;

    public function __construct($name) {
        $this->name = $name;
    }
}

class DICSimpleTestProvider implements \rg\injection\Provider {

    private $decorator;

    private $name;

    /**
     * @inject
     */
    public function __construct(DICTestSimpleProvidedDecorator $decorator, $name = null) {
        $this->decorator = $decorator;
        $this->name = $name;
    }

    public function get() {
        switch ($this->name) {
            case 'impl1':
                $this->decorator->setProvidedClass(new DICTestProvidedInterfaceImpl1());
                break;
            case 'impl2':
                $this->decorator->setProvidedClass(new DICTestProvidedInterfaceImpl2());
                break;
        }
        return $this->decorator;
    }
}

class DICTestInterfaceDependency {

    /**
     * @inject
     * @var rg\injection\DICTestInterface
     */
    public $dependency;
}

class DICTestInterfaceDependencyTwo {

    public $dependency;

    /**
     * @inject
     *
     * @named impl1 $dependency
     */
    public function __construct(DICTestProvidedInterface $dependency) {
        $this->dependency = $dependency;
    }
}

class DICTestInterfaceDependencyTwoNoAnnotation {

    public $dependency;

    /**
     * @inject
     * @named impl1 $dependency
     */
    public function __construct(\rg\injection\DICTestProvidedInterfaceNoConfig $dependency) {
        $this->dependency = $dependency;
    }
}

class DICTestSimpleProvidedInterfaceDependency {
    public $dependency;

    /**
     * @inject
     *
     * @param DICTestSimpleProvidedInterface $dependency
     */
    public function __construct(DICTestSimpleProvidedInterface $dependency) {
        $this->dependency = $dependency;
    }
}

class DICTestAnnotatedInterfacePropertyInjection {
    /**
     * @inject
     * @var rg\injection\DICTestAnnotatedInterface
     */
    public $dependency;
}