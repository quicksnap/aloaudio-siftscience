<?php
/**
*
*/
class AloAudio_SiftScience_Helper_Data extends Mage_Core_Helper_Abstract
{
  public function userId()
  {
    if ( Mage::getSingleton('customer/session')->isLoggedIn() ) {
      $userId = Mage::helper('customer')->getCustomer()->getId();
      $email = Mage::helper('customer')->getCustomer()->getEmail();
    } else {
      $email = 'guest';
      $userId = Mage::getModel('aloaudio_siftscience/sessionId')->getSessionId();
    }

    // Both $email and $userId must have values
    if ( !empty($email) && !empty($userId) ) {
      return $email . '_' . $userId;
    } else {
      return '';
    }
  }

  public function sessionId()
  {
    return Mage::getModel('aloaudio_siftscience/sessionId')->getSessionId();
  }

  public function getScore($sift_userId)
  {
    $sift_apiKey = Mage::getStoreConfig('siftscience_options/general/rest_api_key');

    $ctx = stream_context_create(
      array('http'=>
          array(
              'timeout' => 2 // 2 seconds
          )
      )
    );
    $siftJson = file_get_contents("https://api.siftscience.com/v203/score/$sift_userId/?api_key=$sift_apiKey");
    $siftData = json_decode($siftJson, true);

    $score = null;
    if( array_key_exists('score', $siftData) ) {
      $score = round( $siftData['score'] * 100, 1 );
    }

    return $score;
  }

  public function getScoreUrl($sift_userId)
  {
    return 'https://siftscience.com/console/users/' . urlencode($sift_userId);
  }
}