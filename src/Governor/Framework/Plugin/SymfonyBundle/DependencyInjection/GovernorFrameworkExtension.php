<?php

namespace Governor\Framework\Plugin\SymfonyBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class GovernorFrameworkExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration, $configs);

        $container->setAlias('governor.lock_manager',
            new Alias(sprintf('governor.lock_manager.%s',
                $config['lock_manager'])));

        $loader = new XmlFileLoader($container,
            new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('governor.aggregate_locations',
            $config['aggregate_locations']);

        // configure repositories 
        $this->loadRepositories($config, $container);
    }

    private function loadRepositories($config, ContainerBuilder $container)
    {
        foreach ($config['repositories'] as $name => $parameters) {
            //  $container->setDefinition($name . '.repository', new DefinitionDecorator('governor.repository'))
            //  ->a
            //   print_r($parameters);
            /*

              $definition = $container->findDefinition($id);

              if (null === $tagAggregateRoot) {
              throw new \RuntimeException(sprintf("Missing aggregateRoot attribute on the tagged governor repository [%s]",
              $id));
              }

              // set the first argument based on the entity in the service tag
              $arguments = $definition->getArguments();
              $arguments[0] = $tagAggregateRoot;

              $definition->setArguments($arguments);
              $container->setDefinition($id, $definition); */
        }
    }

}
