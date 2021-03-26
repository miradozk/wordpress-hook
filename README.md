## wordpress-hook

Ce librairie permet de travailler en mode POO avec les hooks de WordPress.

## Fonctionnalités

- Surcharge des hooks (add_action, add_filter, shortcode, activation et desactivation, WP_CLI)
- Mise à jour avec un serveur personnalisé
- Log avec Monolog

## Exemple

```
<?php
namespace MyPlugin;

use MiradoZk\WordPressHook\Hook;

class Application extends Hook
{
    /**
     * Plugin Repository URL
     *
     * @var string
     */
    protected $hub = 'https://myserver.com/?plugin=';


    public function boot()
    {
        // add_action('save_post', 'callback', 10, 1);
        $this->action('save_post', 'callback', 1, 10);
        
        // add_filter('the_content', 'callback', 10, 1);
        $this->filter('the_content', 'callback', 1, 10);
        
        // register_activation_hook(__FILE__, 'callback');
        $this->install('callback');
        
        // register_deactivation_hook(__FILE__, 'callback');
        $this->uninstall('callback');        
        
        // Log
        $logger = $this->getLogger();
    }
}
```
