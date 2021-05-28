<?php
/**
 * @package   Phoca Cart
 * @author    Jan Pavelka - https://www.phoca.cz
 * @copyright Copyright (C) Jan Pavelka https://www.phoca.cz
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 and later
 * @cms       Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

class PlgUserPhocacart extends JPlugin
{
	
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{

		$user_billing_address = $this->params->get('user_billing_address', 1);
		$user_shipping_address = $this->params->get('user_shipping_address', 0);
		$order_billing_address = $this->params->get('order_billing_address', 0);
		$order_shipping_address = $this->params->get('order_shipping_address', 0);
	
	
	
	
		if (isset($user['id']) && (int)$user['id'] > 0 && isset($user['email'])) {
			
			$db = JFactory::getDBO();
			
			// BILLING ADDRESS
			$query = ' SELECT id, email FROM #__phocacart_users AS a'
			    .' WHERE a.user_id = '.(int) $user['id']
				.' AND a.type = 0'
				.' LIMIT 1';

			$db->setQuery($query);
			$userPc = $db->loadAssoc();
			
			if (isset($userPc['email']) && $userPc['email'] != '') {
				if ($userPc['email'] != $user['email'] && JMailHelper::isEmailAddress($user['email'])) {
					// Change email in Phoca Cart User table
					if ($user_billing_address == 1) {
						$query = 'UPDATE #__phocacart_users SET'
								. ' email = ' . $db->quote($user['email'])
								. ' WHERE user_id = ' . (int) $user['id']
								. ' AND type = 0';
						$db->setQuery($query);
						$db->execute();	
					}
					// Change emails in existing orders
					if ($order_billing_address == 1 && isset($userPc['id']) && (int)$userPc['id'] > 0) {
						$query = 'UPDATE #__phocacart_order_users SET'
                            . ' email = CASE WHEN email = \'\' OR email IS NULL THEN \'\' ELSE ' . $db->quote($user['email']) . ' END'
                            . ' WHERE user_address_id = ' . (int) $userPc['id'];
						$db->setQuery($query);
						$db->execute();
					}
				}
			}
			
			
			$userPc = array();
			
			// SHIPPING ADDRESS
			$query = ' SELECT id, email FROM #__phocacart_users AS a'
			    .' WHERE a.user_id = '.(int) $user['id']
				.' AND a.type = 1'
				.' LIMIT 1';

			$db->setQuery($query);
			$userPc = $db->loadAssoc();
			
			if (isset($userPc['email']) && $userPc['email'] != '') {
				if ($userPc['email'] != $user['email'] && JMailHelper::isEmailAddress($user['email'])) {
					// Change email in Phoca Cart User table
					if ($user_shipping_address == 1) {
						$query = 'UPDATE #__phocacart_users SET'
								. ' email = ' . $db->quote($user['email'])
								. ' WHERE user_id = ' . (int) $user['id']
								. ' AND type = 1';
						$db->setQuery($query);
						$db->execute();
					}
					// Change emails in existing orders
					if ($order_shipping_address == 1 && isset($userPc['id']) && (int)$userPc['id'] > 0) {
						$query = 'UPDATE #__phocacart_order_users SET'
							. ' email = CASE WHEN email = \'\' OR email IS NULL THEN \'\' ELSE ' . $db->quote($user['email']) . ' END'
							. ' WHERE user_address_id = ' . (int) $userPc['id'];
						$db->setQuery($query);
						$db->execute();
					}
				}
			}
		}
	}
}
