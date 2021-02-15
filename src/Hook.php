<?php

namespace MiradoZk\WordPressHook;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * @author Mirado <miradozk@gmail.com>
 */
abstract class Hook
{
    /**
     * Liste des hooks.
     *
     * @var array
     */
    protected $hooks = [];

    /**
     * identifiant du plugin.
     *
     * @var string
     */
    protected $plugin;

    /**
     * Fichier principal du plugin
     *
     * @var string
     */
    protected $filename;

    /**
     * Gestion des logs.
     *
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * URL pour recuperer les informations sur le plugin.
     *
     * @var string|null
     */
    protected $hub = null;

    /**
     * Initialiser le plugin
     *
     * @param string $filename
     * @param boolean $logger
     */
    public function __construct(string $filename, $logger = true)
    {
        $this->filename = $filename;
        $this->plugin = plugin_basename($this->filename);

        $this->resetHooks();

        if ($logger) {
            $this->setLogger();
        }
    }

    /**
     * Initialiser les hooks
     *
     * @return void
     */
    protected function resetHooks()
    {
        $this->hooks['actions'] = [];
        $this->hooks['filters'] = [];
        $this->hooks['shortcodes'] = [];
        $this->hooks['install'] = [];
        $this->hooks['uninstall'] = [];
        $this->hooks['cli'] = [];
    }

    /**
     * Definir le logger
     *
     * @return void
     */
    protected function setLogger()
    {
        $this->logger = new Logger($this->plugin);
        $path = dirname($this->filename) . '/logs/' . basename($this->plugin, '.php') . '.log';
        $this->logger->pushHandler(new RotatingFileHandler($path, 4));
    }

    /**
     * Récuperer l'instance du logger.
     *
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Ajouter une action dans la registre.
     *
     * @param string   $hook
     * @param callable $callback
     * @param int      $args
     *
     * @return self
     */
    public function action($hook, $callback, $args = 0, $priority = 10): self
    {
        $this->hooks['actions'][] = [
            'callback' => $callback,
            'name' => $hook,
            'priority' => $priority,
            'args' => $args
        ];
        
        return $this;
    }

    /**
     * Ajouter un filter.
     *
     * @param string   $hook
     * @param callable $callback
     * @param int      $args
     *
     * @return self
     */
    public function filter($hook, $callback, $args = 0, $priority = 10): self
    {
        $this->hooks['filters'][] = [
            'callback' => $callback,
            'name' => $hook,
            'priority' => $priority,
            'args' => $args
        ];
        
        return $this;
    }

    /**
     * Ajouter un shortcode.
     *
     * @param string   $name
     * @param callable $callback
     *
     * @return self
     */
    public function shortcode($name, $callback) : self
    {
        $this->hooks['shortcodes'][] = [
            'name' => $name,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Ajouter une action à la desactivation du plugin.
     *
     * @param callable $callback
     *
     * @return self
     */
    public function uninstall(callable $callback): self
    {
        $this->hooks['uninstall'][] = [
            'name' => 'uninstall',
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Ajouter une action à l'activation du plugin.
     *
     * @param callable $callback
     *
     * @return self
     */
    public function install(callable $callback): self
    {
        $this->hooks['install'][] = [
            'name' => 'install',
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * Ajouter une commande WP-CLI.
     *
     * @param string   $commandname
     * @param callable $callback
     *
     * @return self
     */
    public function command($commandname, $callback): self
    {
        $this->hooks['cli'][] = [
            'callback' => $callback,
            'name' => $commandname,
        ];

        return $this;
    }

    /**
     * Déclarer les hooks.
     */
    abstract public function boot();

    /**
     * Executer les enregistrements des hooks.
     *
     * @return self
     */
    public function execute(): self
    {
        // Rendre updatable par defaut
        $this->updatable();

        // Enregistrer les hooks
        $this->boot();

        foreach ($this->hooks as $type => $hook) {
            foreach ($hook as $items) {
                switch ($type) {
                    case 'actions':
                        add_action($items['name'], $items['callback'], $items['priority'], $items['args']);
                        break;

                    case 'filters':
                        add_filter($items['name'], $items['callback'], $items['priority'], $items['args']);
                        break;

                    case 'shortcodes':
                        add_shortcode($items['name'], $items['callback']);
                        break;

                    case 'install':
                        register_activation_hook($this->plugin, $items['callback']);
                        break;

                    case 'uninstall':
                        register_deactivation_hook($this->plugin, $items['callback']);
                        break;

                    case 'cli':
                        if (defined('WP_CLI') && WP_CLI) {
                            \WP_CLI::add_command($items['name'], $items['callback']);
                        }
                        break;

                    default:
                        break;
                }
            }
        }

        return $this;
    }

    /**
     * Ajouter le gestionnaire de mise à jour.
     *
     * @return self
     */
    public function updatable(): self
    {
        $updater = new Updater($this->plugin, $this->hub);

        $this->filter('site_transient_update_plugins', $updater, 1, 10);
        $this->filter('plugins_api', [$updater, 'tabs'], 3, 20);
        $this->action('upgrader_process_complete', [$updater, 'updated'], 2, 10);

        return $this;
    }

    /**
     * Récuperer l'URL du plugin.
     *
     * @return string
     */
    public function url(): string
    {
        return plugin_dir_url($this->getFilename());
    }

    /**
     * Récuperer le dossier du plugin.
     *
     * @return string
     */
    public function path(): string
    {
        return plugin_dir_path($this->getFilename());
    }

    /**
     * Récuperer le filename du plugin (equivalent __FILE__).
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
