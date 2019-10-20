<?php

namespace Drupal\commercepaytm\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commercepaytm\PaytmLibrary;

use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Utility\Crypt;

/**
 * Provides the Paytm payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytm_payment",
 *   label = @Translation("Paytm Payment"),
 *   display_label = @Translation("Paytm"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commercepaytm\PluginForm\PaytmCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class PaytmCheckout extends OffsitePaymentGatewayBase {

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);
        // dpm($form);
        $merchantID='';
        if(isset($this->configuration['merchant_id'])){
            $merchantID=$this->configuration['merchant_id'];
        }
        $form['merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Id'),
            '#default_value' => $merchantID,
            '#required' => TRUE,
        ];
        $merchantKEY='';
        if(isset($this->configuration['merchant_key'])){
            $merchantKEY=$this->configuration['merchant_key'];
        }
        $form['merchant_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Key'),
            '#default_value' => $merchantKEY,
            '#required' => TRUE,
        ];
        $merchantWEB='';
        if(isset($this->configuration['merchant_website'])){
            $merchantWEB=$this->configuration['merchant_website'];
        }
        $form['merchant_website'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Website'),
            '#default_value' => $merchantWEB,
            '#required' => TRUE,
        ];

        $merchantIndustryType='';
        if(isset($this->configuration['merchant_industry_type'])){
            $merchantIndustryType=$this->configuration['merchant_industry_type'];
        }
        $form['merchant_industry_type'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Industry Type'),
            '#default_value' => $merchantIndustryType,
            '#required' => TRUE,
        ];
        $merchantChannelID='';
        if(isset($this->configuration['merchant_channel_id'])){
            $merchantChannelID=$this->configuration['merchant_channel_id'];
        }
        $form['merchant_channel_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Channel ID'),
            '#default_value' => $merchantChannelID,
            '#required' => TRUE,
        ];

        $merchantCUSTCALLBACKURL='';
        if(isset($this->configuration['merchant_transaction_custom_callback_url'])){
            $merchantCUSTCALLBACKURL=$this->configuration['merchant_transaction_custom_callback_url'];
        }
        $form['merchant_transaction_custom_callback_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Custom Call Back URL (if you want)'),
            '#default_value' => $merchantCUSTCALLBACKURL,
            '#required' => false,
        ];
        $merchantTRSCURL='';
        if(isset($this->configuration['merchant_transaction_url'])){
            $merchantTRSCURL=$this->configuration['merchant_transaction_url'];
        }
        $form['merchant_transaction_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Transaction URL'),
            '#default_value' => $merchantTRSCURL,
            '#required' => TRUE,
        ];
        $merchantTRSCSTATUSURL='';
        if(isset($this->configuration['merchant_transaction_status_url'])){
            $merchantTRSCSTATUSURL=$this->configuration['merchant_transaction_status_url'];
        }
        $form['merchant_transaction_status_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Transaction Status URL'),
            '#default_value' => $merchantTRSCSTATUSURL,
            '#required' => TRUE,
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['merchant_id'] = $values['merchant_id'];
            $this->configuration['merchant_key'] = $values['merchant_key'];
            $this->configuration['merchant_website'] = $values['merchant_website'];
            $this->configuration['merchant_industry_type'] = $values['merchant_industry_type'];
            $this->configuration['merchant_channel_id'] = $values['merchant_channel_id'];
            $this->configuration['merchant_transaction_custom_callback_url'] = $values['merchant_transaction_custom_callback_url'];
            $this->configuration['merchant_transaction_url'] = $values['merchant_transaction_url'];
            $this->configuration['merchant_transaction_status_url'] = $values['merchant_transaction_status_url'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request) {
        $paytm_library = new PaytmLibrary();
        $paramlist = array();
        $txnid                     = $request->get('TXNID');
        $paramlist['RESPCODE']     = $request->get('RESPCODE');
        $paramlist['RESPMSG']      = $request->get('RESPMSG');
        $paramlist['STATUS']       = $request->get('STATUS');
        $paramlist['MID']          = $request->get('MID');
        $paramlist['TXNAMOUNT']    = $request->get('TXNAMOUNT');
        $paramlist['ORDERID']      = $txnid;
        $paramlist['CHECKSUMHASH'] = $request->get('CHECKSUMHASH');
        $valid_checksum = $paytm_library->verifychecksum_e($paramlist, $this->configuration['merchant_key'], $paramlist['CHECKSUMHASH']);
        if($valid_checksum) {
            $a = 0;
            if($paramlist['STATUS'] == 'TXN_SUCCESS') {
                $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                $payment = $payment_storage->create([
                    'state' => 'authorization',
                    'amount' => $order->getTotalPrice(),
                    'payment_gateway' => $this->entityId,
                    'order_id' => $order->id(),
                    'test' => $this->getMode() == 'test',
                    'remote_id' => $order->id(),
                    'remote_state' => $paramlist['STATUS'],
                    'authorized' => $this->time->getRequestTime(),
                ]);
                $payment->save();
                drupal_set_message($this->t('Your payment was successful with Order id : @orderid and Transaction id : @transaction_id', ['@orderid' => $order->id(), '@transaction_id' => $txnid]));
            }
            else {
                drupal_set_message($this->t('The payment was declined. If the amount has been deducted will be refunded back within 7 Days.'), 'error');
                throw new HardDeclineException('The payment was declined. If the amount has been deducted will be refunded back in 2-3 Days.');
            }
        }
        else {
          drupal_set_message($this->t('The payment was declined. If the amount has been deducted will be refunded back within 7 Days.'), 'error');
          throw new HardDeclineException('The payment was declined. If the amount has been deducted will be refunded back in 2-3 Days.');
        }
    }
}
