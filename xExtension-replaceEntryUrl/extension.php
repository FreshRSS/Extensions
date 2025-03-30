<?php

declare(strict_types=1);
class replaceEntryUrlExtension extends Minz_Extension {
    const ALLOWED_LIST = [];  
    const GET_FULL_CONTENT = false;
   /**
	* @throws FreshRSS_Context_Exception
	*/
    public function init() {
      if (!FreshRSS_Context::hasSystemConf()) {
        throw new FreshRSS_Context_Exception('System configuration not initialised!');
      }
      $save = false;
      /*If you want to replace it during refresh, uncomment next line and comment out the line below. it used in test*/
      //$this->registerHook('entry_before_display', [self::class, 'processEntry']);
      $this->registerHook('entry_before_insert', [self::class, 'processEntry']);
      if (FreshRSS_Context::userConf()->attributeString('replaceEntryUrl_matchUrlKeyValues') === null) {
        FreshRSS_Context::userConf()->_attribute('replaceEntryUrl_matchUrlKeyValues', self::ALLOWED_LIST);
        $save = true;
      }
      if (FreshRSS_Context::userConf()->attributeBool('replaceEntryUrl_filterXPathContent') === null) {
        FreshRSS_Context::userConf()->_attribute('replaceEntryUrl_filterXPathContent', self::GET_FULL_CONTENT);
        $save = true;
      }
    }

    /**
	 * @throws FreshRSS_Context_Exception
	 */
    public function handleConfigureAction(): void {
      $this->registerTranslates();
  
      if (Minz_Request::isPost()) {
        FreshRSS_Context::userConf()->_attribute('replaceEntryUrl_matchUrlKeyValues', Minz_Request::paramString('replaceEntryUrl_matchUrlKeyValues', plaintext: true) ?: self::ALLOWED_LIST);
        FreshRSS_Context::userConf()->_attribute('replaceEntryUrl_filterXPathContent', Minz_Request::paramBoolean('replaceEntryUrl_filterXPathContent'));
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
      $use_default_if_empty = FreshRSS_Context::userConf()->attributeBool('replaceEntryUrl_filterXPathContent') ?? self::GET_FULL_CONTENT;
      $allow_array = ""; 
      $allow_url_str = FreshRSS_Context::userConf()->attributeString('replaceEntryUrl_matchUrlKeyValues');
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
      $article = "";
      if($response != false){
        $article = self:: extractMainContent($response,$my_xpath,$use_default_if_empty);
      }
      if($article != ""){
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
        curl_setopt($ch, CURLOPT_ENCODING, "gzip, deflate");
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
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

    public static function extractMainContent(string $content,string $my_xpath,bool $use_default_if_empty): string {
      $doc = new DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
      
      $xpath = new DOMXPath($doc);
      $nodes = $xpath->query($my_xpath);
      $mainContent = "";
      if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
        $mainContent = $doc->saveHTML($nodes->item(0));
      }
      elseif($use_default_if_empty)
      {
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
        return "";
      }
      return "";
    }

        
}
	
