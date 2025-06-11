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

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Plugin\CMSPlugin;

defined('_JEXEC') or die;

class PlgUserPhocacart extends CMSPlugin
{

  /**
   * Helper function to load Phoca Cart classes
   *
   * @since 4.1.0
   */
  private function loadPhocaCart(): bool
  {
    \JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');
    return require_once JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/autoloadPhoca.php';
  }

  /**
   * changes user email address in Phoca Cart address book and/or in stored orders
   *
   * @param   int     $userId
   * @param   string  $email
   * @param   int     $addressType
   * @param   bool    $changeInAddress
   * @param   bool    $changeInOrders
   *
   *
   * @since 4.1.0
   */
  private function updateUserEmail(int $userId, string $email, int $addressType, bool $changeInAddress, bool $changeInOrders): void
  {
    if (!$changeInAddress && !$changeInOrders) {
      return;
    }

    $db = Factory::getDBO();

    $query = ' SELECT id, email FROM #__phocacart_users AS a'
      .' WHERE a.user_id = ' . $userId
      .' AND a.type = ' . $addressType
      .' LIMIT 1';

    $db->setQuery($query);
    $userPc = $db->loadObject();

    if ($userPc->email && $userPc->email != $email) {
      if ($changeInAddress) {
        $userPc->email = $email;
        $db->updateObject('#__phocacart_users', $userPc, 'id');
      }
        // Change emails in existing orders
      if ($changeInOrders) {
          $query = 'UPDATE #__phocacart_order_users SET'
            . ' email = CASE WHEN coalesce(email, ' . $db->quote('') . ') = ' . $db->quote('') . ' THEN ' . $db->quote('') . ' ELSE ' . $db->quote($email) . ' END'
            . ' WHERE user_address_id = ' . $userPc->id;
          $db->setQuery($query);
          $db->execute();
      }
    }
  }

  /**
   * Assigns user to "activate on registration groups" set in customers groups
   *
   * @param   int  $userId
   *
   *
   * @since 4.1.0
   */
  private function assignRegistrationGroups(int $userId): void
  {
    if (!$this->loadPhocaCart()) {
      return;
    }

    $db = Factory::getDBO();

    $query = $db->getQuery(true)
      ->select($db->qn('id'))
      ->from($db->qn('#__phocacart_groups'))
      ->where($db->qn('activate_registration') . ' = 1 OR ' . $db->qn('id') . ' = 1');

    $db->setQuery($query);
    $groups = $db->loadColumn();
    if ($groups)
      PhocacartGroup::storeGroupsById((int)$userId, 1, $groups);
  }

  /**
   * @param $user
   * @param $isnew
   * @param $success
   * @param $msg
   *
   *
   * @since 3.0.0
   */
  public function onUserAfterSave($user, $isnew, $success, $msg)
	{
    // If save wasn't successful, don't do anything
    if (!$success) {
      return;
    }

    // No user specified, do nothing
    if (!isset($user['id']) || !(int)$user['id']) {
      return;
    }

    // Change user email address
		if (!$isnew && isset($user['email']) && MailHelper::isEmailAddress($user['email'])) {
      // Billing address
      $this->updateUserEmail(
        $user['id'], $user['email'], 0,
        !!$this->params->get('user_billing_address', 1), !!$this->params->get('order_billing_address', 0)
      );

      // Shipping address
      $this->updateUserEmail(
        $user['id'], $user['email'], 1,
        !!$this->params->get('user_shipping_address', 0), !!$this->params->get('order_shipping_address', 0)
      );
		}

    // Assign user to customer groups based on settings
    if ($isnew) {
      $this->assignRegistrationGroups($user['id']);
    }
	}
}
