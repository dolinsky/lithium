<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\mocks\action;

class MockRequestType extends \lithium\action\Request {

	public function type($raw = false) {
		return 'foo';
	}

	public function accepts($type = null) {
		return 'foo';
	}
}

?>