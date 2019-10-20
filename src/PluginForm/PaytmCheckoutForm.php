<?php

namespace Drupal\commercepaytm\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Utility\Crypt;
use Drupal\commercepaytm\PaytmLibrary;

class PaytmCheckoutForm extends BasePaymentOffsiteForm {

		/*const PAYTM_API_TEST_URL = 'https://pguat.paytm.com/oltp-web/processTransaction';
		const PAYTM_API_URL = 'https://secure.paytm.in/oltp-web/processTransaction';*/
		/*const PAYTM_API_TEST_URL = 'https://securegw-stage.paytm.in/theia/processTransaction';
		const PAYTM_API_URL = 'https://securegw.paytm.in/theia/processTransaction';*/
		/**
		 * {@inheritdoc}
		 */
		public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
				$form = parent::buildConfigurationForm($form, $form_state);
				/** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
				$paytm_library = new PaytmLibrary();
				$payment = $this->entity;

				$redirect_method = 'post';
				/** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
				$payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

				$order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
				$order = Order::load($order_id);
				$user_id = \Drupal::currentUser()->id();
				$address = $order->getBillingProfile()->address->first();
				// $mode = $payment_gateway_plugin->getConfiguration()['pmode'];
				// $mode = $payment_gateway_plugin->getConfiguration()['mode'];
				$transactionURL=$payment_gateway_plugin->getConfiguration()['merchant_transaction_url'];
				$customCallBackURL=$payment_gateway_plugin->getConfiguration()['merchant_transaction_custom_callback_url'];
				$merchant_id = $payment_gateway_plugin->getConfiguration()['merchant_id'];
				$merchant_key = $payment_gateway_plugin->getConfiguration()['merchant_key'];
				$merchant_website = $payment_gateway_plugin->getConfiguration()['merchant_website'];
				$merchant_industry_type = $payment_gateway_plugin->getConfiguration()['merchant_industry_type'];
				$merchant_channel_id = $payment_gateway_plugin->getConfiguration()['merchant_channel_id'];
				$redirect_url = $transactionURL;
				$callback_url =  Url::FromRoute('commerce_payment.checkout.return', ['commerce_order' => $order_id, 'step' => 'payment'], array('absolute' => TRUE))->toString();
				if(trim($customCallBackURL)!=''){
					if(filter_var($customCallBackURL, FILTER_VALIDATE_URL)){
						$callback_url=$customCallBackURL;
					}
				}
				$paramList["MID"] = $merchant_id;
				$paramList["ORDER_ID"] = $order_id;
				$paramList["CUST_ID"] = $user_id;
				$paramList["INDUSTRY_TYPE_ID"] = 'Retail';
				$paramList["CHANNEL_ID"] = 'WEB';
				$paramList["TXN_AMOUNT"] = round($payment->getAmount()->getNumber(), 2);
				$paramList["CALLBACK_URL"] = $callback_url;
				$paramList["WEBSITE"] = $merchant_website;
				$paramList["INDUSTRY_TYPE_ID"] = $merchant_industry_type;
				$paramList["CHANNEL_ID"] = $merchant_channel_id;
				$paramList['CHECKSUMHASH'] = $paytm_library->getChecksumFromArray($paramList,$merchant_key);

				return $this->buildRedirectForm($form, $form_state, $redirect_url, $paramList, 'post');
		}
}
