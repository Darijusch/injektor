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

/**
 * @implementedBy rg\injection\FactoryDependencyInjectionContainer
 * @generator ignore
 */
class DependencyInjectionContainer {

    /**
     * @var \rg\injection\Configuration
     */
    protected $config;

    /**
     * @var array
     */
    private $instances = array();

    /**
     * @var \rg\injection\DependencyInjectionContainer
     */
    private static $instance;

    /**
     * @var \rg\injection\SimpleAnnotationReader
     */
    private $annotationReader;

    /**
     * @param \rg\injection\Configuration $config
     */
    public function __construct(Configuration $config) {
        $this->config = $config;

        $className = get_class($this);

        $this->instances[$className .  json_encode(array())] = $this;
        $this->config->setClassConfig($className, array(
            'singleton' => true
        ));

        if ($className !== __CLASS__) {
            $this->instances[__CLASS__ . json_encode(array())] = $this;
            $this->config->setClassConfig(__CLASS__, array(
                'singleton' => true
            ));
        }

        self::$instance = $this;

        $this->annotationReader = new SimpleAnnotationReader();
    }

    /**
     * @return \rg\injection\Configuration
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @static
     * @return \rg\injection\DependencyInjectionContainer
     */
    public static function getInstance() {
        if (self::$instance) {
            return self::$instance;
        }

        throw new InjectionException('dependency injection container was not instantiated yet');
    }

    /**
     * @param string $fullClassName
     * @param array $constructorArguments
     * @return object
     */
    public function getInstanceOfClass($fullClassName, array $constructorArguments = array()) {
        $fullClassName = trim($fullClassName, '\\');

        $classConfig = $this->config->getClassConfig($fullClassName);

        $classReflection = new \ReflectionClass($fullClassName);

        if ($configuredInstance = $this->getConfiguredInstance($classConfig)) {
            return $configuredInstance;
        }

        if ($providedClass = $this->getProvidedConfiguredClass($classConfig, $classReflection)) {
            return $providedClass;
        }

        $fullClassName = $this->getRealConfiguredClassName($classConfig, $classReflection);

        $classReflection = $this->getClassReflection($fullClassName);

        $singletonKey = $fullClassName . json_encode($constructorArguments);

        if ($this->isConfiguredAsSingleton($classConfig, $classReflection) &&
            isset($this->instances[$singletonKey])
        ) {
            return $this->instances[$singletonKey];
        }

        $methodReflection = null;

        if ($this->isConfiguredAsSingleton($classConfig, $classReflection) &&
            $this->isSingleton($classReflection)
        ) {
            $methodReflection = $classReflection->getMethod('getInstance');
            $constructorArguments = $this->getConstructorArguments($classReflection, $classConfig, $constructorArguments, 'getInstance');
            $constructorArguments = $this->executeBeforeAspects($methodReflection, $constructorArguments);
            $interceptedResult = $this->executeInterceptorAspects($methodReflection, $constructorArguments);
            if ($interceptedResult !== false) {
                return $interceptedResult;
            }
            $instance = $classReflection->getMethod('getInstance')->invokeArgs(null, $constructorArguments);
        } else {
            $constructorArguments = $this->getConstructorArguments($classReflection, $classConfig, $constructorArguments);
            if ($classReflection->hasMethod('__construct')) {
                $methodReflection = $classReflection->getMethod('__construct');
                $constructorArguments = $this->executeBeforeAspects($methodReflection, $constructorArguments);
                $interceptedResult = $this->executeInterceptorAspects($methodReflection, $constructorArguments);
                if ($interceptedResult !== false) {
                    return $interceptedResult;
                }
            }

            if ($constructorArguments) {
                $instance = $classReflection->newInstanceArgs($constructorArguments);
            } else {
                $instance = $classReflection->newInstanceArgs();
            }
        }

        if ($this->isConfiguredAsSingleton($classConfig, $classReflection)) {
            $this->instances[$singletonKey] = $instance;
        }

        $instance = $this->injectProperties($classReflection, $instance);

        if ($methodReflection) {
            $instance = $this->executeAfterAspects($methodReflection, $instance);
        }

        return $instance;
    }

