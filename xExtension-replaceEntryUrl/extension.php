<?php

declare(strict_types=1);
class replaceEntryUrlExtension extends Minz_Extension {
    const ALLOWED_LIST = [];  
    
    public function init() {
      if (!FreshRSS_Context::hasSystemConf()) {
        throw new FreshRSS_Context_Exception('System configuration not initialised!');
      }
      $save = false;
      /*If you want to replace it during refresh, uncomment this line and comment out the line below*/
      // $this->registerHook('entry_before_display', [self::class, 'processEntry']);
      $this->registerHook('entry_before_insert', [self::class, 'processEntry']);
      if (FreshRSS_Context::userConf()->attributeString('allow_url') == null) {
        FreshRSS_Context::userConf()->_attribute('allow_url', self::ALLOWED_LIST);
        $save = true;
      }
            
    }
    public function handleConfigureAction(): void {
      $this->registerTranslates();
  
      if (Minz_Request::isPost()) {
        FreshRSS_Context::userConf()->_attribute('allow_url', Minz_Request::paramString('allow_url', plaintext: true) ?: self::ALLOWED_LIST);
        FreshRSS_Context::userConf()->save();
      }
    }

    public static function processEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
        if(FreshRSS_Context::userConf()->attributeString('allow_url')){
          $allow_array = json_decode(FreshRSS_Context::userConf()->attributeString('allow_url'),true);
        }
        $allow_url = [];
  
        if(json_last_error() === JSON_ERROR_NONE && is_array($allow_array)){
          foreach ($allow_array as $key => $value) {
            array_push($allow_url,$key);
          }
        }
        if(!is_array($allow_array)){
          return $entry;
        }
     

      
        $link = $entry->link();
        $my_xpath = self::isUrlAllowed($link,$allow_url,$allow_array);
        
        if(!$my_xpath){
          return $entry;
        }
        $response = self::curlDownloadContent($link);
        if($response != false){
          $article = self:: extractMainContent($response,$my_xpath);
          $entry->_content ($article);
        }
        
      return $entry;
    }

    public static function curlDownloadContent(string $link) {
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $link);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
      curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Optional: set timeout
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        $response = false;
      }
      curl_close($ch);
      if ($response) {
           return($response);
      }
      return false;

    }

    public static function extractMainContent(string $content,string $my_xpath): string {
      $doc = new DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
      
      $xpath = new DOMXPath($doc);
      $nodes = $xpath->query($my_xpath);
      $mainContent = $nodes->length > 0 ?  $doc->saveHTML($nodes->item(0)) : $content;

      libxml_clear_errors();
      
      return $mainContent;
    }

    public static function isUrlAllowed(string $url, array $allowed, array $allow_array): string {
      $host = parse_url($url, PHP_URL_HOST);
      if (!$host) {
          return "";
      }

      if (strpos($host, 'www.') === 0) {
          $host = substr($host, 4);
      }
      if(in_array(strtolower($host), array_map('strtolower', $allowed), true)){
        return $allow_array[$host];
      }
      return "";
    }

        
}
	
