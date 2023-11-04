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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Phoca\PhocaCart\Dispatcher\Dispatcher;
use Phoca\PhocaCart\Event;

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
        return require_once JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php';
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
            PhocacartGroup::storeGroupsById($userId, 1, $groups);
    }

	/**
	 * Adds Phoca Cart fields to form
	 *
	 * @param   Form|null  $form
	 *
	 * @return Form
	 *
	 * @throws Exception
	 * @since 5.0.0
	 */
	private function loadUserForm(Form $form): void
	{
		$fields = \PhocacartFormUser::getFormXml('', '', 0, 0, 1, 0, 'phocacart');
		$xml    = new SimpleXMLElement($fields['xml']);
		$form->load($xml);

		// Do not force user to fill Joomla fullname, when we have at least one "name" fields in Phoca Cart
		if (
			$form->getField('name_first', 'phocacart')
			|| $form->getField('name_middle', 'phocacart')
			|| $form->getField('name_last', 'phocacart')
			|| $form->getField('company', 'phocacart')
		) {
			$form->removeField('name');
		}

		// Do not duplicate email field
		$form->removeField('email', 'phocacart');
	}

	/**
	 * Runs on content preparation
	 *
	 * @param   string  $context  The context for the data
	 * @param   object  $data     An object containing the data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	public function onContentPrepareData($context, $data)
	{
		if (
			!in_array($context, ['com_users.registration'])
			|| !$this->params->get('integration_registration')
		) {
			return true;
		}

		$input = Factory::getApplication()->getInput();
		$inputData = $input->post->get('jform', [], 'array');

		if (is_array($inputData) && isset($inputData['phocacart']) && is_array($inputData['phocacart'])) {
			$phocaCart = $inputData['phocacart'];
			if (!isset($data->name) || empty($data->name)) {
				$userName = [];
				if ($name = ArrayHelper::getValue($phocaCart, 'name_first', null, 'string')) {
					$userName[] = $name;
				}
				if ($name = ArrayHelper::getValue($phocaCart, 'name_middle', null, 'string')) {
					$userName[] = $name;
				}
				if ($name = ArrayHelper::getValue($phocaCart, 'name_last', null, 'string')) {
					$userName[] = $name;
				}
				if (!$userName && ($name = ArrayHelper::getValue($phocaCart, 'company', null, 'string'))) {
					$userName[] = $name;
				}
				$data->name = implode(' ', $userName);
			}
		}

		return true;
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Form  $form  The form to be altered.
	 * @param   mixed $data  The associated data for the form.
	 *
	 * @return true
	 *
	 * @throws Exception
	 * @since 5.0.0
	 */
	public function onContentPrepareForm(Form $form, $data)
	{
		$name = $form->getName();
		if (
			!$this->params->get('integration_registration')
			|| !$this->loadPhocaCart()
			|| !in_array($name, ['com_users.registration'])
		) {
			return true;
		}

		$this->loadUserForm($form);

		$lang = Factory::getLanguage();
		$lang->load('com_phocacart');

		return true;
	}

    /**
     * @param array   $user     entered user data
     * @param bool    $isNew    true if this is a new user
     * @param bool    $success  true if saving the user worked
     * @param string  $msg      error message
     *
     * @since 3.0.0
     */
    public function onUserAfterSave($user, $isNew, $success, $error)
    {
        // If save wasn't successful, don't do anything
        if (!$success) {
            return;
        }

	    $userId = ArrayHelper::getValue($user, 'id', 0, 'int');

        // No user specified, do nothing
        if (!$userId) {
            return;
        }

	    $userEmail = ArrayHelper::getValue($user, 'email', null, 'string');
        // Change user email address
        if (!$isNew && $userEmail && MailHelper::isEmailAddress($userEmail)) {
            // Billing address
            $this->updateUserEmail(
	            $userId, $userEmail, 0,
                !!$this->params->get('user_billing_address', 1), !!$this->params->get('order_billing_address', 0)
            );

            // Shipping address
            $this->updateUserEmail(
                $userId, $userEmail, 1,
                !!$this->params->get('user_shipping_address', 0), !!$this->params->get('order_shipping_address', 0)
            );
        }

        // Assign user to customer groups based on settings
        if ($isNew) {
            $this->assignRegistrationGroups($userId);
        }

		// Create Phoca Cart customer
	    if (
			$isNew
			&& $this->params->get('integration_registration')
			&& isset($user['phocacart'])
			&& !empty($user['phocacart'])
	    ) {
		    $data = $user['phocacart'];
		    $data['user_id'] = $userId;
		    $data['type'] = 0;

		    // Copy Joomla Email to Phoca Cart email
		    if (isset($user['email1'])) {
			    $data['email'] = $user['email1'];
		    }

			Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_phocacart/tables');
		    $row = Table::getInstance('PhocacartUser', 'Table');
		    if ($row->bind($data)) {
			    $row->date = gmdate('Y-m-d H:i:s');
			    if ($row->check()) {
				    Dispatcher::dispatch(new Event\Tax\UserAddressBeforeSaveCheckout('com_phocacart.checkout',$row));
				    $row->store();
			    }
		    }
	    }
    }

	/**
	 * Remove Phoca Cart customer profile information for the deleted user
	 *  Method is called after user data is deleted from the database
	 *
	 * @param   array    $user     Holds the user data
	 * @param   boolean  $success  True if user was successfully stored in the database
	 * @param   string   $msg      Message
	 *
	 * @since 5.0.0
	 */
	public function onUserAfterDelete($user, $success, $msg): void
	{
		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');
		if (
			!$success
			|| !$userId
		) {
			return;
		}

		// delete PhocaCart User
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__phocacart_users'))
			->where($db->quoteName('user_id') . ' = :userid')
			->bind(':userid', $userId, ParameterType::INTEGER);
		$db->setQuery($query);
		$db->execute();
	}
}
