<?php

declare(strict_types=1);

namespace Jax;

use Jax\Routes\API;
use Jax\Routes\Asteroids;
use Jax\Routes\Badges;
use Jax\Routes\BoardIndex;
use Jax\Routes\Calendar;
use Jax\Routes\Confetti;
use Jax\Routes\Contacts;
use Jax\Routes\Download;
use Jax\Routes\Earthbound;
use Jax\Routes\Forum;
use Jax\Routes\Katamari;
use Jax\Routes\LogReg;
use Jax\Routes\Manifest;
use Jax\Routes\Members;
use Jax\Routes\ModControls;
use Jax\Routes\Nope;
use Jax\Routes\Post;
use Jax\Routes\Rainbow;
use Jax\Routes\Report;
use Jax\Routes\Search;
use Jax\Routes\Snow;
use Jax\Routes\Solitaire;
use Jax\Routes\Spin;
use Jax\Routes\Tardis;
use Jax\Routes\Ticker;
use Jax\Routes\Topic;
use Jax\Routes\UCP;
use Jax\Routes\UserProfile;

function routes(Router $router): void
{
    $router->get('', '/', BoardIndex::class);
    $router->get('api', '/api/{method}', API::class);
    $router->get('badges', '/badges', Badges::class);
    $router->get('calendar', '/calendar', Calendar::class);
    $router->get('category', '/', BoardIndex::class);
    $router->get('contacts', '/contacts', Contacts::class);
    $router->get('download', '/download', Download::class);
    $router->get('index', '/', BoardIndex::class);
    $router->get('members', '/members', Members::class);
    $router->get('modcontrols', '/modcontrols/{do}', ModControls::class);
    $router->get('forum', '/forum/{id}/{slug}', Forum::class);
    $router->get('manifest.json', '/manifest.json', Manifest::class);
    $router->get('post', '/post', Post::class);
    $router->get('profile', '/profile/{id}/{page}', UserProfile::class);
    $router->get('report', '/report', Report::class);
    $router->get('search', '/search', Search::class);
    $router->get('ticker', '/ticker', Ticker::class);
    $router->get('topic', '/topic/{id}/{slug}', Topic::class);
    $router->get('ucp', '/ucp/{what}', UCP::class);

    // Easter eggs
    $router->get('asteroids', '/asteroids', Asteroids::class);
    $router->get('confetti', '/confetti/{count}', Confetti::class);
    $router->get('earthbound', '/earthbound', Earthbound::class);
    $router->get('katamari', '/katamari', Katamari::class);
    $router->get('nope', '/nope', Nope::class);
    $router->get('rainbow', '/rainbow', Rainbow::class);
    $router->get('snow', '/snow/{snowFlakeCount}', Snow::class);
    $router->get('solitaire', '/solitaire', Solitaire::class);
    $router->get('spin', '/spin', Spin::class);
    $router->get('tardis', '/tardis', Tardis::class);

    // Authentication
    $router->get('register', '/register', LogReg::class);
    $router->get('logout', '/logout', LogReg::class);
    $router->get('login', '/login', LogReg::class);
    $router->get('toggleInvisible', '/toggleInvisible', LogReg::class);
    $router->get('forgotPassword', '/forgotPassword', LogReg::class);
}
