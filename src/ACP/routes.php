<?php

use ACP\Routes\Forums;
use ACP\Routes\Groups;
use ACP\Routes\Index;
use ACP\Routes\Members;
use ACP\Routes\Posting;
use ACP\Routes\Settings;
use ACP\Routes\Themes;
use ACP\Routes\Tools;
use Jax\Router;

function routes(Router $router)
{
    $router->get('', '/', Index::class);
    $router->get('forums', '/Forums/{do}', Forums::class);
    $router->get('groups', '/Groups/{do}', Groups::class);
    $router->get('members', '/Members/{do}', Members::class);
    $router->get('posting', '/Posting/{do}', Posting::class);
    $router->get('settings', '/Settings/{do}', Settings::class);
    $router->get('themes', '/Themes/{do}', Themes::class);
    $router->get('tools', '/Tools/{do}', Tools::class);
}

;
