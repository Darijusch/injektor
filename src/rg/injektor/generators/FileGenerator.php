<?php
namespace rg\injektor\generators;
/*
 * This file is part of rg\injektor.
 *
 * (c) ResearchGate GmbH <bastian.hofmann@researchgate.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Zend\Code\Generator;
use rg\injektor\Configuration;
use rg\injektor\FactoryDependencyInjectionContainer;

class FileGenerator {

    /**
     * @var FactoryGenerator
     */
    private $factoryGenerator;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var string
     */
    private $factoryPath;

    /**
     * @var string
     */
    private $fullClassName;

    /**
     * @var array
     */
    private $constructorArgumentStringParts = array();

    /**
     * @var array
     */
    private $usedFactories = array();

    /**
     * @var array
     */
    private $realConstructorArgumentStringParts = array();

    /**
     * @var array
     */
    private $constructorArguments = array();

    /**
     * @var array
     */
    private $injectableArguments = array();

    /**
     * @var array
     */
    private $injectableProperties = array();

    /**
     * @var FactoryDependencyInjectionContainer
     */
    private $dic;
    
    /**
     * @param FactoryGenerator $factoryGenerator
     * @param Configuration $config
     * @param string $factoryPath
     * @param string $fullClassName
     */
    public function __construct(FactoryGenerator $factoryGenerator, Configuration $config, $factoryPath, $fullClassName) {
        $this->factoryGenerator = $factoryGenerator;
        $this->config = $config;
        $this->factoryPath = $factoryPath;
        $this->fullClassName = $fullClassName;
        $this->dic = new FactoryDependencyInjectionContainer($this->config);
    }

    /**
     * @return \Zend\Code\Generator\FileGenerator|null
     */
    public function getGeneratedFile() {
        $classConfig = $this->config->getClassConfig($this->fullClassName);
        $factoryName = $this->dic->getFactoryClassName($this->fullClassName);

        $classReflection = $this->dic->getClassReflection($this->fullClassName);

        if (strpos($classReflection->getDocComment(), '@generator ignore') !== false) {
            return null;
        }

        $file = new Generator\FileGenerator();

        $factoryClass = new \rg\injektor\generators\FactoryClass($factoryName);
        $instanceMethod = new \rg\injektor\generators\InstanceMethod($this->factoryGenerator);

        $arguments = array();

        $constructorMethodReflection = null;
        if ($this->dic->isSingleton($classReflection)) {
            $constructorMethodReflection = $classReflection->getMethod('getInstance');
            $arguments = $constructorMethodReflection->getParameters();
        } else  if ($classReflection->hasMethod('__construct')) {
            $constructorMethodReflection = $classReflection->getMethod('__construct');
            $arguments = $constructorMethodReflection->getParameters();
        }

        $isSingleton = $this->dic->isConfiguredAsSingleton($classConfig, $classReflection);

        $body = '';

        if ($isSingleton) {
            $property = new Generator\PropertyGenerator('instance', array(), Generator\PropertyGenerator::FLAG_PRIVATE);
            $property->setStatic(true);
            $factoryClass->setProperty($property);

            $body = '$singletonKey = json_encode($parameters) . "#" . getmypid();' . PHP_EOL;
            $body .= 'if (isset(self::$instance[$singletonKey])) {' . PHP_EOL;
            $body .= '    return self::$instance[$singletonKey];' . PHP_EOL;
            $body .= '}' . PHP_EOL . PHP_EOL;
        }
        $bottomBody = '';

        $providerClassName = $this->dic->getProviderClassName($classConfig, new \ReflectionClass($this->fullClassName), null);
        if ($providerClassName && $providerClassName->getClassName()) {
            $argumentFactory = $this->dic->getFullFactoryClassName($providerClassName->getClassName());
            $this->factoryGenerator->processFileForClass($providerClassName->getClassName());
            $body .= '$instance = \\' . $argumentFactory . '::getInstance(array())->get();' . PHP_EOL;
            $this->usedFactories[] = $argumentFactory;
        } else {
            // constructor method arguments

            foreach ($arguments as $argument) {
                /** @var \ReflectionParameter $argument  */

                $injectionParameter = new \rg\injektor\generators\InjectionParameter(
                    $argument,
                    $classConfig,
                    $this->config,
                    $this->dic
                );

                $argumentName = $argument->name;

                try {
                    if ($injectionParameter->getClassName()) {
                        $this->factoryGenerator->processFileForClass($injectionParameter->getClassName());
                    }
                    if ($injectionParameter->getFactoryName()) {
                        $this->usedFactories[] = $injectionParameter->getFactoryName();
                    }
                    $body .= $injectionParameter->getPreProcessingBody();
                    $bottomBody .= $injectionParameter->getPostProcessingBody();
                } catch (\Exception $e) {
                    $body .= $injectionParameter->getDefaultPreProcessingBody();
                    $bottomBody .= $injectionParameter->getDefaultPostProcessingBody();
                }
                $this->constructorArguments[] = $argumentName;
                $this->constructorArgumentStringParts[] = '$' . $argumentName;
                $this->realConstructorArgumentStringParts[] = '$' . $argumentName;

            }

            // Property injection
            $body .= $this->injectProperties($classConfig, $classReflection);

            if ($constructorMethodReflection) {

                $body .= $this->injectBeforeAspects($constructorMethodReflection);

                $body .= $this->injectInterceptAspects($constructorMethodReflection);

            }
            $body .= $bottomBody;

            if (count($this->injectableProperties) > 0) {
                $proxyName = $this->dic->getProxyClassName($this->fullClassName);
                if ($this->dic->isSingleton($classReflection)) {
                    $file->setClass($this->createSingletonProxyClass($proxyName));
                    $body .= PHP_EOL . '$instance = ' . $proxyName . '::getProxyInstance(' . implode(', ', $this->constructorArgumentStringParts) . ');' . PHP_EOL;
                } else {
                    $file->setClass($this->createProxyClass($proxyName, $classReflection->hasMethod('__construct')));
                    $body .= PHP_EOL . '$instance = new ' . $proxyName . '(' . implode(', ', $this->constructorArgumentStringParts) . ');' . PHP_EOL;
                }
            } else {
                if ($this->dic->isSingleton($classReflection)) {
                    $body .= PHP_EOL . '$instance = \\' . $this->fullClassName . '::getInstance(' . implode(', ', $this->constructorArgumentStringParts) . ');' . PHP_EOL;
                } else {
                    $body .= PHP_EOL . '$instance = new \\' . $this->fullClassName . '(' . implode(', ', $this->constructorArgumentStringParts) . ');' . PHP_EOL;
                }

            }

            if ($constructorMethodReflection) {
                $body .= $this->injectAfterAspects($constructorMethodReflection, '$instance');
            }

        }

        if ($isSingleton) {
            $body .= 'self::$instance[$singletonKey] = $instance;' . PHP_EOL;
        }

        $body .= 'return $instance;' . PHP_EOL;

        $instanceMethod->setBody($body);
        $instanceMethod->setStatic(true);
        $factoryClass->setMethod($instanceMethod);

        // Add Factory Method
        $methods = $classReflection->getMethods();
        foreach ($methods as $method) {
            /** @var \ReflectionMethod $method */
            if ($method->isPublic() &&
                substr($method->name, 0, 2) !== '__' &&
                !$method->isStatic()
            ) {
                $factoryMethod = $this->getFactoryMethod($method, $classConfig);
                $factoryClass->setMethod($factoryMethod);
            }
        }

        // Generate File


        $file->setNamespace('rg\injektor\generated');
        $this->usedFactories = array_unique($this->usedFactories);
        foreach ($this->usedFactories as &$usedFactory) {
            $usedFactory = str_replace('rg\injektor\generated\\', '', $usedFactory);
            $usedFactory = $this->factoryPath . DIRECTORY_SEPARATOR . $usedFactory . '.php';
        }
        $file->setRequiredFiles($this->usedFactories);
        $file->setClass($factoryClass);
        $file->setFilename($this->factoryPath . DIRECTORY_SEPARATOR . $factoryName . '.php');

        return $file;
    }

    /**
     * @param array $classConfig
     * @param \ReflectionClass $classReflection
     * @return string
     */
    private function injectProperties(array $classConfig, \ReflectionClass $classReflection) {
        $body = '';
        try {
            $this->injectableProperties = $this->dic->getInjectableProperties($classReflection);
            foreach ($this->injectableProperties as $key => $injectableProperty) {
                /** @var \ReflectionProperty $injectableProperty */
                $propertyClass = $this->dic->getClassFromVarTypeHint($injectableProperty->getDocComment());
                if (!$propertyClass) {
                    unset($this->injectableProperties[$key]);
                    continue;
                }

                $injectionProperty = new \rg\injektor\generators\InjectionProperty(
                    $injectableProperty, $classConfig, $this->config, $this->dic
                );

                $propertyName = $injectableProperty->name;

                try {
                    if ($injectionProperty->getClassName()) {
                        $this->factoryGenerator->processFileForClass($injectionProperty->getClassName());
                    }
                    if ($injectionProperty->getFactoryName()) {
                        $this->usedFactories[] = $injectionProperty->getFactoryName();
                    }
                    $this->injectableArguments[] = $propertyName;
                    $this->constructorArguments[] = $propertyName;
                    $this->constructorArgumentStringParts[] = '$' . $propertyName;
                    $body .= $injectionProperty->getProcessingBody();
                } catch (\Exception $e) {
                    unset($this->injectableProperties[$key]);
                }
            }
        } catch (\Exception $e) {
        }
        return $body;
    }

    private function injectBeforeAspects(\ReflectionMethod $methodReflection) {
        $body = '';
        $beforeAspects = $this->dic->getAspects($methodReflection, 'before');
        foreach ($beforeAspects as $aspect) {
            $aspect['class'] = trim($aspect['class'], '\\');
            $aspectFactory = $this->dic->getFactoryClassName($aspect['class']);
            $body .= '$aspect = ' . $aspectFactory . '::getInstance();' . PHP_EOL;
            $body .= '$methodParameters = $aspect->execute(' . var_export($aspect['aspectArguments'], true) . ', \'' . $this->fullClassName . '\', \'' . $methodReflection->name . '\', $methodParameters);' . PHP_EOL;

            $this->usedFactories[] = $aspectFactory;
            $this->factoryGenerator->processFileForClass($aspect['class']);
        }

        return $body;
    }

    private function injectInterceptAspects(\ReflectionMethod $methodReflection) {
        $body = '';
        $interceptAspects = $this->dic->getAspects($methodReflection, 'intercept');
        if (count($interceptAspects) > 0) {
            $body .= '$result = false;' . PHP_EOL;
            foreach ($interceptAspects as $aspect) {
                $aspect['class'] = trim($aspect['class'], '\\');
                $aspectFactory = $this->dic->getFactoryClassName($aspect['class']);
                $body .= '$aspect = ' . $aspectFactory . '::getInstance();' . PHP_EOL;
                $body .= '$result = $aspect->execute(' . var_export($aspect['aspectArguments'], true) . ', \'' . $this->fullClassName . '\', \'' . $methodReflection->name . '\', $methodParameters, $result);' . PHP_EOL;
                $this->usedFactories[] = $aspectFactory;
                $this->factoryGenerator->processFileForClass($aspect['class']);
            }
            $body .= 'if ($result !== false) {' . PHP_EOL;
            $body .= '    return $result;' . PHP_EOL;
            $body .= '}' . PHP_EOL;
        }
        return $body;
    }

    private function injectAfterAspects(\ReflectionMethod $methodReflection, $returnVariableName) {
        $body = '';
        $afterAspects = $this->dic->getAspects($methodReflection, 'after');
        foreach ($afterAspects as $aspect) {
            $aspect['class'] = trim($aspect['class'], '\\');
            $aspectFactory = $this->dic->getFactoryClassName($aspect['class']);
            $body .= '$aspect = ' . $aspectFactory . '::getInstance();' . PHP_EOL;
            $body .= $returnVariableName . ' = $aspect->execute(' . var_export($aspect['aspectArguments'], true) . ', \'' . $this->fullClassName . '\', \'' . $methodReflection->name . '\', ' . $returnVariableName. ');' . PHP_EOL;
            $this->usedFactories[] = $aspectFactory;
            $this->factoryGenerator->processFileForClass($aspect['class']);
        }
        return $body;
    }

    protected function getFactoryMethod(\ReflectionMethod $method, $classConfig) {
        $factoryMethod = new Generator\MethodGenerator($this->dic->getFactoryMethodName($method->name));
        $factoryMethod->setParameter(new Generator\ParameterGenerator('object'));
        $factoryMethod->setStatic(true);

        $arguments = $method->getParameters();

        $body = '$methodParameters = array();' . PHP_EOL;

        if (count($arguments) > 0) {
            $factoryMethod->setParameter(new \Zend\Code\Generator\ParameterGenerator('parameters', 'array', array()));
        }
        $methodArgumentStringParts = array();

        $bottomBody = '';

        foreach ($arguments as $argument) {
            /** @var \ReflectionParameter $argument */

            $injectionParameter = new \rg\injektor\generators\InjectionParameter(
                $argument,
                $classConfig,
                $this->config,
                $this->dic
            );

            $argumentName = $argument->name;

            try {
                if ($injectionParameter->getClassName()) {
                    $this->factoryGenerator->processFileForClass($injectionParameter->getClassName());
                }
                if ($injectionParameter->getFactoryName()) {
                    $this->usedFactories[] = $injectionParameter->getFactoryName();
                }
                $body .= $injectionParameter->getPreProcessingBody();
                $bottomBody .= $injectionParameter->getPostProcessingBody();
            } catch (\Exception $e) {
                $body .= $injectionParameter->getDefaultPreProcessingBody();
                $bottomBody .= $injectionParameter->getDefaultPostProcessingBody();
            }

            $methodArgumentStringParts[] = '$' . $argumentName;
        }

        $body .= $this->injectBeforeAspects($method);

        $body .= $this->injectInterceptAspects($method);

        $body .=  $bottomBody;
        $body .= '$result = $object->' . $method->name . '(' . implode(', ', $methodArgumentStringParts) . ');' . PHP_EOL . PHP_EOL;

        $body .= $this->injectAfterAspects($method, '$result');

        $body .= PHP_EOL . 'return $result;';
        $factoryMethod->setBody($body);
        return $factoryMethod;
    }

    /**
     * @param string $proxyName
     * @param boolean $hasConstructor
     * @return \Zend\Code\Generator\ClassGenerator
     */
    private function createProxyClass($proxyName, $hasConstructor) {
        $proxyClass = new Generator\ClassGenerator($proxyName);
        $proxyClass->setExtendedClass('\\' . $this->fullClassName);
        $constructor = new Generator\MethodGenerator('__construct');
        foreach ($this->constructorArguments as $constructorArgument) {
            $parameter = new Generator\ParameterGenerator($constructorArgument);
            $constructor->setParameter($parameter);
        }
        $constructorBody = '';
        foreach ($this->injectableArguments as $injectableArgument) {
            $constructorBody .= '$this->' . $injectableArgument . ' = $' . $injectableArgument . ';' . PHP_EOL;
        }
        if ($hasConstructor) {
            $constructorBody .= 'parent::__construct(' . implode(', ', $this->realConstructorArgumentStringParts) . ');' . PHP_EOL;
        }
        $constructor->setBody($constructorBody);
        $proxyClass->setMethod($constructor);
        return $proxyClass;
    }

    /**
     * @param string $proxyName
     * @return \Zend\Code\Generator\ClassGenerator
     */
    private function createSingletonProxyClass($proxyName) {
        $proxyClass = new Generator\ClassGenerator($proxyName);
        $proxyClass->setExtendedClass('\\' . $this->fullClassName);
        $constructor = new Generator\MethodGenerator('getProxyInstance');
        $constructor->setStatic(true);
        foreach ($this->constructorArguments as $constructorArgument) {
            $parameter = new Generator\ParameterGenerator($constructorArgument);
            $constructor->setParameter($parameter);
        }
        $constructorBody = '$instance = parent::getInstance(' . implode(', ', $this->realConstructorArgumentStringParts) . ');' . PHP_EOL;
        ;
        foreach ($this->injectableArguments as $injectableArgument) {
            $constructorBody .= '$instance->' . $injectableArgument . ' = $' . $injectableArgument . ';' . PHP_EOL;
        }
        $constructorBody .= 'return $instance;' . PHP_EOL;
        $constructor->setBody($constructorBody);
        $proxyClass->setMethod($constructor);
        return $proxyClass;
    }
}