    /**
     * @param array $classConfig
     * @return object
     */
    protected function getConfiguredInstance($classConfig) {
        if (isset($classConfig['instance'])) {
            return $classConfig['instance'];
        }

        return null;
    }

    /**
     * @param \ReflectionClass $classReflection
     * @return bool
     */
    public function isSingleton(\ReflectionClass $classReflection) {
        return $classReflection->hasMethod('__construct') &&
            !$classReflection->getMethod('__construct')->isPublic() &&
            $classReflection->hasMethod('getInstance') &&
            $classReflection->getMethod('getInstance')->isStatic() &&
            $classReflection->getMethod('getInstance')->isPublic();
    }

    /**
     * @param \ReflectionClass $classReflection
     * @param array $classConfig
     * @param array $defaultConstructorArguments
     * @param string $constructorMethod
     * @return array
     */
    public function getConstructorArguments($classReflection, $classConfig, array $defaultConstructorArguments = array(), $constructorMethod = '__construct') {
        $methodReflection = $this->getMethodReflection($classReflection, $constructorMethod);

        if (!$methodReflection) {
            return array();
        }

        $defaultConstructorArguments = $this->getDefaultArguments($classConfig, $defaultConstructorArguments);

        return $this->getMethodArguments($methodReflection, $defaultConstructorArguments);
    }

    /**
     * @param $classConfig
     * @param $defaultConstructorArguments
     * @return array
     */
    private function getDefaultArguments($classConfig, $defaultConstructorArguments) {
        if (isset($classConfig['params']) && is_array($classConfig['params'])) {
            return array_merge($defaultConstructorArguments, $classConfig['params']);
        }
        return $defaultConstructorArguments;
    }

    /**
     * @param \ReflectionClass $classReflection
     * @param object $instance
     * @return object
     * @throws InjectionException
     */
    private function injectProperties($classReflection, $instance) {
        $properties = $this->getInjectableProperties($classReflection);
        foreach ($properties as $property) {
            $this->injectProperty($property, $instance);
        }
        return $instance;
    }

    /**
     * @param \ReflectionClass $classReflection
     * @return array
     */
    public function getInjectableProperties($classReflection) {
        $properties = $classReflection->getProperties();

        $injectableProperties = array();

        foreach ($properties as $property) {
            if ($this->isInjectable($property->getDocComment())) {
                if ($property->isPrivate()) {
                    throw new InjectionException('Property ' . $property->name . ' must not be private for property injection.');
                }
                $injectableProperties[] = $property;
            }
        }

        return $injectableProperties;
    }

    /**
     * @param \ReflectionProperty $property
     * @param object $instance
     * @throws InjectionException
     */
    private function injectProperty($property, $instance) {
        $fullClassName = $this->getClassFromVarTypeHint($property->getDocComment());
        if (! $fullClassName) {
            throw new InjectionException('Expected tag @var not found in doc comment.');
        }
        $propertyInstance = $this->getInstanceOfClass($fullClassName);
        $property->setAccessible(true);
        $property->setValue($instance, $propertyInstance);
        $property->setAccessible(false);
    }


    /**
     * @param string $docComment
     * @return string
     * @throws InjectionException
     */
    public function getClassFromVarTypeHint($docComment) {
        $class = $this->annotationReader->getClassFromVarTypeHint($docComment);
        $propertyClassConfig = $this->config->getClassConfig($class);
        $namedClass = $this->getNamedClassOfArgument($class, $propertyClassConfig, $docComment);
        if ($namedClass) {
            return $namedClass;
        }
        return $class;
    }

