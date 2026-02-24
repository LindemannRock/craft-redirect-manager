<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\events;

use yii\base\Event;

/**
 * Redirect Event
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class RedirectEvent extends Event
{
    /**
     * @var array The redirect data
     */
    public array $redirect = [];

    /**
     * @var bool Whether the event is valid (can be set to false to prevent the action)
     */
    public bool $isValid = true;
}
