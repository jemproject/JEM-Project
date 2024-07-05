<?php
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Quickicon\Jem\Extension\JemQuickIcon;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new JemQuickIcon(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('quickicon', 'jemquickicon')
                );
                $plugin->setApplication(\Joomla\CMS\Factory::getApplication());

                return $plugin;
            }
        );
    }
};