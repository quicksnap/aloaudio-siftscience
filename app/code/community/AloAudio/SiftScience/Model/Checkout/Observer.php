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
      $session_id               = Mage::helper('aloaudio_siftscience')->sessionId();
      $user_id                  = Mage::helper('aloaudio_siftscience')->userId();
      $user_email               = $shippingAddress->getEmail();
      $order_id                 = $order->getIncrementId();
      $amount                   = $order->getGrandTotal() * 1000000;

      $data = array(
        '$api_key'        => $sift_api_key,
        '$type'           => '$transaction',
        '$session_id'     => $session_id,
        '$user_id'        => $user_id,
        '$user_email'     => $user_email,
        '$transaction_id' => $order_id,
        '$currency_code'  => 'USD',
        '$amount'         => $amount,
      );

      $data_string = json_encode($data);

      $ch = curl_init('https://api.siftscience.com/v202/events');

      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2-second timeout
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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
        $fraudAlert = (!empty($sift_fraud_threshold) && ($score > $sift_fraud_threshold)) ? '(FRAUD RISK) ' : '';
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
