<?php

/**
 * Direct debit secured payment method
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */
// @codingStandardsIgnoreLine magento marketplace namespace warning
class HeidelpayCD_Edition_Model_Payment_HcdDirectDebitSecured extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    /**
     * payment code
     *
     * @var string payment code
     */
    protected $_code = 'hcdddsec';

    /**
     * send basket information to basket api
     *
     * @var bool send basket information to basket api
     */
    protected $_canBasketApi = true;

    /**
     * set checkout form block
     *
     * @var string checkout form block
     */
    protected $_formBlockType = 'hcd/form_directDebitSecured';

    /**
     * over write existing info block
     * @var string
     */

    protected $_infoBlockType = 'hcd/info_directDebit';

    /**
     * Over wright from block
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * is payment method available
     *
     * @param null $quote
     *
     * @return bool is payment method available
     */
    public function isAvailable($quote = null)
    {
        $billing = $this->getQuote()->getBillingAddress();
        $shipping = $this->getQuote()->getShippingAddress();

        /* billing and shipping address has to match */
        if (($billing->getFirstname() != $shipping->getFirstname()) or
            ($billing->getLastname() != $shipping->getLastname()) or
            ($billing->getStreet() != $shipping->getStreet()) or
            ($billing->getPostcode() != $shipping->getPostcode()) or
            ($billing->getCity() != $shipping->getCity()) or
            ($billing->getCountry() != $shipping->getCountry())
        ) {
            return false;
        }

        /* payment method is b2c only */
        if (!empty($billing->getCompany())) {
            return false;
        }


        return parent::isAvailable($quote);
    }

    /**
     * Validate customer input on checkout
     *
     * @return $this
     */
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');


        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code . '_salutation', $payment)) {
                $params['NAME.SALUTATION'] =
                    (
                        $payment[$this->_code . '_salutation'] == 'MR' or
                        $payment[$this->_code . '_salutation'] == 'MRS'
                    )
                        ? $payment[$this->_code . '_salutation'] : '';
            }

            if (array_key_exists($this->_code . '_dobday', $payment) &&
                array_key_exists($this->_code . '_dobmonth', $payment) &&
                array_key_exists($this->_code . '_dobyear', $payment)
            ) {
                $day = (int)$payment[$this->_code . '_dobday'];
                $mounth = (int)$payment[$this->_code . '_dobmonth'];
                $year = (int)$payment[$this->_code . '_dobyear'];

                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] = $year . '-' . sprintf("%02d", $mounth) . '-' . sprintf("%02d", $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment methode.')
                    );
                }
            }

            if (empty($payment[$this->_code . '_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }

            if (empty($payment[$this->_code . '_iban'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a iban or account'));
            }

            $params['ACCOUNT.HOLDER'] = $payment[$this->_code . '_holder'];

            if (preg_match('#^[\d]#', $payment[$this->_code . '_iban'])) {
                $params['ACCOUNT.NUMBER'] = $payment[$this->_code . '_iban'];
            } else {
                $params['ACCOUNT.IBAN'] = $payment[$this->_code . '_iban'];
            }

            $this->saveCustomerData($params);
        }

        return $this;
    }

    /**
     * Payment information for invoice mail
     *
     * @param array $paymentData transaction response
     *
     * @return string return payment information text
     */
    public function showPaymentInfo($paymentData)
    {
        $loadSnippet = $this->_getHelper()->__("Direct Debit Info Text");

        $repl = array(
            '{AMOUNT}' => $paymentData['CLEARING_AMOUNT'],
            '{CURRENCY}' => $paymentData['CLEARING_CURRENCY'],
            '{Iban}' => $paymentData['ACCOUNT_IBAN'],
            '{Ident}' => $paymentData['ACCOUNT_IDENTIFICATION'],
            '{CreditorId}' => $paymentData['IDENTIFICATION_CREDITOR_ID'],
        );

        return strtr($loadSnippet, $repl);
    }

    /**
     * @inheritdoc
     */
    public function chargeBack($order, $message = "")
    {
        $message = Mage::helper('hcd')->__('debit failed');
        return parent::chargeBack($order, $message);
    }
}
