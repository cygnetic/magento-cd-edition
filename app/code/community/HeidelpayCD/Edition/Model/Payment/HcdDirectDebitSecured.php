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
class HeidelpayCD_Edition_Model_Payment_HcdDirectDebitSecured
    extends HeidelpayCD_Edition_Model_Payment_Abstract
{
    protected $_code = 'hcdDirectDebitSecured';
    protected $_canCapture = true;
    protected $_canCapturePartial = true;

    protected $_formBlockType = 'hcd/form_directDebitSecured';
    
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }
    
    public function isAvailable($quote = null)
    {
        $path = "payment/" . $this->_code . "/";
        $storeId = Mage::app()->getStore()->getId();

        
        // in case if insurence billing and shipping adress
            $billing = $this->getQuote()->getBillingAddress();
            $shipping = $this->getQuote()->getShippingAddress();
            
            if (($billing->getFirstname() != $shipping->getFirstname()) or
                ($billing->getLastname() != $shipping->getLastname()) or
                ($billing->getStreet() != $shipping->getStreet()) or
                ($billing->getPostcode() != $shipping->getPostcode()) or
                ($billing->getCity() != $shipping->getCity()) or
                ($billing->getCountry() != $shipping->getCountry())) {
                return false;
            }

        return parent::isAvailable($quote);
    }
    
    public function validate()
    {
        parent::validate();
        $payment = array();
        $params = array();
        $payment = Mage::app()->getRequest()->getPOST('payment');
        

        if (isset($payment['method']) and $payment['method'] == $this->_code) {
            if (array_key_exists($this->_code.'_salut', $payment)) {
                $params['NAME.SALUTATION'] =
                    (preg_match('/[A-z]{2}/', $payment[$this->_code.'_salut']))
                        ? $payment[$this->_code.'_salut'] : '';
            }
            
            if (array_key_exists($this->_code.'_dobday', $payment) &&
                array_key_exists($this->_code.'_dobmonth', $payment) &&
                array_key_exists($this->_code.'_dobyear', $payment)
                ) {
                $day    = (int)$payment[$this->_code.'_dobday'];
                $mounth = (int)$payment[$this->_code.'_dobmonth'];
                $year    = (int)$payment[$this->_code.'_dobyear'];
                
                if ($this->validateDateOfBirth($day, $mounth, $year)) {
                    $params['NAME.BIRTHDATE'] = $year.'-'.sprintf("%02d", $mounth).'-'.sprintf("%02d", $day);
                } else {
                    Mage::throwException(
                        $this->_getHelper()
                            ->__('The minimum age is 18 years for this payment methode.')
                    );
                }
            }
        
            if (empty($payment[$this->_code.'_holder'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a account holder'));
            }

            if (empty($payment[$this->_code.'_iban'])) {
                Mage::throwException($this->_getHelper()->__('Please specify a iban or account'));
            }

            if (empty($payment[$this->_code.'_bic'])) {
                if (!preg_match('/^[A-Za-z]{2}/', $payment[$this->_code.'_iban'])) {
                    Mage::throwException($this->_getHelper()->__('Please specify a bank code'));
                }
            }
        
            $params['ACCOUNT.HOLDER'] = $payment[$this->_code.'_holder'];
                
            if (preg_match('#^[\d]#', $payment[$this->_code.'_iban'])) {
                $params['ACCOUNT.NUMBER'] = $payment[$this->_code.'_iban'];
            } else {
                $params['ACCOUNT.IBAN'] = $payment[$this->_code.'_iban'];
            }
            
            if (preg_match('#^[\d]#', $payment[$this->_code.'_bic'])) {
                $params['ACCOUNT.BANK'] = $payment[$this->_code.'_bic'];
                $params['ACCOUNT.COUNTRY'] = $this->getQuote()->getBillingAddress()->getCountry();
            } else {
                $params['ACCOUNT.BIC'] = $payment[$this->_code.'_bic'];
            }

            $this->saveCustomerData($params);
            
            return $this;
        }
        
        return $this;
    }
    
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
}