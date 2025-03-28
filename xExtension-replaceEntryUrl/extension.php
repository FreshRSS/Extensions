<?php

declare(strict_types=1);
class replaceEntryUrlExtension extends Minz_Extension {
    const ALLOWED_LIST = [];  
    
   /**
	* @throws FreshRSS_Context_Exception
	*/
    public function init() {
      if (!FreshRSS_Context::hasSystemConf()) {
        throw new FreshRSS_Context_Exception('System configuration not initialised!');
      }
      $save = false;
      /*If you want to replace it during refresh, uncomment this line and comment out the line below. it used in test*/
      // $this->registerHook('entry_before_display', [self::class, 'processEntry']);
      $this->registerHook('entry_before_insert', [self::class, 'processEntry']);
      if (FreshRSS_Context::userConf()->attributeString('allow_url') === null) {
        FreshRSS_Context::userConf()->_attribute('allow_url', self::ALLOWED_LIST);
        $save = true;
      }
            
    }

    /**
	 * @throws FreshRSS_Context_Exception
	 */
    public function handleConfigureAction(): void {
      $this->registerTranslates();
  
      if (Minz_Request::isPost()) {
        FreshRSS_Context::userConf()->_attribute('allow_url', Minz_Request::paramString('allow_url', plaintext: true) ?: self::ALLOWED_LIST);
        FreshRSS_Context::userConf()->save();
      }
    }

    /**
     * Process the feed content before inserting the feed
     * @param FreshRSS_Entry $entry RSS article
     * @return FreshRSS_Entry Processed entries
     * @throws FreshRSS_Context_Exception 
     */
    public static function processEntry(FreshRSS_Entry $entry): FreshRSS_Entry {
        $allow_array = ""; 
        $allow_url_str = FreshRSS_Context::userConf()->attributeString('allow_url');
        if (!is_string($allow_url_str) || $allow_url_str === '') {
          return $entry;
        }
          $allow_array = json_decode($allow_url_str,true);
        
        $allow_url = [];
  
        if(json_last_error() === JSON_ERROR_NONE && is_array($allow_array)){
          foreach ($allow_array as $key => $value) {
            array_push($allow_url, (string)$key); 
          }
        }
        if(!is_array($allow_array)){
          return $entry;
        }
     

      
        $link = $entry->link();
        $my_xpath = self::isUrlAllowed($link,$allow_url,$allow_array);
        
        if (empty($my_xpath)) {
          return $entry;
      }
        $response = self::curlDownloadContent($link);
        if($response != false){
          $article = self:: extractMainContent($response,$my_xpath);
          $entry->_content ($article);
        }
        
      return $entry;
    }

    public static function curlDownloadContent(string $link): string|false {
      
      $ch = curl_init();
      if ($ch === false) {
        throw new RuntimeException('Failed to initialize cURL.');
      }
      if ($link !== '') {
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Optional: set timeout
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
          error_log('cURL Error: ' . curl_error($ch));
          $response = false;
        }
        curl_close($ch);
        if ($response !== false && is_string($response)) {
          return $response;
        }
      }
      return false;

    }

    public static function extractMainContent(string $content,string $my_xpath): string {
      $doc = new DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
      
      $xpath = new DOMXPath($doc);
      $nodes = $xpath->query($my_xpath);
      if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
        $mainContent = $doc->saveHTML($nodes->item(0));
      }else{
        $mainContent = $content;
      }
      libxml_clear_errors();     
      return  is_string($mainContent) ? $mainContent : '';
    }
    /**
     * @param string $url
     * @param list<string> $allowed 
     * @param array<mixed,mixed> $allow_array 
     */
    public static function isUrlAllowed(string $url, array $allowed, array $allow_array): string {
      $host = parse_url($url, PHP_URL_HOST);
      if (!is_string($host) || $host === ''){
          return "";
      }
      
      if (preg_match('/([a-z0-9-]+\.[a-z]+)$/i', $host, $matches)) {
          $host = $matches[1];
      }
      if (in_array(strtolower($host), array_map('strtolower', $allowed), true)) {
        $xpath_value = $allow_array[$host] ?? null; 
        if (is_string($xpath_value)) {
            return $xpath_value; 
        }
        return '';
      }
      return "";
    }

        
}
	
