<?php

use Shopware\Plugins\K10rVersionCentralTracker\Components\Credentials;
use Shopware\Plugins\K10rVersionCentralTracker\Components\HttpClient;

class Shopware_Plugins_Core_K10rVersionCentralTracker_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var array
     */
    protected $pluginInfo = array();

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'enable'  => true,
            'update'  => true,
        );
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        $pluginInfo = $this->getPluginInfo();
        return array(
            'version'     => $this->getVersion(),
            'author'      => $pluginInfo['author'],
            'label'       => $this->getLabel(),
            'description' => str_replace('%label%', $this->getLabel(),
                file_get_contents(sprintf('%s/plugin.txt', __DIR__))),
            'copyright'   => $pluginInfo['copyright'],
            'support'     => $pluginInfo['support'],
            'link'        => $pluginInfo['link'],
        );
    }

    /**
     * @return array
     */
    protected function getPluginInfo()
    {
        if ($this->pluginInfo === array()) {
            $file = sprintf('%s/plugin.json', __DIR__);

            if ( ! file_exists($file) || ! is_file($file)) {
                throw new \RuntimeException('The plugin has an invalid version file.');
            }

            $this->pluginInfo = json_decode(file_get_contents($file), true);
        }

        return $this->pluginInfo;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        $pluginInfo = $this->getPluginInfo();
        return (string)$pluginInfo['label']['de'];
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        $pluginInfo = $this->getPluginInfo();
        return $pluginInfo['currentVersion'];
    }

    /**
     * @return bool
     */
    public function install()
    {
        return $this->createEvents();
    }

    /**
     * @param string $oldVersion
     *
     * @return bool
     */
    public function update($oldVersion)
    {
        return $this->createEvents($oldVersion);
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public function enable()
    {
        $output = new Symfony\Component\Console\Output\NullOutput();
        $container = $this->Application()->Container();
        $trackerUpdate = new Shopware\Plugins\K10rVersionCentralTracker\Service\TrackerUpdate(
            $output, $container->get('models')
        );
        $trackerUpdate->execute();

        return true;
    }

    public function afterInit()
    {
        $this->get('Loader')->registerNamespace(
            'Shopware\Plugins\K10rVersionCentralTracker',
            $this->Path()
        );
    }

    /**
     * @param null|string $oldVersion
     *
     * @return bool
     */
    private function createEvents($oldVersion = null)
    {
        $versionClosures = array(

            '1.0.0' => function (Shopware_Plugins_Core_K10rVersionCentralTracker_Bootstrap $bootstrap) {
                $bootstrap->addConfigurationForm();

                $bootstrap->subscribeEvent(
                    'Shopware_Console_Add_Command',
                    'onConsoleAddCommand'
                );

                $bootstrap->subscribeEvent(
                    'Shopware_Controllers_Backend_Config::saveFormAction::after',
                    'afterConfigSave'
                );

                $bootstrap->createCronJob(
                    'K10rVersionCentralTrackerUpdate',
                    'K10rVersionCentralTrackerUpdateCron',
                    60 * 60 * 24,
                    true
                );
                $bootstrap->subscribeEvent(
                    'Shopware_CronJob_K10rVersionCentralTrackerUpdateCron',
                    'executeUpdateCron'
                );

                return true;
            },
        );

        foreach ($versionClosures as $version => $versionClosure) {
            if ($oldVersion === null || version_compare($oldVersion, $this->getVersion(), '<')) {
                if ( ! $versionClosure($this)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function addConfigurationForm()
    {
        $form = $this->Form();

        $form->setParent(
            $this->Forms()->findOneBy(array('name' => 'Interface'))
        );

        $form->setElement(
            'text',
            'versionCentralApiCredentials',
            array(
                'label'    => 'API Credentials',
                'value'    => null,
                'required' => true,
                'scope'    => Shopware\Models\Config\Element::SCOPE_SHOP,
            )
        );
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function onConsoleAddCommand(Enlight_Event_EventArgs $args)
    {
        return new \Doctrine\Common\Collections\ArrayCollection(array(
            new \Shopware\Plugins\K10rVersionCentralTracker\Commands\TrackerUpdateCommand(),
        ));
    }

    public function afterConfigSave(Enlight_Hook_HookArgs $args)
    {
        $config = $this->Config();
        $credentials = new Credentials(
           $config['versionCentralApiCredentials']
        );

        $httpClient = new HttpClient($credentials);
        $response = $httpClient->request($httpClient::HEAD);

        if (intval($response->getStatus() / 100) === 2) {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            try {
                $result = array(
                    'success' => true,
                    'message' => $this->executeUpdate($output),
                );
            } catch(Exception $e) {
                $result = array(
                    'success' => false,
                    'message' => $e->getMessage(),
                );
            }
        } else {
            $result = array(
                'success' => false,
                'message' => 'Verbindung nicht erfolgreich, bitte prüfen Sie Ihre API-Daten.',
            );
        }

        $args->getSubject()->View()->assign($result);
    }


    /**
     * @param Shopware_Components_Cron_CronJob $job
     * @return bool
     */
    public function executeUpdateCron(Shopware_Components_Cron_CronJob $job)
    {
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        try {
            return $this->executeUpdate($output);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    protected function executeUpdate($output) {
        $container = $this->Application()->Container();

        $trackerUpdate = new Shopware\Plugins\K10rVersionCentralTracker\Service\TrackerUpdate(
            $output, $container->get('models')
        );
        $trackerUpdate->execute();

        return $trackerUpdate->getOutput()->fetch();
    }
}