    /**
     * @param string $fullClassName
     * @return \ReflectionClass
     * @throws InjectionException
     */
    public function getClassReflection($fullClassName) {
        $classReflection = new \ReflectionClass($fullClassName);

        if ($classReflection->isAbstract()) {
            throw new InjectionException('Can not instanciate abstract class ' . $fullClassName);
        }

        if ($classReflection->isInterface()) {
            throw new InjectionException('Can not instanciate interface ' . $fullClassName);
        }
        return $classReflection;
    }

    /**
     * @param array $classConfig
     * @param \ReflectionClass $classReflection
     * @param string $name
     * @return null|object
     */
    public function getProvidedConfiguredClass($classConfig, \ReflectionClass $classReflection, $name = null) {
        if ($namedAnnoation = $this->getProviderClassName($classConfig, $classReflection, $name)) {
            return $this->getRealClassInstanceFromProvider($namedAnnoation->getClassName(), $classReflection->name, $namedAnnoation->getParameters());
        }

        return null;
    }

    /**
     * @param array $classConfig
     * @param \ReflectionClass $classReflection
     * @param string $name
     * @return annotations\Named
     */
    public function getProviderClassName($classConfig, $classReflection, $name) {
        if ($name && isset($classConfig['namedProviders']) && isset($classConfig['namedProviders'][$name])
            && isset($classConfig['namedProviders'][$name]['class'])) {
            $parameters = isset($classConfig['namedProviders'][$name]['parameters']) ? $classConfig['namedProviders'][$name]['parameters'] : array();
            $annotation = new \rg\injection\annotations\Named();
            $annotation->setClassName($classConfig['namedProviders'][$name]['class']);
            $annotation->setParameters($parameters);
            return $annotation;
        }
        if (isset($classConfig['provider']) && isset($classConfig['provider']['class'])) {
            $parameters = isset($classConfig['provider']['parameters']) ? $classConfig['provider']['parameters'] : array();
            $annotation = new \rg\injection\annotations\Named();
            $annotation->setClassName($classConfig['provider']['class']);
            $annotation->setParameters($parameters);
            return $annotation;
        }

        return $this->getProvidedByAnnotation($classReflection->getDocComment(), $name);
    }

    /**
     * @param array $classConfig
     * @param \ReflectionClass $classReflection
     * @return string
     */
    public function getRealConfiguredClassName($classConfig, \ReflectionClass $classReflection) {
        if (isset($classConfig['class'])) {
            return $classConfig['class'];
        }

        $annotatedClassName = $this->getAnnotatedImplementationClass($classReflection);
        if ($annotatedClassName) {
            return $annotatedClassName;
        }

        return $classReflection->name;
    }

    /**
     * @param \ReflectionClass $classReflection
     * @return string
     */
    private function getAnnotatedImplementationClass(\ReflectionClass $classReflection, $name = null) {
        $docComment = $classReflection->getDocComment();

        if ($namedAnnotation = $this->getImplementedByAnnotation($docComment, $name)) {
            return $namedAnnotation->getClassName();
        }

        return null;
    }

    /**
     * @param string $providerClassName
     * @param string $originalName
     * @param array $parameters
     * @return object
     * @throws InjectionException
     */
    private function getRealClassInstanceFromProvider($providerClassName, $originalName, array $parameters = array()) {
        /** @var Provider $provider  */
        $provider = $this->getInstanceOfClass($providerClassName, $parameters);

        if (!$provider instanceof Provider) {
            throw new InjectionException('Provider class ' . $providerClassName . ' specified in ' . $originalName . ' does not implement rg\injection\provider');
        }

        return $provider->get();
    }

    /**
     * @param string $docComment
     * @param string $name
     * @return \rg\injection\annotations\Named
     */
    private function getImplementedByAnnotation($docComment, $name) {
        return $this->getMatchingAnnotationByNamedPatter($docComment, '@implementedBy', $name);
    }

