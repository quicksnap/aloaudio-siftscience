<?php

/**
 * Send checkout data to Sift Science API
 * @see https://siftscience.com/docs/rest-api#transactions
 */
class AloAudio_SiftScience_Model_Checkout_Observer
{

  public function post_order_hook($observer)
  {
    // Catch all exceptions here. Breaking checkout is bad.
    try {
      if ( Mage::getStoreConfig('siftscience_options/general/enable') != true ) {
        return $observer;
      }

      $event = $observer->getEvent();
      $order = $event->getOrder();

      if ( ! $order->getStoreId() ) {
        // If there's no StoreId, the order was placed in Admin: http://goo.gl/qntiI
        return $observer;
      }

      $shippingAddress  = $order->getShippingAddress();
      $billingAddress   = $order->getBillingAddress();

      $sift_api_key             = Mage::getStoreConfig('siftscience_options/general/rest_api_key');
      $sift_fraud_threshold     = Mage::getStoreConfig('siftscience_options/general/fraud_threshold');
      $sift_include_billing     = Mage::getStoreConfig('siftscience_options/general/include_billing');
      $sift_include_shipping    = Mage::getStoreConfig('siftscience_options/general/include_shipping');
      $sift_include_payment     = Mage::getStoreConfig('siftscience_options/general/include_payment');
      $session_id               = Mage::helper('aloaudio_siftscience')->sessionId();
      $user_id                  = Mage::helper('aloaudio_siftscience')->userId();
      $user_email               = $billingAddress->getEmail();
      $order_id                 = $order->getIncrementId();
      $amount                   = $order->getGrandTotal() * 1000000;
      $ip                       = Mage::helper('core/http')->getRemoteAddr(true);

      $data = array(

        // Universal fields
        '$api_key'             => $sift_api_key,
        '$type'                => '$transaction',
        '$user_id'             => $user_id,
        '$time'                => time(),
        '$user_email'          => $user_email,
        '$ip'                  => $ip,

        // Transaction events (required)
        '$amount'              => $amount,
        '$currency_code'       => 'USD',

      );

      // Transaction events (optional)
      $data = array_merge($data, array(

        '$transaction_id'      => $order_id,
        '$session_id'          => $session_id,

      ));

      // Payment data (optional)
      if ($sift_include_payment) {
        $data = array_merge($data, array(

          /*
            First six digits of the credit card, also known as the Issuer Identification Number (IIN).
            If specified, this must be six digits, with no alphabetic characters.
           */
          // '$billing_bin'         => $billingAddress->get,

          '$billing_last4'       => $order->getPayment()->getCcLast4(),
        ));
      }

      // Billing address (optional)
      if ($sift_include_billing) {
        $data = array_merge($data, array(

          '$billing_name'        => $billingAddress->getName(),
          '$billing_address1'    => $billingAddress->getStreet1(),
          '$billing_address2'    => $billingAddress->getStreet2(),
          '$billing_city'        => $billingAddress->getCity(),
          '$billing_region'      => $billingAddress->getRegionCode(),   // 2-digit state code (or full state name if no code available)
          '$billing_country'     => $billingAddress->getCountryId(),    // 2-digit country code
          '$billing_zip'         => $billingAddress->getPostcode(),

        ));
      }

      // Shipping address (optional)
      if ($sift_include_shipping) {
        $data = array_merge($data, array(

          '$shipping_address1'   => $shippingAddress->getStreet1(),
          '$shipping_address2'   => $shippingAddress->getStreet2(),
          '$shipping_city'       => $shippingAddress->getCity(),
          '$shipping_region'     => $shippingAddress->getRegionCode(),  // 2-digit state code (or full state name if no code available)
          '$shipping_country'    => $shippingAddress->getCountryId(),   // 2-digit country code
          '$shipping_zip'        => $shippingAddress->getPostcode(),

        ));
      }

      $data_string = json_encode($data);

      $ch = curl_init('https://api.siftscience.com/v202/events');

      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2-second timeout
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($data_string))
      );

      $result = curl_exec($ch);

      // Mage::log("SiftScience Debug Output: " . $result);

#### TODO: Handle error codes - https://siftscience.com/docs/rest-api#error_codes

      $order->setSiftscienceUserid($user_id);

      if ( Mage::getStoreConfig('siftscience_options/general/comment_creation') != false )
      {
        $score = Mage::helper('aloaudio_siftscience')->getScore($user_id);
        $score = $score !== NULL ? $score : 'N/A';
        $fraudAlert = (!empty($sift_fraud_threshold) && is_int($sift_fraud_threshold) && ($score > $sift_fraud_threshold)) ? '(FRAUD RISK) ' : '';
        $fraudRiskComment =
          'SiftScience Score: ' . $fraudAlert . $score ."\n" .
          'More info: ' . Mage::helper('aloaudio_siftscience')->getScoreUrl($user_id);

        $order->addStatusHistoryComment($fraudRiskComment);
      }

      $order->save();

    } catch ( Exception $e) {
      Mage::logException($e);
    }

    return $observer;
  }

}
