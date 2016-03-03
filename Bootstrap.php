<?php

use Shopware\Plugins\VersionCentralTracker\Components\Credentials;
use Shopware\Plugins\VersionCentralTracker\Components\HttpClient;

class Shopware_Plugins_Core_VersionCentralTracker_Bootstrap extends Shopware_Components_Plugin_Bootstrap
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
        return array(
            'version'     => $this->getVersion(),
            'author'      => $this->getPluginInfo()['author'],
            'label'       => $this->getLabel(),
            'description' => str_replace('%label%', $this->getLabel(),
                file_get_contents(sprintf('%s/plugin.txt', __DIR__))),
            'copyright'   => $this->getPluginInfo()['copyright'],
            'support'     => $this->getPluginInfo()['support'],
            'link'        => $this->getPluginInfo()['link'],
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
        return (string)$this->getPluginInfo()['label']['de'];
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->getPluginInfo()['currentVersion'];
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
        $trackerUpdate = new Shopware\Plugins\VersionCentralTracker\Service\TrackerUpdate(
            $output, $container->get('models')
        );
        $trackerUpdate->execute();

        return true;
    }

    public function afterInit()
    {
        $this->get('Loader')->registerNamespace(
            'Shopware\Plugins\VersionCentralTracker',
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

            '0.0.1' => function (Shopware_Plugins_Core_VersionCentralTracker_Bootstrap $bootstrap) {
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
                    'VersionCentralTrackerUpdate',
                    'VersionCentralTrackerUpdateCron',
                    60 * 60 * 24,
                    true
                );
                $bootstrap->subscribeEvent(
                    'Shopware_CronJob_VersionCentralTrackerUpdateCron',
                    'executeUpdateCron'
                );

                return true;
            },
        );

        foreach ($versionClosures as $version => $versionClosure) {
            if (version_compare($oldVersion, $this->getVersion(), '<')) {
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
            new \Shopware\Plugins\VersionCentralTracker\Commands\TrackerUpdateCommand(),
        ));
    }

    public function afterConfigSave(Enlight_Hook_HookArgs $args)
    {
        $credentials = new Credentials(
           $this->Config()['versionCentralApiCredentials']
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

        $trackerUpdate = new Shopware\Plugins\VersionCentralTracker\Service\TrackerUpdate(
            $output, $container->get('models')
        );
        $trackerUpdate->execute();

        return $trackerUpdate->getOutput()->fetch();
    }
}