    /**
     * @param string $docComment
     * @param string $name
     * @return \rg\injection\annotations\Named
     */
    private function getProvidedByAnnotation($docComment, $name) {
        return $this->getMatchingAnnotationByNamedPatter($docComment, '@providedBy', $name);
    }

    /**
     * @param string $docComment
     * @param string $type
     * @param string $name
     * @return \rg\injection\annotations\Named
     */
    private function getMatchingAnnotationByNamedPatter($docComment, $type, $name) {
        $matches = array();

        $pattern = $this->createNamedPattern($type, $name);

        preg_match('/' . $pattern . '/', $docComment, $matches);

        if (isset($matches['className'])) {
            $annotation = new \rg\injection\annotations\Named();
            $annotation->setName($name);
            $annotation->setClassName($matches['className']);
            if (isset($matches['parameters'])) {
                $parameters = json_decode($matches['parameters'], true);
                if ($parameters) {
                    $annotation->setParameters($parameters);
                }
            }
            return $annotation;
        }

        return null;
    }

    private function createNamedPattern($type, $name) {
        $pattern = $type;
        if ($name) {
            $pattern .= '\s+' . preg_quote($name, '/');
        } else {
            $pattern .= '(\s+default)?';
        }
        $pattern .= '\s+(?P<className>[a-zA-Z0-9\\\]+)';
        $pattern .= '(\s+(?P<parameters>{[\s\:\'\",a-zA-Z0-9\\\]+}))?';

        return $pattern;
    }


    /**
     * @param array $classConfig
     * @param \ReflectionClass $classReflection
     * @return bool
     */
    public function isConfiguredAsSingleton(array $classConfig, \ReflectionClass $classReflection) {
        if (isset($classConfig['singleton'])) {
            return (bool) $classConfig['singleton'];
        }

        $classComment = $classReflection->getDocComment();

        return strpos($classComment, '@singleton') !== false;
    }

    /**
     * @param object $object
     * @param string $methodName
     * @param array $additionalArguments
     * @return mixed
     * @throws InjectionException
     */
    public function callMethodOnObject($object, $methodName, array $additionalArguments = array()) {
        $fullClassName = get_class($object);

        if (substr($methodName, 0, 2) === '__') {
            throw new InjectionException('You are not allowed to call magic method ' . $methodName . ' on ' . $fullClassName);
        }
        $classReflection = $this->getClassReflection($fullClassName);

        $methodReflection = $this->getMethodReflection($classReflection, $methodName);

        $this->checkAllowedHttpMethodAnnotation($methodReflection);

        $arguments = $this->getMethodArguments($methodReflection, $additionalArguments);

        $arguments = $this->executeBeforeAspects($methodReflection, $arguments);
        $interceptedResult = $this->executeInterceptorAspects($methodReflection, $arguments);
        if ($interceptedResult !== false) {
            return $interceptedResult;
        }

        $result = $methodReflection->invokeArgs($object, $arguments);

        return $this->executeAfterAspects($methodReflection, $result);
    }

    private function executeInterceptorAspects(\ReflectionMethod $methodReflection, $arguments) {
        $aspects = $this->getAspects($methodReflection, 'intercept');

        $result = false;

        foreach ($aspects as $aspect) {
            /** @var \rg\injection\aspects\Intercept $aspectInstance */
            $aspectInstance = $this->getInstanceOfClass($aspect['class']);
            $result = $aspectInstance->execute($aspect['aspectArguments'], $methodReflection->getDeclaringClass()->name, $methodReflection->name, $arguments, $result);
        }

        return $result;
    }

    private function executeBeforeAspects(\ReflectionMethod $methodReflection, $arguments) {
        $aspects = $this->getAspects($methodReflection, 'before');

        foreach ($aspects as $aspect) {
            /** @var \rg\injection\aspects\Before $aspectInstance */
            $aspectInstance = $this->getInstanceOfClass($aspect['class']);
            $arguments = $aspectInstance->execute($aspect['aspectArguments'], $methodReflection->getDeclaringClass()->name, $methodReflection->name, $arguments);
        }

        return $arguments;
    }

