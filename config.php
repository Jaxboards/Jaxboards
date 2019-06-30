<?php
/**
 * JaxBoards config file. It's just JSON embedded in PHP- wow!
 *
 * PHP Version 5.3.0
 *
 * @category Jaxboards
 * @package  Jaxboards
 *
 * @author  Sean Johnson <seanjohnson08@gmail.com>
 * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @link https://github.com/Jaxboards/Jaxboards Jaxboards on Github
 */
$CFG = json_decode(file_get_contents('config.json'), true);
