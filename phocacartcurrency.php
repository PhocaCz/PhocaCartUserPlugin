<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Factory;


defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );


class plgSystemPhocacartCurrency extends JPlugin
{

	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
	}

	//function onAfterInitialise() {
	function onAfterRoute(){

		$session = Factory::getSession();
		$id = $session->get('currency', 0, 'phocaCart');


		$app 	= Factory::getApplication();
		$option	= $app->input->get('option');
		$view	= $app->input->get('view');
		$format = $app->input->get('format');

		if ($format == 'feed' || $format == 'pdf' || $format == 'json' || $format == 'raw' || $format == 'xml') {
			return true;
		}

		if ($app->getName() != 'site') {
			return;
		}

		$items = $this->params->get('items', []);

		$langTag = Factory::getLanguage()->getTag();

		if (!empty($items)) {
			foreach ($items as $k => $v) {

				if (isset($v->item_language) && $v->item_language != '' && $v->item_language == $langTag) {

					// if there is no currency set, don't set the default by my own specific
					if ((int)$id < 1) {
					   $idCur = (int)$v->item_currency;
					   $session->set('currency', (int)$idCur, 'phocaCart');
					   return true;
					}
					return false;
				}

			}
		}
	}
}
?>