    private function executeAfterAspects(\ReflectionMethod $methodReflection, $result) {
        $aspects = $this->getAspects($methodReflection, 'after');

        foreach ($aspects as $aspect) {
            /** @var \rg\injection\aspects\After $aspectInstance */
            $aspectInstance = $this->getInstanceOfClass($aspect['class']);
            $result = $aspectInstance->execute($aspect['aspectArguments'], $methodReflection->getDeclaringClass()->name, $methodReflection->name, $result);
        }

        return $result;
    }

    public function getAspects(\ReflectionMethod $methodReflection, $type) {
        $docComment = $methodReflection->getDocComment();
        $matches = array();
        $pattern = '@' . $type . '\s+([a-z0-9\\\]+)\s*([a-z0-9\\\=&]*)';
        preg_match_all('/' . $pattern . '/i', $docComment, $matches);

        $aspects = array();

        if (isset($matches[1])) {
            foreach ($matches[1] as $key => $aspectClass) {
                $aspectArguments = array();
                if (isset($matches[2][$key])) {
                    parse_str($matches[2][$key], $aspectArguments);
                }
                $aspects[] = array(
                    'class' => $aspectClass,
                    'aspectArguments' => $aspectArguments,
                );
            }
        }

        return $aspects;
    }

    /**
     * @param \ReflectionMethod $methodReflection
     * @return
     */
    public function checkAllowedHttpMethodAnnotation(\ReflectionMethod $methodReflection) {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }

        $allowedHttpMethod = $this->getAllowedHttpMethod($methodReflection);

