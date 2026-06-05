<?php
/**
 * Singleton Master Class
 * @author Adam Ness <adam.ness@gmail.com>
 * @copyright 2008
 */

/**
 * @package Controllers
 */
class Singleton {

	// implements the 'singleton' design pattern.
	function getInstance ($class) {
		static $instances = array();  // array of instance names

		if (!array_key_exists($class, $instances)) {
			// instance does not exist, so create it
			$instances[$class] = new $class;
		}

		$instance =& $instances[$class];

		return $instance;
	}
}
