<?php

class AloAudio_SiftScience_Block_Jssnippet extends Mage_Page_Block_Html
{
  public function getUserId()
  {
    return Mage::helper('aloaudio_siftscience')->userId();
  }

  public function getSessionId()
  {
    return Mage::helper('aloaudio_siftscience')->sessionId();
  }
}