        if ($allowedHttpMethod && strtolower($allowedHttpMethod) !== strtolower($_SERVER['REQUEST_METHOD'])) {
            throw new \RuntimeException('invalid http method ' . $_SERVER['REQUEST_METHOD'] . ' for ' . $methodReflection->class . '::' . $methodReflection->name . '(), ' . $allowedHttpMethod . ' expected');
        }
    }

    /**
     * @param \ReflectionMethod $methodReflection
     * @return string
     */
    public function getAllowedHttpMethod(\ReflectionMethod $methodReflection) {
        $docComment = $methodReflection->getDocComment();
        $matches = array();
        preg_match('/@method\s+([a-z]+)/i', $docComment, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param \ReflectionClass $classReflection
     * @param string $methodName
     * @return null|\ReflectionMethod
     * @throws InjectionException
     */
    private function getMethodReflection(\ReflectionClass $classReflection, $methodName) {
        if (!$classReflection->hasMethod($methodName)) {
            if ($methodName === '__construct') {
                return null;
            }

            throw new InjectionException('Method ' . $methodName . ' not found in ' . $classReflection->name);
        }

        return $classReflection->getMethod($methodName);
    }

    /**
     * @param \ReflectionMethod $methodReflection
     * @param array $defaultArguments
     * @return array
     */
    public function getMethodArguments(\ReflectionMethod $methodReflection, array $defaultArguments = array()) {
        $arguments = $methodReflection->getParameters();

        $methodIsMarkedInjectible = $this->isInjectable($methodReflection->getDocComment());

        $argumentValues = array();

        foreach ($arguments as $argument) {
            /** @var \ReflectionParameter $argument */
            if (isset($defaultArguments[$argument->name])) {
                $argumentValues[$argument->name] = $this->getValueOfDefaultArgument($defaultArguments[$argument->name]);
            } else if ($methodIsMarkedInjectible) {
                $argumentValues[$argument->name] = $this->getInstanceOfArgument($argument);
            } else if ($argument->isOptional()) {
                $argumentValues[$argument->name] = $argument->getDefaultValue();
            } else if (!$argument->isOptional()) {
                throw new InjectionException('Parameter ' . $argument->name . ' in class ' . $methodReflection->class . ' is not injectable');
            }
        }

        return $argumentValues;
    }

    /**
     * @param array $argumentConfig
     * @return mixed
     */
    private function getValueOfDefaultArgument($argumentConfig) {
        if (!is_array($argumentConfig)) {
            return $argumentConfig;
        }
        if (isset($argumentConfig['value'])) {
            return $argumentConfig['value'];
        }
        if (isset($argumentConfig['class'])) {
            return $this->getInstanceOfClass($argumentConfig['class']);
        }
        return $argumentConfig;
    }

    /**
     * @param \ReflectionParameter $argument
     * @return object
     * @throws InjectionException
     */
    private function getInstanceOfArgument(\ReflectionParameter $argument) {
        if (!$argument->getClass()) {
            if ($argument->isOptional()) {
                return $argument->getDefaultValue();
            }
            throw new InjectionException('Invalid argument without class typehint class: [' . $argument->getDeclaringClass()->name . '] method: [' . $argument->getDeclaringFunction()->name . '] argument: [' . $argument->name . ']');
        }

        $argumentClassConfig = $this->config->getClassConfig($argument->getClass()->name);

        $providedInstance = $this->getNamedProvidedInstance($argument->getClass()->name, $argumentClassConfig, $argument->getDeclaringFunction()->getDocComment(), $argument->name);
        if ($providedInstance) {
            return $providedInstance;
        }

        $namedClassName = $this->getNamedClassOfArgument($argument->getClass()->name, $argumentClassConfig, $argument->getDeclaringFunction()->getDocComment(), $argument->name);
        if ($namedClassName) {
            return $this->getInstanceOfClass($namedClassName);
        }

        return $this->getInstanceOfClass($argument->getClass()->name);
    }

    /**
     * @param string $argumentClass
     * @param array $classConfig
     * @param string$docComment
     * @param string $argumentName
     * @return null|object
     */
    public function getNamedProvidedInstance($argumentClass, array $classConfig, $docComment, $argumentName = null) {
        $implementationName = $this->getImplementationName($docComment, $argumentName);

        return $this->getProvidedConfiguredClass($classConfig, new \ReflectionClass($argumentClass), $implementationName);
    }

    /**
     * @param string $argumentClass
     * @param array $classConfig
     * @param string $docComment
     * @param string $argumentName
     * @return string
     */
    public function getNamedClassOfArgument($argumentClass, array $classConfig, $docComment, $argumentName = null) {
        $implementationName = $this->getImplementationName($docComment, $argumentName);

        if ($implementationName) {
            return $this->getImplementingClassBecauseOfName($argumentClass, $classConfig, $implementationName);
        }
        return null;
    }

    /**
     * @param string $docComment
     * @param string $argumentName
     * @return string
     */
    public function getImplementationName($docComment, $argumentName) {
        $matches = array();
        $pattern = '@named\s+([a-zA-Z0-9\\\]+)';
        if ($argumentName) {
            $pattern .= '\s+\$' . preg_quote($argumentName, '/');
        }
        preg_match('/' . $pattern . '/', $docComment, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param string $argumentClass
     * @param array $classConfig
     * @param string $name
     * @return string
     * @throws InjectionException
     */
    private function getImplementingClassBecauseOfName($argumentClass, $classConfig, $name) {
        if (!isset($classConfig['named']) || !isset($classConfig['named'][$name])) {
            $classReflection = new \ReflectionClass($argumentClass);
            $annotatedConfigurationClassName = $this->getAnnotatedImplementationClass($classReflection, $name);
            if ($annotatedConfigurationClassName) {
                return $annotatedConfigurationClassName;
            }

            throw new InjectionException('Configuration for name ' . $name . ' not found.');
        }
        return $classConfig['named'][$name];
    }

    /**
     * @param $docComment
     * @return bool
     */
    public function isInjectable($docComment) {
        return strpos($docComment, '@inject') !== false;
    }

}
