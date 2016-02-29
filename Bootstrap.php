<?php

use Shopware\Plugins\VersionCentralTracker\Components\Credentials;
use Shopware\Plugins\VersionCentralTracker\Components\HttpClient;

class Shopware_Plugins_Core_VersionCentralTracker_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var array
     */
    protected $pluginInfo = [];

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return [
            'install' => true,
            'enable'  => true,
            'update'  => true,
        ];
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return [
            'version'     => $this->getVersion(),
            'author'      => $this->getPluginInfo()['author'],
            'label'       => $this->getLabel(),
            'description' => str_replace('%label%', $this->getLabel(), file_get_contents(sprintf('%s/plugin.txt', __DIR__))),
            'copyright'   => $this->getPluginInfo()['copyright'],
            'support'     => $this->getPluginInfo()['support'],
            'link'        => $this->getPluginInfo()['link'],
        ];
    }

    /**
     * @return array
     */
    protected function getPluginInfo()
    {
        if ($this->pluginInfo === []) {
            $file = sprintf('%s/plugin.json', __DIR__);

            if (!file_exists($file) || !is_file($file)) {
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
        return (string) $this->getPluginInfo()['label']['de'];
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
        $config = $this->Config();

        $credentials = new Credentials(
            $config->get('versionCentralApiCredentials')
        );

        $httpClient = new HttpClient($credentials);
        $response = $httpClient->request($httpClient::HEAD);

        if (intval($response->getStatus()/100) !== 2) {
            throw new Exception(print_r($response, true));
        }

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
        $versionClosures = [

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
        ];

        foreach ($versionClosures as $version => $versionClosure) {
            if (version_compare($oldVersion, $this->getVersion(), '<')) {
                if (!$versionClosure($this)) {
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
            $this->Forms()->findOneBy(['name' => 'Interface'])
        );

        $form->setElement(
            'text',
            'versionCentralApiCredentials',
            [
                'label' => 'API Credentials',
                'value' => null,
                'required' => true,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ]
        );
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function onConsoleAddCommand(Enlight_Event_EventArgs $args)
    {
        return new \Doctrine\Common\Collections\ArrayCollection([
            new \Shopware\Plugins\VersionCentralTracker\Commands\TrackerUpdateCommand(),
        ]);
    }

    public function afterConfigSave(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $values = [];
        foreach ($request->getParam('elements') as $element) {
            $values[$element['name']] = $element['values'][0]['value'];
        }

        $credentials = new Credentials(
            $values['versionCentralApiCredentials']
        );

        $httpClient = new HttpClient($credentials);
        $response = $httpClient->request($httpClient::HEAD);

        if (intval($response->getStatus()/100) === 2) {
            $result = [
                'success' => true
            ];
        } else {
            $result = [
                'success' => false,
                'message' => 'AUTHORIZATION_INVALID'
            ];
        }

        $args->getSubject()->View()->assign($result);
        return;
    }


    /**
     * @param Shopware_Components_Cron_CronJob $job
     * @return bool
     */
    public function executeUpdateCron(Shopware_Components_Cron_CronJob $job)
    {
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        $container = $this->Application()->Container();

        try {
            $trackerUpdate = new Shopware\Plugins\VersionCentralTracker\Service\TrackerUpdate(
                $output, $container->get('models'), $container->get('config')
            );
            $trackerUpdate->execute();
            return $trackerUpdate->getOutput()->fetch();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
