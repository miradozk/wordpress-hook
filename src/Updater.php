<?php

namespace MiradoZk\WordPressHook;

use GuzzleHttp\Client;
use Throwable;

/**
 * Gestion des mises à jour.
 *
 * @author Mirado <miradozk@gmail.com>
 */
class Updater
{
    /**
     * Informations concernant le plugin.
     *
     * @var \stdClass
     */
    private $plugin;

    /**
     * URL du hub.
     *
     * @var string|null
     */
    private $hub = null;

    /**
     * Client HTTP.
     *
     * @var GuzzleHttp\Client
     */
    private $http;

    /**
     * Intégrer l'updater nécessite le nom de l'application.
     *
     * @param string $name
     */
    public function __construct(string $name, string $hub)
    {
        $this->hub = $hub;
        $this->plugin = new \stdClass();
        $this->plugin->plugin = $name;
        $this->plugin->slug = basename($name, '.php');
        $this->plugin->cacheId = sprintf('update_%s', $this->plugin->slug);

        $this->http = new Client([
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Télécharger la mise à jour.
     *
     * @param \stdClass $transient
     *
     * @return \stdClass
     */
    public function __invoke($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $result = get_transient($this->plugin->cacheId);

        if (false == $result) {
            try {
                $response = $this->http->request('GET', $this->hub . $this->plugin->slug);

                $result = json_decode($response->getBody()->getContents());

                if (isset($result->sections) && $result->sections) {
                    $result->sections = (array) $result->sections;
                }

                if (isset($result->banners) && $result->banners) {
                    $result->banners = (array) $result->banners;
                }

                set_transient($this->plugin->cacheId, $result, 360);
            } catch (Throwable $e) {
            }
        }

        if ($result) {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $metadata = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin->plugin);

            if ($result && -1 === version_compare($metadata['Version'], $result->Version)) {
                $this->plugin->new_version = $result->Version;
                $this->plugin->tested = $result->tested;
                $this->plugin->package = $result->download_link;

                $transient->response[$this->plugin->plugin] = $this->plugin;
            }
        }

        return $transient;
    }

    /**
     * Afficher les informations du plugins.
     *
     * @param stdClass $result
     * @param string   $action
     * @param stdClass $args
     *
     * @return mixed
     */
    public function tabs($result, $action, $args)
    {
        if ('plugin_information' != $action) {
            return false;
        }

        if ($this->plugin->slug !== $args->slug) {
            return $result;
        }

        $result = get_transient($this->plugin->cacheId);

        if (!$result) {
            try {
                $response = $this->http->request('GET', $this->hub . $this->plugin->slug);
                $response = json_decode($response->getBody()->getContents(), true);

                if (isset($result->sections)) {
                    $result->sections = (array) $result->sections;
                }

                if (isset($result->banners)) {
                    $result->banners = (array) $result->banners;
                }

                set_transient($this->plugin->cacheId, $result, 360);
            } catch (Throwable $e) {
                $result = false;
            }
        }

        return $result ?: $transient;
    }

    /**
     * Après mise à jour, nettoyez les caches.
     *
     * @param array $upgrader
     * @param array $options
     */
    public function updated($upgrader, $options)
    {
        if ('update' == $options['action'] && 'plugin' == $options['type']) {
            delete_transient($this->plugin->cacheId);
        }
    }
}
